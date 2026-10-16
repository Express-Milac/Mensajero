import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import makeWASocket, {
  BufferJSON,
  DisconnectReason,
  downloadMediaMessage,
  fetchLatestWaWebVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import type { Logger } from 'pino';
import { AlmacenContextoEdicionesDurable } from '../ediciones/almacen-contexto-ediciones-durable.js';
import {
  descifrarEdicionCifradaWhatsapp,
  esEdicionCifradaWhatsapp,
  extraerContextoEdicionOriginal,
  extraerMensajeObjetivoEdicionCifrada,
} from '../ediciones/ediciones-cifradas.js';
import {
  analizarSemanticaMensaje,
  desarrollarContenido,
  esContenidoInternoWhatsapp,
  fechaIsoDesdeMarca,
} from '../mensajes.js';
import type {
  AdaptadorWhatsapp,
  ChatDescubierto,
  FuenteMultimediaProveedor,
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
  private readonly rutaAutorizados: string;
  private readonly almacenContextoEdiciones: AlmacenContextoEdicionesDurable;

  public constructor(
    private readonly rutaSesion: string,
    private readonly logger: Logger,
  ) {
    const rutaDatos = dirname(rutaSesion);
    this.rutaAutorizados = join(rutaDatos, 'chats-autorizados.json');
    this.almacenContextoEdiciones = new AlmacenContextoEdicionesDurable(rutaDatos);
  }

  public async iniciar(manejadores: ManejadoresWhatsapp): Promise<void> {
    this.manejadores = manejadores;
    this.deteniendo = false;
    await this.almacenContextoEdiciones.preparar();
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

  public reconstruirMensaje(contenidoCrudo: string): MensajeProveedor | null {
    try {
      const valor = JSON.parse(contenidoCrudo, BufferJSON.reviver) as unknown;
      return this.normalizarMensaje(valor);
    } catch (error) {
      this.logger.error({ error }, 'No fue posible reconstruir un mensaje durable de WhatsApp.');
      return null;
    }
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

    // Las ediciones legadas sí llegan aquí. Las ediciones modernas cifradas se
    // compatibilizan desde messages.upsert porque Baileys 6.7.24 no las descifra.
    socket.ev.on('messages.update', (actualizaciones) => {
      void this.manejarActualizacionesMensajes(actualizaciones);
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
    let edicionesCifradas = 0;

    for (const mensaje of mensajes) {
      try {
        if (this.esCandidatoGrupo(mensaje)) {
          candidatosGrupo += 1;
        }

        if (this.esMensajeConEdicionCifrada(mensaje)) {
          const normalizado = await this.normalizarEdicionCifrada(mensaje);

          if (normalizado !== null) {
            normalizadosGrupo += 1;
            if (normalizado.tipoEvento === 'edicion') {
              edicionesCifradas += 1;
            }
            await this.manejadores.alMensaje(normalizado);
          }
          continue;
        }

        const normalizado = this.normalizarMensaje(mensaje);

        if (normalizado !== null) {
          normalizadosGrupo += 1;
          await this.guardarContextoEdicionSiCorresponde(mensaje, normalizado);
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
        ediciones_cifradas: edicionesCifradas,
      },
      'Lote de mensajes de WhatsApp procesado.',
    );
  }

  private async manejarActualizacionesMensajes(actualizaciones: unknown[]): Promise<void> {
    if (this.manejadores === null) {
      return;
    }

    if (actualizaciones.length > 0) {
      await this.confirmarRecepcionActiva('messages_update');
    }

    let candidatosGrupo = 0;
    let normalizadosGrupo = 0;
    let ediciones = 0;

    for (const actualizacion of actualizaciones) {
      try {
        if (this.esActualizacionCandidataGrupo(actualizacion)) {
          candidatosGrupo += 1;
        }

        const normalizado = this.normalizarActualizacionMensaje(actualizacion);

        if (normalizado !== null) {
          normalizadosGrupo += 1;
          if (normalizado.tipoEvento === 'edicion') {
            ediciones += 1;
          }
          await this.manejadores.alMensaje(normalizado);
        }
      } catch (error) {
        this.logger.error({ error }, 'No fue posible normalizar una actualización de mensaje de WhatsApp.');
      }
    }

    this.logger.info(
      {
        tipo_evento: 'messages.update',
        recibidos: actualizaciones.length,
        candidatos_grupo: candidatosGrupo,
        normalizados_grupo: normalizadosGrupo,
        ediciones,
      },
      'Lote de actualizaciones de mensajes de WhatsApp procesado.',
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

  private esMensajeConEdicionCifrada(valor: unknown): boolean {
    if (typeof valor !== 'object' || valor === null) {
      return false;
    }

    const mensaje = valor as {
      key?: { remoteJid?: string | null };
      message?: unknown;
    };

    return Boolean(
      mensaje.key?.remoteJid?.endsWith('@g.us')
      && esEdicionCifradaWhatsapp(mensaje.message),
    );
  }

  private esActualizacionCandidataGrupo(valor: unknown): boolean {
    if (typeof valor !== 'object' || valor === null) {
      return false;
    }

    const actualizacion = valor as {
      key?: { remoteJid?: string | null };
      update?: { message?: unknown };
    };

    return Boolean(
      actualizacion.key?.remoteJid?.endsWith('@g.us')
      && actualizacion.update?.message,
    );
  }

  private normalizarMensaje(valor: unknown): MensajeProveedor | null {
    if (typeof valor !== 'object' || valor === null) {
      return null;
    }

    const mensaje = valor as {
      key?: {
        remoteJid?: string | null;
        id?: string | null;
        participant?: string | null;
        participantAlt?: string | null;
      };
      message?: unknown;
      messageTimestamp?: unknown;
    };
    const chatId = mensaje.key?.remoteJid;

    if (!chatId || !chatId.endsWith('@g.us') || !mensaje.message) {
      return null;
    }

    if (esContenidoInternoWhatsapp(mensaje.message)) {
      this.logger.info(
        { motivo: 'envoltura_interna_whatsapp' },
        'Evento interno de WhatsApp descartado antes de la normalización de usuario.',
      );
      return null;
    }

    const contenidoCrudo = JSON.stringify(valor, BufferJSON.replacer);

    if (contenidoCrudo === undefined) {
      return null;
    }

    const semantica = analizarSemanticaMensaje(mensaje.message);
    const remitenteProveedorId = mensaje.key?.participant
      ?? mensaje.key?.participantAlt
      ?? null;

    return {
      chatId,
      tipoEvento: semantica.tipoEvento,
      tipoMensaje: semantica.tipoMensaje,
      eventoExternoId: mensaje.key?.id ?? null,
      mensajeProveedorId: mensaje.key?.id ?? null,
      remitenteProveedorId,
      mensajeCitadoProveedorId: semantica.mensajeCitadoProveedorId,
      mensajeObjetivoProveedorId: semantica.mensajeObjetivoProveedorId,
      contenidoCrudo,
      recibidoEn: fechaIsoDesdeMarca(mensaje.messageTimestamp),
      multimedia: this.crearFuenteMultimedia(valor, mensaje.message, semantica.tipoMensaje),
    };
  }

  private normalizarActualizacionMensaje(valor: unknown): MensajeProveedor | null {
    if (typeof valor !== 'object' || valor === null) {
      return null;
    }

    const actualizacion = valor as {
      key?: {
        remoteJid?: string | null;
        id?: string | null;
        participant?: string | null;
        participantAlt?: string | null;
      };
      update?: {
        message?: unknown;
        messageTimestamp?: unknown;
      };
    };
    const chatId = actualizacion.key?.remoteJid;
    const contenido = actualizacion.update?.message;

    if (!chatId || !chatId.endsWith('@g.us') || !contenido) {
      return null;
    }

    const semantica = analizarSemanticaMensaje(contenido);

    // messages.update también transporta estados/recibos. Solo promovemos a evento
    // conversacional las actualizaciones que realmente contienen una edición.
    if (semantica.tipoEvento !== 'edicion') {
      return null;
    }

    const mensajeObjetivoProveedorId = semantica.mensajeObjetivoProveedorId
      ?? actualizacion.key?.id
      ?? null;
    const mensajeCompleto = {
      key: actualizacion.key,
      message: contenido,
      messageTimestamp: actualizacion.update?.messageTimestamp,
    };
    const contenidoCrudo = JSON.stringify(mensajeCompleto, BufferJSON.replacer);

    if (contenidoCrudo === undefined) {
      return null;
    }

    const remitenteProveedorId = actualizacion.key?.participant
      ?? actualizacion.key?.participantAlt
      ?? null;

    return {
      chatId,
      tipoEvento: 'edicion',
      tipoMensaje: semantica.tipoMensaje,
      eventoExternoId: actualizacion.key?.id ?? null,
      mensajeProveedorId: actualizacion.key?.id ?? null,
      remitenteProveedorId,
      mensajeCitadoProveedorId: semantica.mensajeCitadoProveedorId,
      mensajeObjetivoProveedorId,
      contenidoCrudo,
      recibidoEn: fechaIsoDesdeMarca(actualizacion.update?.messageTimestamp),
      multimedia: null,
    };
  }

  private async normalizarEdicionCifrada(valor: unknown): Promise<MensajeProveedor | null> {
    if (typeof valor !== 'object' || valor === null) {
      return null;
    }

    const mensaje = valor as {
      key?: {
        remoteJid?: string | null;
        id?: string | null;
        participant?: string | null;
        participantAlt?: string | null;
      };
      message?: unknown;
      messageTimestamp?: unknown;
    };
    const chatId = mensaje.key?.remoteJid;
    const objetivo = extraerMensajeObjetivoEdicionCifrada(mensaje.message);

    if (!chatId || !chatId.endsWith('@g.us') || objetivo === null) {
      return null;
    }

    if (!await this.chatAutorizado(chatId)) {
      return null;
    }

    const contexto = await this.almacenContextoEdiciones.obtener(chatId, objetivo);

    if (contexto !== null) {
      const usuario = this.socket?.user as { id?: string | null; lid?: string | null } | undefined;
      const identidadesLocales = [usuario?.id, usuario?.lid]
        .filter((identidad): identidad is string => typeof identidad === 'string' && identidad !== '');
      const resultado = descifrarEdicionCifradaWhatsapp(valor, contexto, identidadesLocales);

      if (resultado.actualizacion !== null) {
        const normalizado = this.normalizarActualizacionMensaje(resultado.actualizacion);

        if (normalizado !== null) {
          const contenidoCrudo = JSON.stringify({
            key: resultado.actualizacion.key,
            message: resultado.actualizacion.update.message,
            messageTimestamp: resultado.actualizacion.update.messageTimestamp,
            mensajeroContexto: {
              origen: 'secretEncryptedMessage',
              envolturaCifrada: mensaje.message,
            },
          }, BufferJSON.replacer);

          if (contenidoCrudo !== undefined) {
            normalizado.contenidoCrudo = contenidoCrudo;
          }

          this.logger.info(
            { tipo: normalizado.tipoMensaje },
            'Edición cifrada de WhatsApp descifrada y normalizada.',
          );
          return normalizado;
        }
      }

      this.logger.error(
        { causa: resultado.causaFallo ?? 'normalizacion_fallida' },
        'La edición cifrada no pudo descifrarse; se conservará como evento auditable.',
      );
    } else {
      this.logger.error(
        { causa: 'contexto_original_no_disponible' },
        'La edición cifrada no pudo descifrarse; se conservará como evento auditable.',
      );
    }

    const contenidoCrudo = JSON.stringify(valor, BufferJSON.replacer);

    if (contenidoCrudo === undefined) {
      return null;
    }

    return {
      chatId,
      tipoEvento: 'otro',
      tipoMensaje: 'edicion_cifrada_no_descifrada',
      eventoExternoId: mensaje.key?.id ?? null,
      mensajeProveedorId: mensaje.key?.id ?? null,
      remitenteProveedorId: mensaje.key?.participant
        ?? mensaje.key?.participantAlt
        ?? null,
      mensajeCitadoProveedorId: null,
      mensajeObjetivoProveedorId: objetivo,
      contenidoCrudo,
      recibidoEn: fechaIsoDesdeMarca(mensaje.messageTimestamp),
      multimedia: null,
    };
  }

  private async guardarContextoEdicionSiCorresponde(
    valor: unknown,
    normalizado: MensajeProveedor,
  ): Promise<void> {
    if (
      normalizado.tipoEvento !== 'mensaje'
      || normalizado.mensajeProveedorId === null
      || !await this.chatAutorizado(normalizado.chatId)
    ) {
      return;
    }

    const contexto = extraerContextoEdicionOriginal(valor);

    if (contexto !== null) {
      await this.almacenContextoEdiciones.guardar(contexto);
    }
  }

  private async chatAutorizado(chatId: string): Promise<boolean> {
    try {
      const contenido = await readFile(this.rutaAutorizados, 'utf8');
      const chats = JSON.parse(contenido) as unknown;

      return Array.isArray(chats)
        && chats.some((chat) => chat === chatId);
    } catch (error) {
      this.logger.warn(
        { causa: error instanceof Error ? error.name : 'lectura_fallida' },
        'No fue posible validar localmente el grupo para contexto de edición; se aplica denegación por defecto.',
      );
      return false;
    }
  }

  private crearFuenteMultimedia(
    mensajeCompleto: unknown,
    valorContenido: unknown,
    tipoMensaje: string,
  ): FuenteMultimediaProveedor | null {
    const contenido = desarrollarContenido(valorContenido);

    if (contenido === null) {
      return null;
    }

    const campos: Record<string, string> = {
      foto: 'imageMessage',
      video: 'videoMessage',
      audio: 'audioMessage',
      documento: 'documentMessage',
    };
    const campo = campos[tipoMensaje];

    if (campo === undefined) {
      return null;
    }

    const valorMultimedia = contenido[campo];

    if (typeof valorMultimedia !== 'object' || valorMultimedia === null) {
      return null;
    }

    const datos = valorMultimedia as Record<string, unknown>;
    const tipoMime = typeof datos.mimetype === 'string' ? datos.mimetype : null;
    const nombreOriginal = typeof datos.fileName === 'string' ? datos.fileName : null;

    return {
      tipoMime,
      nombreOriginal,
      abrirFlujo: async () => {
        const socket = this.socket;

        if (socket === null) {
          throw new Error('La conexión de WhatsApp no está disponible para descargar multimedia.');
        }

        const flujo = await downloadMediaMessage(
          mensajeCompleto as Parameters<typeof downloadMediaMessage>[0],
          'stream',
          {},
          {
            logger: this.logger.child({ componente: 'baileys_multimedia' }, { level: 'silent' }),
            reuploadRequest: socket.updateMediaMessage,
          },
        );

        return flujo as AsyncIterable<Uint8Array>;
      },
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
