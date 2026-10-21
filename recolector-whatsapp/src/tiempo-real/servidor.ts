import { createHmac, timingSafeEqual } from 'node:crypto';
import { createServer, type Server, type ServerResponse } from 'node:http';

interface Registrador {
  info(datos: Record<string, unknown>, mensaje: string): void;
  warn(datos: Record<string, unknown>, mensaje: string): void;
  error(datos: Record<string, unknown>, mensaje: string): void;
}

function firmaSegura(valor: string, firma: string, secreto: string): boolean {
  if (!/^[a-f0-9]{64}$/i.test(firma)) return false;
  const esperada = createHmac('sha256', secreto).update(valor).digest();
  return timingSafeEqual(esperada, Buffer.from(firma, 'hex'));
}

export function credencialValida(
  caduca: string | null,
  aleatorio: string | null,
  firma: string | null,
  secreto: string,
  ahora = Math.floor(Date.now() / 1000),
): boolean {
  if (!caduca || !/^[0-9]{10,12}$/.test(caduca) ||
      !aleatorio || !/^[a-f0-9]{32}$/.test(aleatorio) || !firma) return false;
  const vencimiento = Number(caduca);
  if (vencimiento < ahora || vencimiento > ahora + 95) return false;
  return firmaSegura(caduca + '.' + aleatorio, firma, secreto);
}

/**
 * Servidor SSE independiente del servidor HTTP de PHP.
 * Los avisos nunca contienen nombres, teléfonos ni textos de WhatsApp.
 */
export async function iniciarServidorTiempoReal(
  secreto: string,
  origenAplicacion: string,
  puerto: number,
  logger: Registrador,
): Promise<Server> {
  if (!Number.isInteger(puerto) || puerto < 1024 || puerto > 65535) {
    throw new Error('Puerto de tiempo real inválido.');
  }

  const clientes = new Set<ServerResponse>();
  const servidor = createServer(async (peticion, respuesta) => {
    const url = new URL(peticion.url ?? '/', 'http://127.0.0.1');
    const denegar = (codigo: number): void => {
      respuesta.writeHead(codigo, { 'Cache-Control': 'no-store' });
      respuesta.end();
    };

    if (peticion.method === 'GET' && url.pathname === '/eventos') {
      if (peticion.headers.origin !== origenAplicacion ||
          !credencialValida(
            url.searchParams.get('caduca'),
            url.searchParams.get('aleatorio'),
            url.searchParams.get('firma'),
            secreto,
          )) {
        denegar(403);
        return;
      }
      if (clientes.size >= 100) {
        denegar(503);
        return;
      }

      respuesta.writeHead(200, {
        'Content-Type': 'text/event-stream; charset=utf-8',
        'Cache-Control': 'no-store, no-transform',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no',
        'Access-Control-Allow-Origin': origenAplicacion,
        'Vary': 'Origin',
      });
      respuesta.write(': conectado\nretry: 2000\n\n');
      clientes.add(respuesta);

      const latido = setInterval(() => {
        if (!respuesta.destroyed) respuesta.write(': latido\n\n');
      }, 20_000);

      // Obliga a renovar autorización periódicamente en Laravel.
      const caducidadSesion = setTimeout(() => respuesta.end(), 10 * 60_000);
      const limpiar = (): void => {
        clearInterval(latido);
        clearTimeout(caducidadSesion);
        clientes.delete(respuesta);
      };
      respuesta.on('close', limpiar);
      return;
    }

    if (peticion.method === 'POST' && url.pathname === '/notificar') {
      let cuerpo = '';
      for await (const trozo of peticion) {
        cuerpo += trozo.toString('utf8');
        if (cuerpo.length > 4096) {
          denegar(413);
          return;
        }
      }

      const firma = String(peticion.headers['x-mensajero-firma-tr'] ?? '');
      if (!firmaSegura(cuerpo, firma, secreto)) {
        denegar(403);
        return;
      }

      try {
        const aviso: unknown = JSON.parse(cuerpo);
        if (typeof aviso !== 'object' || aviso === null) throw new Error('Aviso inválido.');
        const datos = aviso as Record<string, unknown>;
        if (!Number.isSafeInteger(datos.grupo_id) || Number(datos.grupo_id) < 1 ||
            !Number.isSafeInteger(datos.evento_id) || Number(datos.evento_id) < 1 ||
            !Number.isInteger(datos.marca_tiempo) ||
            Math.abs(Math.floor(Date.now() / 1000) - Number(datos.marca_tiempo)) > 60) {
          throw new Error('Datos o vigencia de aviso inválidos.');
        }

        // Solo se emite el identificador. Laravel vuelve a autorizar los datos al leerlos.
        const mensaje = 'event: cambio\ndata: ' +
          JSON.stringify({ grupo_id: datos.grupo_id, evento_id: datos.evento_id }) + '\n\n';
        for (const cliente of clientes) {
          if (!cliente.destroyed) cliente.write(mensaje);
        }
        respuesta.writeHead(202, { 'Cache-Control': 'no-store' });
        respuesta.end();
      } catch {
        denegar(422);
      }
      return;
    }

    denegar(404);
  });

  servidor.on('error', (error) => {
    logger.error({ error: error.message }, 'Error del canal SSE del tablero.');
  });

  await new Promise<void>((resolver, rechazar) => {
    const fallo = (error: Error): void => {
      servidor.off('listening', listo);
      rechazar(error);
    };
    const listo = (): void => {
      servidor.off('error', fallo);
      resolver();
    };
    servidor.once('error', fallo);
    servidor.once('listening', listo);
    servidor.listen(puerto, '127.0.0.1');
  });

  logger.info({ puerto, origen: origenAplicacion }, 'Canal SSE de Mensajero activo en la interfaz local.');
  return servidor;
}
