import 'dotenv/config';
import pino from 'pino';
import qrcode from 'qrcode-terminal';
import { AdaptadorBaileys } from './adaptadores/baileys.js';
import { cargarConfiguracion } from './configuracion.js';
import { ClienteMensajero, ErrorMensajero } from './cliente-mensajero.js';
import type { EstadoConexion } from './tipos.js';

const configuracion = cargarConfiguracion();
const logger = pino({ level: configuracion.nivelRegistro });
const cliente = new ClienteMensajero(
  configuracion.mensajeroUrl,
  configuracion.secreto,
  configuracion.claveConexion,
);
const adaptador = new AdaptadorBaileys(configuracion.rutaSesion, logger);

let chatsAutorizados = new Set<string>();
let cerrando = false;

async function actualizarAutorizaciones(): Promise<void> {
  try {
    chatsAutorizados = await cliente.obtenerChatsAutorizados();
    logger.info({ cantidad: chatsAutorizados.size }, 'Lista permitida actualizada.');
  } catch (error) {
    registrarError(error, 'No fue posible actualizar la lista permitida.');
  }
}

async function actualizarEstado(
  estado: EstadoConexion,
  detalles: Record<string, unknown> = {},
): Promise<void> {
  try {
    await cliente.actualizarEstado(estado, detalles);
  } catch (error) {
    registrarError(error, `No fue posible informar el estado ${estado}.`);
  }
}

await adaptador.iniciar({
  alQr: (qr) => {
    console.log('\nEscanea este QR únicamente con la cuenta de WhatsApp de prueba:\n');
    qrcode.generate(qr, { small: true });
    console.log('\nLa sesión se guardará en la ruta privada configurada para no pedir QR en cada reinicio.\n');
  },
  alEstado: async (estado, detalles = {}) => {
    logger.info({ estado }, 'Estado de WhatsApp actualizado.');
    await actualizarEstado(estado, detalles);

    if (estado === 'conectado') {
      await actualizarAutorizaciones();
    }
  },
  alGrupos: async (grupos) => {
    try {
      await cliente.sincronizarChats(grupos);
      logger.info({ cantidad: grupos.length }, 'Grupos de WhatsApp sincronizados con Mensajero.');
      await actualizarAutorizaciones();
    } catch (error) {
      registrarError(error, 'No fue posible sincronizar los grupos descubiertos.');
    }
  },
  alMensaje: async (mensaje) => {
    if (mensaje.tipoMensaje === 'sticker') {
      return;
    }

    if (!chatsAutorizados.has(mensaje.chatId)) {
      logger.debug({ chatId: mensaje.chatId }, 'Mensaje descartado antes de enviarlo a Laravel: chat no autorizado.');
      return;
    }

    try {
      await cliente.capturarEvento(mensaje);
      logger.info(
        { chatId: mensaje.chatId, tipo: mensaje.tipoMensaje, evento: mensaje.eventoExternoId },
        'Evento entregado a Mensajero.',
      );
    } catch (error) {
      registrarError(error, 'No fue posible entregar un evento a Mensajero.');
    }
  },
});

const temporizadorAutorizaciones = setInterval(
  () => void actualizarAutorizaciones(),
  configuracion.intervaloAutorizacionesMs,
);
const temporizadorGrupos = setInterval(
  () => void adaptador.sincronizarGrupos().catch((error) => registrarError(error, 'Falló la resincronización de grupos.')),
  configuracion.intervaloGruposMs,
);

async function cerrar(senal: string): Promise<void> {
  if (cerrando) {
    return;
  }

  cerrando = true;
  clearInterval(temporizadorAutorizaciones);
  clearInterval(temporizadorGrupos);
  logger.info({ senal }, 'Cerrando pasarela de WhatsApp.');
  await actualizarEstado('desconectado', { motivo: `proceso_${senal.toLowerCase()}` });
  await adaptador.detener();
  process.exit(0);
}

process.on('SIGINT', () => void cerrar('SIGINT'));
process.on('SIGTERM', () => void cerrar('SIGTERM'));

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
