import { randomUUID } from 'node:crypto';
import { generarFirma } from './seguridad/firma.js';
import type { ChatDescubierto, EstadoConexion, MensajeProveedor } from './tipos.js';

interface ChatAutorizadoRespuesta {
  id_chat_proveedor: string;
  tipo_chat: string;
  nombre_grupo: string | null;
  version_autorizacion: number;
}

interface RespuestaChatsAutorizados {
  estado: string;
  chats: ChatAutorizadoRespuesta[];
}

export class ErrorMensajero extends Error {
  public constructor(
    mensaje: string,
    public readonly estadoHttp: number | null,
    public readonly respuesta: string | null,
  ) {
    super(mensaje);
  }
}

export class ClienteMensajero {
  public constructor(
    private readonly urlBase: string,
    private readonly secreto: string,
    private readonly claveConexion: string,
  ) {}

  public async actualizarEstado(
    estado: EstadoConexion,
    detalles: Record<string, unknown> = {},
  ): Promise<void> {
    await this.enviar('/api/pasarela/v1/estado-conexion', {
      clave_conexion: this.claveConexion,
      estado,
      ...detalles,
    });
  }

  public async sincronizarChats(chats: ChatDescubierto[]): Promise<void> {
    await this.enviar('/api/pasarela/v1/chats-descubiertos', {
      clave_conexion: this.claveConexion,
      chats: chats.map((chat) => ({
        id_chat_proveedor: chat.idChatProveedor,
        tipo_chat: chat.tipoChat,
        nombre_grupo: chat.nombreGrupo,
        es_activo: chat.esActivo,
      })),
    });
  }

  public async obtenerChatsAutorizados(): Promise<Set<string>> {
    const respuesta = await this.enviar<RespuestaChatsAutorizados>('/api/pasarela/v1/chats-autorizados', {
      clave_conexion: this.claveConexion,
    });

    return new Set(respuesta.chats.map((chat) => chat.id_chat_proveedor));
  }

  public async capturarEvento(mensaje: MensajeProveedor): Promise<void> {
    await this.enviar('/api/pasarela/v1/eventos', {
      clave_conexion: this.claveConexion,
      chat_id: mensaje.chatId,
      tipo_evento: 'mensaje',
      tipo_mensaje: mensaje.tipoMensaje,
      evento_externo_id: mensaje.eventoExternoId,
      mensaje_proveedor_id: mensaje.mensajeProveedorId,
      contenido_crudo: mensaje.contenidoCrudo,
      recibido_en: mensaje.recibidoEn,
      version_contrato: 'v1',
    });
  }

  private async enviar<T = Record<string, unknown>>(ruta: string, contenido: Record<string, unknown>): Promise<T> {
    const cuerpo = JSON.stringify(contenido);
    let ultimoError: unknown;

    for (let intento = 1; intento <= 3; intento += 1) {
      try {
        return await this.enviarUnaVez<T>(ruta, cuerpo);
      } catch (error) {
        ultimoError = error;

        if (error instanceof ErrorMensajero && error.estadoHttp !== null && error.estadoHttp < 500) {
          throw error;
        }

        if (intento < 3) {
          await new Promise((resolver) => setTimeout(resolver, 400 * intento));
        }
      }
    }

    throw ultimoError instanceof Error
      ? ultimoError
      : new Error('No fue posible comunicarse con Mensajero.');
  }

  private async enviarUnaVez<T>(ruta: string, cuerpo: string): Promise<T> {
    const identificador = `pasarela-${randomUUID()}`;
    const marcaTiempo = Math.floor(Date.now() / 1000).toString();
    const firma = generarFirma(identificador, marcaTiempo, cuerpo, this.secreto);

    let respuesta: Response;

    try {
      respuesta = await fetch(`${this.urlBase}${ruta}`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Mensajero-Id': identificador,
          'X-Mensajero-Marca-Tiempo': marcaTiempo,
          'X-Mensajero-Firma': firma,
        },
        body: cuerpo,
        signal: AbortSignal.timeout(10_000),
      });
    } catch (error) {
      throw new ErrorMensajero(
        error instanceof Error ? error.message : 'Error de red hacia Mensajero.',
        null,
        null,
      );
    }

    const texto = await respuesta.text();

    if (!respuesta.ok) {
      throw new ErrorMensajero(
        `Mensajero respondió HTTP ${respuesta.status}.`,
        respuesta.status,
        texto || null,
      );
    }

    if (texto === '') {
      return {} as T;
    }

    return JSON.parse(texto) as T;
  }
}
