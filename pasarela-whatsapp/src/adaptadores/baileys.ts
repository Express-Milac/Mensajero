import makeWASocket, {
  BufferJSON,
  DisconnectReason,
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
    const socket = makeWASocket({
      auth: state,
      logger: this.logger,
      markOnlineOnConnect: false,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
    });

    this.socket = socket;
    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', (actualizacion) => {
      void this.manejarActualizacionConexion(actualizacion);
    });

    socket.ev.on('messages.upsert', (evento) => {
      void this.manejarMensajes(evento.messages, evento.type);
    });
  }

  private async manejarActualizacionConexion(actualizacion: {
    connection?: string | null;
    lastDisconnect?: { error?: unknown } | null;
    qr?: string | null;
  }): Promise<void> {
    if (this.manejadores === null) {
      return;
    }

    try {
      if (actualizacion.qr) {
        this.manejadores.alQr(actualizacion.qr);
        await this.manejadores.alEstado('requiere_vinculacion');
      }

      if (actualizacion.connection === 'open') {
        this.reconexionProgramada = false;
        await this.manejadores.alEstado('conectado', {
          id_cuenta_externa: this.socket?.user?.id ?? null,
        });
        await this.sincronizarGrupos();
      }

      if (actualizacion.connection === 'close') {
        const codigo = this.codigoDesconexion(actualizacion.lastDisconnect?.error);
        const sesionCerrada = codigo === DisconnectReason.loggedOut;

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
    if (this.manejadores === null || tipoEvento !== 'notify') {
      return;
    }

    for (const mensaje of mensajes) {
      try {
        const normalizado = this.normalizarMensaje(mensaje);

        if (normalizado !== null) {
          await this.manejadores.alMensaje(normalizado);
        }
      } catch (error) {
        this.logger.error({ error }, 'No fue posible normalizar un mensaje de WhatsApp.');
      }
    }
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

    if (!chatId || !mensaje.message) {
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
