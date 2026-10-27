/**
 * Diagnóstico exclusivo de lectura: consulta la lista de chats autorizados
 * mediante el contrato firmado y la compara con la caché local.
 *
 * No inicia Baileys, no publica eventos, no cambia autorizaciones,
 * no drena/borra tareas y no imprime JID, secretos o contactos.
 */
import 'dotenv/config';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { ClienteMensajero, ErrorMensajero } from './cliente-mensajero.js';
import { cargarConfiguracion } from './configuracion.js';

async function ejecutar(): Promise<void> {
  const grupo = process.env.MENSAJERO_JID_DIAGNOSTICO?.trim();

  if (!grupo || !grupo.endsWith('@g.us')) {
    throw new Error('Falta MENSAJERO_JID_DIAGNOSTICO con el identificador del grupo a contrastar (no se imprimirá).');
  }

  const config = cargarConfiguracion();
  const rutaCache = join(config.rutaDatos, 'chats-autorizados.json');
  let listaCache: string[];

  try {
    const leido = JSON.parse(await readFile(rutaCache, 'utf8')) as unknown;
    if (!Array.isArray(leido) || !leido.every((valor) => typeof valor === 'string')) {
      throw new Error('La caché de autorizaciones no contiene una lista válida.');
    }
    listaCache = leido;
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== 'ENOENT') {
      throw error;
    }
    listaCache = [];
  }

  const cache = new Set(listaCache);
  const cliente = new ClienteMensajero(config.mensajeroUrl, config.secreto, config.claveConexion);
  let actuales: Set<string>;

  try {
    // Solo consulta: ruta firmada de autorización, sin registro de petición
    // ni publicación de trabajos del buzón.
    actuales = await cliente.obtenerChatsAutorizados();
  } catch (error) {
    if (error instanceof ErrorMensajero) {
      console.log(JSON.stringify({
        consulta_laravel: 'fallida',
        estado_http: error.estadoHttp,
        grupo_en_cache: cache.has(grupo),
        cantidad_cache: cache.size,
        detalles: 'Revisa servidor Laravel, URL y configuración de firma. No se ha modificado la cola.',
      }, null, 2));
      process.exitCode = 1;
      return;
    }

    throw error;
  }

  const cacheDiferente = cache.size !== actuales.size
    || [...cache].some((identificador) => !actuales.has(identificador));

  const destino = new URL(config.mensajeroUrl);

  console.log(JSON.stringify({
    consulta_laravel: 'correcta',
    destino_laravel: destino.origin,
    cantidad_servidor: actuales.size,
    cantidad_cache: cache.size,
    grupo_en_servidor: actuales.has(grupo),
    grupo_en_cache: cache.has(grupo),
    listas_diferentes: cacheDiferente,
    tareas_de_cola_modificadas: 0,
  }, null, 2));
}

ejecutar().catch((error) => {
  console.error(
    'No fue posible completar la consulta de autorizaciones:',
    error instanceof Error ? error.message : 'error desconocido',
  );
  process.exitCode = 1;
});
