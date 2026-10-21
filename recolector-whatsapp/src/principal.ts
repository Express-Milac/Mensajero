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
import {
  AlmacenMultimediaDurable,
  type MultimediaPersistida,
} from './multimedia/almacen-multimedia-durable.js';
import {
  ColaDescargaMultimediaDurable,
  type TareaDescargaMultimediaDurable,
} from './multimedia/cola-descarga-multimedia-durable.js';
import type { EstadoConexion, MensajeProveedor } from './tipos.js';

const configuracion = cargarConfiguracion();
const logger = pino({ level: configuracion.nivelRegistro });
const cliente = new ClienteMensajero(
  configuracion.mensajeroUrl,
  configuracion.secreto,
  configuracion.claveConexion,
);
const cola = new ColaDurable(configuracion.rutaDatos);
const colaDescargasMultimedia = new ColaDescargaMultimediaDurable(configuracion.rutaDatos);
const monitor = new MonitorIntegridad(configuracion.rutaDatos);
const adaptador = new AdaptadorBaileys(configuracion.rutaSesion, logger);
const almacenMultimedia = new AlmacenMultimediaDurable(
  configuracion.rutaAlmacenamiento,
  configuracion.claveConexion,
);
const rutaAutorizados = join(configuracion.rutaDatos, 'chats-autorizados.json');
const rutaInicioOperacion = join(configuracion.rutaDatos, 'inicio-operacion.txt');
const tiposMultimedia = new Set(['foto', 'video', 'audio', 'documento']);
const descargasMultimediaEnCurso = new Set<string>();

let chatsAutorizados = new Set<string>();
let cerrando = false;
let conectadoAlgunaVez = false;
let estadoActual: EstadoConexion = 'desconectado';
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
  if (mensaje.tipoEvento === 'mensaje') {
    const base = mensaje.mensajeProveedorId
      ? `${mensaje.chatId}\n${mensaje.mensajeProveedorId}`
      : `${mensaje.chatId}\n${mensaje.recibidoEn}\n${mensaje.contenidoCrudo}`;

    return createHash('sha256').update(base, 'utf8').digest('hex');
  }

  const identidad = mensaje.mensajeProveedorId
    ?? mensaje.eventoExternoId
    ?? mensaje.mensajeObjetivoProveedorId
    ?? mensaje.recibidoEn;
  const hashContenido = createHash('sha256')
    .update(mensaje.contenidoCrudo, 'utf8')
    .digest('hex');

  return createHash('sha256')
    .update([
      mensaje.chatId,
      mensaje.tipoEvento,
      identidad,
      mensaje.mensajeObjetivoProveedorId ?? '',
      hashContenido,
    ].join('\n'), 'utf8')
    .digest('hex');
}

async function encolarMensaje(
  mensaje: MensajeProveedor,
  multimedia: MultimediaPersistida | null,
): Promise<void> {
  await cola.agregar('/api/pasarela/v1/eventos', {
    clave_conexion: configuracion.claveConexion,
    chat_id: mensaje.chatId,
    tipo_evento: mensaje.tipoEvento,
    tipo_mensaje: mensaje.tipoMensaje,
    evento_externo_id: mensaje.eventoExternoId,
    mensaje_proveedor_id: mensaje.mensajeProveedorId,
    id_remitente_proveedor: mensaje.remitenteProveedorId,
    nombre_remitente: mensaje.nombreRemitente ?? null,
    fuente_nombre_remitente: mensaje.fuenteNombreRemitente ?? null,
    telefono_remitente: mensaje.telefonoRemitente ?? null,
    id_mensaje_citado_proveedor: mensaje.mensajeCitadoProveedorId,
    id_mensaje_objetivo_proveedor: mensaje.mensajeObjetivoProveedorId,
    contenido_crudo: mensaje.contenidoCrudo,
    recibido_en: mensaje.recibidoEn,
    version_contrato: 'v3-recolector',
    multimedia: multimedia === null ? null : {
      identificador_archivo: multimedia.identificadorArchivo,
      ruta_relativa: multimedia.rutaRelativa,
      hash_sha256: multimedia.hashSha256,
      tamano_bytes: multimedia.tamanoBytes,
      tipo_mime: multimedia.tipoMime,
      nombre_original: multimedia.nombreOriginal,
      extension: multimedia.extension,
    },
  }, `mensaje-${claveMensaje(mensaje)}`);
}

async function procesarDescargaMultimedia(
  tarea: TareaDescargaMultimediaDurable,
  mensajeOriginal: MensajeProveedor | null = null,
  reportarPrimerFallo = false,
): Promise<boolean> {
  if (descargasMultimediaEnCurso.has(tarea.clave)) {
    return false;
  }

  descargasMultimediaEnCurso.add(tarea.clave);
  let etapa = 'reconstruccion';

  try {
    const mensaje = mensajeOriginal
      ?? adaptador.reconstruirMensaje(tarea.mensaje.contenidoCrudo);

    if (mensaje === null) {
      throw new Error('No fue posible reconstruir el mensaje multimedia pendiente.');
    }

    if (!tiposMultimedia.has(mensaje.tipoMensaje) || mensaje.multimedia === null) {
      throw new Error('La tarea durable no contiene una fuente multimedia descargable.');
    }

    etapa = 'persistencia_archivo';
    const multimedia = await almacenMultimedia.guardar(mensaje);

    if (multimedia === null) {
      throw new Error('La persistencia multimedia terminó sin producir un archivo durable.');
    }

    etapa = 'encolado_evento';
    await encolarMensaje(mensaje, multimedia);
    etapa = 'confirmacion_tarea';
    await colaDescargasMultimedia.confirmar(tarea);

    logger.info(
      {
        tipo: mensaje.tipoMensaje,
        bytes: multimedia.tamanoBytes,
        recuperada: mensajeOriginal === null,
      },
      'Multimedia persistida y evento colocado en cola durable; tarea de descarga confirmada.',
    );

    return true;
  } catch (error) {
    registrarError(error, 'La descarga multimedia permanece pendiente para reintento durable.');

    if (reportarPrimerFallo) {
      try {
        await encolarPeriodo(
          tarea.mensaje.recibidoEn,
          new Date().toISOString(),
          'fallo_descarga_multimedia',
          {
            tipo_mensaje: tarea.mensaje.tipoMensaje,
            etapa,
            reintento_durable: true,
          },
        );
      } catch (errorIntegridad) {
        registrarError(
          errorIntegridad,
          'No fue posible encolar el periodo de integridad del fallo multimedia.',
        );
      }
    }

    return false;
  } finally {
    descargasMultimediaEnCurso.delete(tarea.clave);
  }
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

async function cicloDescargasMultimedia(): Promise<void> {
  let espera = configuracion.intervaloColaMs;

  while (!cerrando) {
    const tareas = await colaDescargasMultimedia.listar();
    let huboFallo = false;

    for (const tarea of tareas) {
      if (!await procesarDescargaMultimedia(tarea)) {
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

    // El latido local siempre se conserva para detectar un cierre abrupto.
    // El remoto es efímero: nunca se acumula en la cola durante cortes de red.
    if (estadoActual === 'conectado') {
      try {
        await cliente.enviar('/api/pasarela/v1/latido', {
          clave_conexion: configuracion.claveConexion,
        });
      } catch (error) {
        logger.warn(
          {
            etapa: 'latido_remoto',
            estado_http: error instanceof ErrorMensajero ? error.estadoHttp : null,
          },
          'No se pudo actualizar el latido remoto; se reintentará en el próximo intervalo.',
        );
      }
    }

    await dormir(configuracion.intervaloLatidoMs);
  }
}

async function iniciar(): Promise<void> {
  await cola.preparar();
  await colaDescargasMultimedia.preparar();
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
  void cicloDescargasMultimedia();
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
      estadoActual = estado;
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

      if (tiposMultimedia.has(mensaje.tipoMensaje)) {
        if (mensaje.multimedia === null) {
          logger.error(
            { tipo: mensaje.tipoMensaje, causa: 'fuente_multimedia_ausente' },
            'WhatsApp entregó un mensaje multimedia sin fuente descargable.',
          );
          await encolarPeriodo(
            mensaje.recibidoEn,
            new Date().toISOString(),
            'fuente_multimedia_ausente',
            { tipo_mensaje: mensaje.tipoMensaje },
          );
          await encolarMensaje(mensaje, null);
          return;
        }

        const tareaDescarga = await colaDescargasMultimedia.agregar(mensaje);
        logger.info(
          { tipo: mensaje.tipoMensaje },
          'Mensaje multimedia registrado en cola durable de descarga antes de acceder al archivo.',
        );

        await procesarDescargaMultimedia(tareaDescarga, mensaje, true);
        return;
      }

      await encolarMensaje(mensaje, null);
      logger.info(
        {
          tipo_evento: mensaje.tipoEvento,
          tipo: mensaje.tipoMensaje,
          multimedia_persistida: false,
        },
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
  logger.info(
    {
      pendientes: await cola.cantidad(),
      descargas_multimedia_pendientes: await colaDescargasMultimedia.cantidad(),
    },
    'Recolector detenido; ninguna tarea pendiente se elimina al apagar.',
  );
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
