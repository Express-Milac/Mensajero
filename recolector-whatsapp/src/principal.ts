import 'dotenv/config';
import { createHash, randomUUID } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import { AdaptadorBaileys } from './adaptadores/baileys.js';
import { ClienteMensajero, ErrorMensajero } from './cliente-mensajero.js';
import { ColaDurable, type TareaDurable } from './cola/cola-durable.js';
import { cargarConfiguracion } from './configuracion.js';
import { MonitorIntegridad } from './monitor-integridad.js';
import type { EstadoConexion, MensajeProveedor } from './tipos.js';

const configuracion = cargarConfiguracion();
const logger = pino({ level: configuracion.nivelRegistro });
const cliente = new ClienteMensajero(
  configuracion.mensajeroUrl,
  configuracion.secreto,
  configuracion.claveConexion,
);
const cola = new ColaDurable(configuracion.rutaDatos);
const monitor = new MonitorIntegridad(configuracion.rutaDatos);
const adaptador = new AdaptadorBaileys(configuracion.rutaSesion, logger);
const rutaAutorizados = join(configuracion.rutaDatos, 'chats-autorizados.json');
const rutaInicioOperacion = join(configuracion.rutaDatos, 'inicio-operacion.txt');

let chatsAutorizados = new Set<string>();
let cerrando = false;
let conectadoAlgunaVez = false;
let desconexionDesde: string | null = null;
let inicioOperacionMs = 0;

async function obtenerInicioOperacion(): Promise<number> {
  await mkdir(configuracion.rutaDatos, { recursive: true });

  try {
    const existente = (await readFile(rutaInicioOperacion, 'utf8')).trim();
    const marca = Date.parse(existente);

    if (Number.isFinite(marca)) {
      return marca;
    }
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== 'ENOENT') {
      throw error;
    }
  }

  const ahora = new Date().toISOString();
  await writeFile(rutaInicioOperacion, ahora, { encoding: 'utf8', mode: 0o600 });

  return Date.parse(ahora);
}

async function cargarAutorizacionesLocales(): Promise<void> {
  try {
    const chats = JSON.parse(await readFile(rutaAutorizados, 'utf8')) as string[];
    chatsAutorizados = new Set(chats.filter((chat) => typeof chat === 'string'));
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== 'ENOENT') {
      logger.warn({ error }, 'No fue posible leer la caché local de grupos autorizados.');
    }
  }
}

async function actualizarAutorizaciones(): Promise<void> {
  const nuevos = await cliente.obtenerChatsAutorizados();
  chatsAutorizados = nuevos;
  await mkdir(configuracion.rutaDatos, { recursive: true });
  await writeFile(rutaAutorizados, JSON.stringify([...nuevos]), {
    encoding: 'utf8',
    mode: 0o600,
  });
  logger.info({ cantidad: nuevos.size }, 'Lista permitida actualizada.');
}

async function encolarEstado(
  estado: EstadoConexion,
  detalles: Record<string, unknown> = {},
): Promise<void> {
  const copia = { ...detalles };
  const idCuenta = typeof copia.id_cuenta_externa === 'string'
    ? copia.id_cuenta_externa
    : null;
  delete copia.id_cuenta_externa;

  await cola.agregar('/api/pasarela/v1/estado-conexion', {
    clave_conexion: configuracion.claveConexion,
    estado,
    id_cuenta_externa: idCuenta,
    metadatos: {
      componente: 'recolector_whatsapp',
      biblioteca: '@whiskeysockets/baileys',
      ...copia,
    },
  }, `estado-${randomUUID()}`);
}

async function encolarPeriodo(
  inicioEn: string,
  finEn: string,
  causa: string,
  detalles: Record<string, unknown> = {},
): Promise<void> {
  const identificador = createHash('sha256')
    .update(`${configuracion.claveConexion}\n${inicioEn}\n${finEn}\n${causa}`, 'utf8')
    .digest('hex');

  await cola.agregar('/api/pasarela/v1/integridad', {
    clave_conexion: configuracion.claveConexion,
    identificador_periodo: identificador,
    inicio_en: inicioEn,
    fin_en: finEn,
    causa,
    detalles,
  }, `integridad-${identificador}`);
}

function claveMensaje(mensaje: MensajeProveedor): string {
  const base = mensaje.mensajeProveedorId
    ? `${mensaje.chatId}\n${mensaje.mensajeProveedorId}`
    : `${mensaje.chatId}\n${mensaje.recibidoEn}\n${mensaje.contenidoCrudo}`;

  return createHash('sha256').update(base, 'utf8').digest('hex');
}

async function encolarMensaje(mensaje: MensajeProveedor): Promise<void> {
  await cola.agregar('/api/pasarela/v1/eventos', {
    clave_conexion: configuracion.claveConexion,
    chat_id: mensaje.chatId,
    tipo_evento: 'mensaje',
    tipo_mensaje: mensaje.tipoMensaje,
    evento_externo_id: mensaje.eventoExternoId,
    mensaje_proveedor_id: mensaje.mensajeProveedorId,
    contenido_crudo: mensaje.contenidoCrudo,
    recibido_en: mensaje.recibidoEn,
    version_contrato: 'v1-recolector',
  }, `mensaje-${claveMensaje(mensaje)}`);
}

async function enviarTarea(tarea: TareaDurable): Promise<boolean> {
  try {
    await cliente.enviar(tarea.ruta, tarea.cuerpo);
    await cola.confirmar(tarea);

    return true;
  } catch (error) {
    registrarError(error, `La tarea ${tarea.id} permanece en cola durable.`);

    return false;
  }
}

async function bombearCola(): Promise<void> {
  let espera = configuracion.intervaloColaMs;

  while (!cerrando) {
    const tareas = await cola.listar();
    let huboFallo = false;

    for (const tarea of tareas) {
      if (!await enviarTarea(tarea)) {
        huboFallo = true;
      }
    }

    espera = huboFallo
      ? Math.min(espera * 2, configuracion.maxReintentoMs)
      : configuracion.intervaloColaMs;

    await dormir(tareas.length > 0 && !huboFallo ? 100 : espera);
  }
}

async function cicloAutorizaciones(): Promise<void> {
  while (!cerrando) {
    try {
      await actualizarAutorizaciones();
    } catch (error) {
      registrarError(error, 'No fue posible actualizar la lista permitida; se conserva la caché local.');
    }

    await dormir(configuracion.intervaloAutorizacionesMs);
  }
}

async function cicloGrupos(): Promise<void> {
  while (!cerrando) {
    await dormir(configuracion.intervaloGruposMs);

    if (cerrando) {
      return;
    }

    try {
      await adaptador.sincronizarGrupos();
    } catch (error) {
      registrarError(error, 'Falló la resincronización periódica de grupos.');
    }
  }
}

async function cicloLatido(): Promise<void> {
  while (!cerrando) {
    await monitor.latido();
    await dormir(configuracion.intervaloLatidoMs);
  }
}

async function iniciar(): Promise<void> {
  await cola.preparar();
  await cargarAutorizacionesLocales();
  inicioOperacionMs = await obtenerInicioOperacion();

  const huecoReinicio = await monitor.iniciar();

  if (huecoReinicio !== null) {
    await encolarPeriodo(
      huecoReinicio.inicioEn,
      huecoReinicio.finEn,
      huecoReinicio.causa,
    );
  }

  try {
    await actualizarAutorizaciones();
  } catch (error) {
    registrarError(error, 'Mensajero no respondió al inicio; se usará la última lista permitida guardada.');
  }

  void bombearCola();
  void cicloAutorizaciones();
  void cicloGrupos();
  void cicloLatido();

  await adaptador.iniciar({
    alQr: (qr) => {
      console.log('\nEscanea este QR únicamente con el NÚMERO RECOLECTOR CORPORATIVO DEDICADO:\n');
      qrcode.generate(qr, { small: true });
      console.log('\nNo uses el número principal de MILAC. La sesión queda fuera de Git.\n');
    },
    alEstado: async (estado, detalles = {}) => {
      const ahora = new Date().toISOString();

      if (estado === 'conectado') {
        if (desconexionDesde !== null) {
          await encolarPeriodo(desconexionDesde, ahora, 'desconexion_whatsapp', detalles);
          desconexionDesde = null;
        }

        conectadoAlgunaVez = true;
      } else if (conectadoAlgunaVez && ['desconectado', 'error', 'requiere_vinculacion'].includes(estado)) {
        desconexionDesde ??= ahora;
      }

      await encolarEstado(estado, detalles);
      logger.info({ estado }, 'Estado del recolector actualizado.');
    },
    alGrupos: async (grupos) => {
      const cuerpo = {
        clave_conexion: configuracion.claveConexion,
        chats: grupos.map((grupo) => ({
          id_chat_proveedor: grupo.idChatProveedor,
          tipo_chat: grupo.tipoChat,
          nombre_grupo: grupo.nombreGrupo,
          es_activo: grupo.esActivo,
        })),
      };
      const hash = createHash('sha256').update(JSON.stringify(cuerpo), 'utf8').digest('hex');
      await cola.agregar('/api/pasarela/v1/chats-descubiertos', cuerpo, `grupos-${hash}`);
      logger.info({ cantidad: grupos.length }, 'Lista de grupos colocada en cola durable.');
    },
    alMensaje: async (mensaje) => {
      if (mensaje.tipoMensaje === 'sticker') {
        logger.info({ motivo: 'sticker' }, 'Mensaje de grupo descartado antes de la cola durable.');
        return;
      }

      const marcaMensaje = Date.parse(mensaje.recibidoEn);

      if (Number.isFinite(marcaMensaje) && marcaMensaje < inicioOperacionMs) {
        logger.info(
          { motivo: 'anterior_inicio_operacion', tipo: mensaje.tipoMensaje },
          'Mensaje de grupo descartado antes de la cola durable.',
        );
        return;
      }

      if (!chatsAutorizados.has(mensaje.chatId)) {
        logger.info(
          { motivo: 'grupo_no_autorizado', tipo: mensaje.tipoMensaje },
          'Mensaje de grupo descartado antes de la cola durable.',
        );
        return;
      }

      await encolarMensaje(mensaje);
      logger.info(
        { tipo: mensaje.tipoMensaje },
        'Mensaje persistido primero en la cola local durable.',
      );
    },
  });
}

async function cerrar(senal: string): Promise<void> {
  if (cerrando) {
    return;
  }

  cerrando = true;
  logger.info({ senal }, 'Cerrando recolector de WhatsApp.');
  await encolarEstado('desconectado', { motivo: `proceso_${senal.toLowerCase()}` });
  await adaptador.detener();
  await monitor.cerrar();
  logger.info({ pendientes: await cola.cantidad() }, 'Recolector detenido; ninguna tarea pendiente se elimina al apagar.');
}

function dormir(ms: number): Promise<void> {
  return new Promise((resolver) => setTimeout(resolver, ms));
}

function registrarError(error: unknown, mensaje: string): void {
  if (error instanceof ErrorMensajero) {
    logger.error(
      {
        error: error.message,
        estadoHttp: error.estadoHttp,
        respuesta: error.respuesta,
      },
      mensaje,
    );

    return;
  }

  logger.error({ error }, mensaje);
}

process.once('SIGINT', () => void cerrar('SIGINT').finally(() => process.exit(0)));
process.once('SIGTERM', () => void cerrar('SIGTERM').finally(() => process.exit(0)));

iniciar().catch((error) => {
  registrarError(error, 'No fue posible iniciar el recolector.');
  process.exitCode = 1;
});
