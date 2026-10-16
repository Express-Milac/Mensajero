import makeWASocket, {
  BufferJSON,
  DisconnectReason,
  fetchLatestWaWebVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import type { Logger } from 'pino';
import { clasificarTipoMensaje, fechaIsoDesdeMarca } from '../mensajes.js';
import type {
  AdaptadorWhatsapp,
  ChatDescubierto,
  ManejadoresWhatsapp,
  MensajeProveedor,
} from '../tipos.js';

export class AdaptadorBaileys implements AdaptadorWhatsapp {
  private socket: ReturnType<typeof makeWASocket> | null = null;
  private manejadores: ManejadoresWhatsapp | null = null;
  private deteniendo = false;
  private reconexionProgramada = false;
  private temporizadorIntegridad: NodeJS.Timeout | null = null;
  private secuenciaConexion = 0;
  private errorRecepcionMarcado = false;
  private recepcionConfirmada = false;

  public constructor(
    private readonly rutaSesion: string,
    private readonly logger: Logger,
  ) {}

  public async iniciar(manejadores: ManejadoresWhatsapp): Promise<void> {
    this.manejadores = manejadores;
    this.deteniendo = false;
    await this.conectar();
  }

  public async sincronizarGrupos(): Promise<void> {
    if (this.socket === null || this.manejadores === null) {
      return;
    }

    const grupos = await this.socket.groupFetchAllParticipating();
    const descubiertos: ChatDescubierto[] = Object.entries(grupos).map(([jid, grupo]) => ({
      idChatProveedor: jid,
      tipoChat: 'grupo',
      nombreGrupo: grupo.subject ?? null,
      esActivo: true,
    }));

    await this.manejadores.alGrupos(descubiertos);
  }

  public async detener(): Promise<void> {
    this.deteniendo = true;
    this.cancelarVigilanciaIntegridad();

    if (this.socket !== null) {
      await this.socket.end(new Error('Detención solicitada por Mensajero.'));
    }

    this.socket = null;
  }

  private async conectar(): Promise<void> {
    if (this.manejadores === null || this.deteniendo) {
      return;
    }

    await this.manejadores.alEstado('conectando');

    const { state, saveCreds } = await useMultiFileAuthState(this.rutaSesion);
    const versionWeb = await fetchLatestWaWebVersion({});

    if (!versionWeb.isLatest) {
      this.logger.warn(
        { error: versionWeb.error },
        'No fue posible consultar la versión actual de WhatsApp Web; se usará la versión de respaldo de Baileys.',
      );
    }

    this.logger.info(
      { version: versionWeb.version.join('.'), actual: versionWeb.isLatest },
      'Versión de WhatsApp Web seleccionada para la conexión.',
    );

    const socket = makeWASocket({
      auth: state,
      version: versionWeb.version,
      // Los logs internos de Baileys pueden incluir JID, nodos cifrados y metadatos del proveedor.
      // Mensajero expone únicamente sus propios eventos sanitizados para evitar fuga de información.
      logger: this.logger.child({ componente: 'baileys' }, { level: 'silent' }),
      markOnlineOnConnect: false,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
    });

    this.secuenciaConexion += 1;
    const secuencia = this.secuenciaConexion;
    this.errorRecepcionMarcado = false;
    this.recepcionConfirmada = false;
    this.socket = socket;
    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', (actualizacion) => {
      void this.manejarActualizacionConexion(actualizacion, secuencia);
    });

    socket.ev.on('messages.upsert', (evento) => {
      void this.manejarMensajes(evento.messages, evento.type);
    });
  }

  private async manejarActualizacionConexion(actualizacion: {
    connection?: string | null;
    lastDisconnect?: { error?: unknown } | null;
    qr?: string | null;
    receivedPendingNotifications?: boolean;
  }, secuencia: number): Promise<void> {
    if (this.manejadores === null || secuencia !== this.secuenciaConexion) {
      return;
    }

    try {
      if (actualizacion.qr) {
        this.manejadores.alQr(actualizacion.qr);
        await this.manejadores.alEstado('requiere_vinculacion');
      }

      if (actualizacion.receivedPendingNotifications === true) {
        await this.confirmarRecepcionActiva('confirmacion_buffer_inicial');
      }

      if (actualizacion.connection === 'open') {
        this.reconexionProgramada = false;
        await this.manejadores.alEstado('conectado', {
          id_cuenta_externa: this.socket?.user?.id ?? null,
        });
        await this.sincronizarGrupos();

        // La confirmación del búfer puede llegar antes que connection=open.
        // No rearmar el watchdog si el flujo ya fue confirmado en esta misma conexión.
        if (!this.recepcionConfirmada && actualizacion.receivedPendingNotifications !== true) {
          this.iniciarVigilanciaIntegridad(secuencia);
        }
      }

      if (actualizacion.connection === 'close') {
        this.cancelarVigilanciaIntegridad();
        const codigo = this.codigoDesconexion(actualizacion.lastDisconnect?.error);
        const sesionCerrada = codigo === DisconnectReason.loggedOut;

        this.logger.warn(
          { codigo_desconexion: codigo },
          'WhatsApp cerró la conexión del recolector.',
        );

        await this.manejadores.alEstado(
          sesionCerrada ? 'requiere_vinculacion' : 'desconectado',
          { codigo_desconexion: codigo },
        );

        if (!sesionCerrada && !this.deteniendo) {
          this.programarReconexion();
        }
      }
    } catch (error) {
      this.logger.error({ error }, 'Fallo al procesar cambio de conexión de WhatsApp.');
    }
  }

  private async manejarMensajes(mensajes: unknown[], tipoEvento: string): Promise<void> {
    if (this.manejadores === null || !['notify', 'append'].includes(tipoEvento)) {
      return;
    }

    if (mensajes.length > 0) {
      await this.confirmarRecepcionActiva('messages_upsert');
    }

    let candidatosGrupo = 0;
    let normalizadosGrupo = 0;

    for (const mensaje of mensajes) {
      try {
        if (this.esCandidatoGrupo(mensaje)) {
          candidatosGrupo += 1;
        }

        const normalizado = this.normalizarMensaje(mensaje);

        if (normalizado !== null) {
          normalizadosGrupo += 1;
          await this.manejadores.alMensaje(normalizado);
        }
      } catch (error) {
        this.logger.error({ error }, 'No fue posible normalizar un mensaje de WhatsApp.');
      }
    }

    this.logger.info(
      {
        tipo_evento: tipoEvento,
        recibidos: mensajes.length,
        candidatos_grupo: candidatosGrupo,
        normalizados_grupo: normalizadosGrupo,
      },
      'Lote de mensajes de WhatsApp procesado.',
    );
  }

  private esCandidatoGrupo(valor: unknown): boolean {
    if (typeof valor !== 'object' || valor === null) {
      return false;
    }

    const mensaje = valor as {
      key?: { remoteJid?: string | null };
      message?: unknown;
    };

    return Boolean(
      mensaje.key?.remoteJid?.endsWith('@g.us')
      && mensaje.message,
    );
  }

  private normalizarMensaje(valor: unknown): MensajeProveedor | null {
    if (typeof valor !== 'object' || valor === null) {
      return null;
    }

    const mensaje = valor as {
      key?: { remoteJid?: string | null; id?: string | null };
      message?: unknown;
      messageTimestamp?: unknown;
    };
    const chatId = mensaje.key?.remoteJid;

    if (!chatId || !chatId.endsWith('@g.us') || !mensaje.message) {
      return null;
    }

    const contenidoCrudo = JSON.stringify(valor, BufferJSON.replacer);

    if (contenidoCrudo === undefined) {
      return null;
    }

    return {
      chatId,
      tipoMensaje: clasificarTipoMensaje(mensaje.message),
      eventoExternoId: mensaje.key?.id ?? null,
      mensajeProveedorId: mensaje.key?.id ?? null,
      contenidoCrudo,
      recibidoEn: fechaIsoDesdeMarca(mensaje.messageTimestamp),
    };
  }

  private iniciarVigilanciaIntegridad(secuencia: number): void {
    this.cancelarVigilanciaIntegridad();

    if (this.recepcionConfirmada) {
      return;
    }

    this.temporizadorIntegridad = setTimeout(() => {
      if (
        this.deteniendo
        || secuencia !== this.secuenciaConexion
        || this.manejadores === null
        || this.recepcionConfirmada
      ) {
        return;
      }

      this.errorRecepcionMarcado = true;
      this.logger.error(
        { causa: 'buffer_inicial_sin_confirmacion' },
        'WhatsApp mantiene el socket abierto, pero no confirmó el flujo de mensajes; la integridad de captura no puede garantizarse.',
      );
      void this.manejadores.alEstado('error', {
        causa: 'buffer_inicial_sin_confirmacion',
        impacto: 'integridad_no_confirmada',
      }).catch((error) => {
        this.logger.error({ error }, 'No fue posible reportar el error de integridad de recepción.');
      });
    }, 30_000);
  }

  private cancelarVigilanciaIntegridad(): void {
    if (this.temporizadorIntegridad !== null) {
      clearTimeout(this.temporizadorIntegridad);
      this.temporizadorIntegridad = null;
    }
  }

  private async confirmarRecepcionActiva(origen: string): Promise<void> {
    this.cancelarVigilanciaIntegridad();

    if (!this.recepcionConfirmada) {
      this.recepcionConfirmada = true;
      this.logger.info({ origen }, 'Flujo de mensajes de WhatsApp confirmado.');
    }

    if (!this.errorRecepcionMarcado || this.manejadores === null) {
      return;
    }

    this.errorRecepcionMarcado = false;
    this.logger.info({ origen }, 'Se recuperó el flujo de mensajes de WhatsApp.');
    await this.manejadores.alEstado('conectado', {
      recuperacion_recepcion: origen,
    });
  }

  private codigoDesconexion(error: unknown): number | null {
    if (typeof error !== 'object' || error === null) {
      return null;
    }

    const salida = (error as { output?: { statusCode?: unknown } }).output;
    return typeof salida?.statusCode === 'number' ? salida.statusCode : null;
  }

  private programarReconexion(): void {
    if (this.reconexionProgramada || this.deteniendo) {
      return;
    }

    this.reconexionProgramada = true;
    setTimeout(() => {
      this.reconexionProgramada = false;
      void this.conectar().catch((error) => {
        this.logger.error({ error }, 'No fue posible reconstruir la sesión de WhatsApp.');
        this.programarReconexion();
      });
    }, 5_000);
  }
}
