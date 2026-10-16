import { randomUUID } from 'node:crypto';
import { generarFirma } from './seguridad/firma.js';

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

  public async obtenerChatsAutorizados(): Promise<Set<string>> {
    const respuesta = await this.enviar<RespuestaChatsAutorizados>(
      '/api/pasarela/v1/chats-autorizados',
      { clave_conexion: this.claveConexion },
    );

    return new Set(respuesta.chats.map((chat) => chat.id_chat_proveedor));
  }

  public async enviar<T = Record<string, unknown>>(
    ruta: string,
    contenido: Record<string, unknown>,
  ): Promise<T> {
    const cuerpo = JSON.stringify(contenido);
    const identificador = `recolector-${randomUUID()}`;
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
        signal: AbortSignal.timeout(15_000),
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
