import { createHash, randomUUID } from 'node:crypto';
import { createReadStream } from 'node:fs';
import { mkdir, open, rename, rm, stat, type FileHandle } from 'node:fs/promises';
import { dirname, extname, join } from 'node:path';
import type { MensajeProveedor } from '../tipos.js';

export interface MultimediaPersistida {
  identificadorArchivo: string;
  rutaRelativa: string;
  hashSha256: string;
  tamanoBytes: number;
  tipoMime: string | null;
  nombreOriginal: string | null;
  extension: string;
}

export class AlmacenMultimediaDurable {
  public constructor(
    private readonly rutaRaiz: string,
    private readonly claveConexion: string,
  ) {}

  public async guardar(mensaje: MensajeProveedor): Promise<MultimediaPersistida | null> {
    if (mensaje.multimedia === null) {
      return null;
    }

    const identificador = this.identificador(mensaje);
    const extension = this.extensionSegura(
      mensaje.multimedia.nombreOriginal,
      mensaje.multimedia.tipoMime,
    );
    const fecha = this.fechaSegura(mensaje.recibidoEn);
    const claveRuta = this.segmentoSeguro(this.claveConexion);
    const rutaRelativa = [
      'multimedia',
      'whatsapp',
      claveRuta,
      String(fecha.getUTCFullYear()),
      String(fecha.getUTCMonth() + 1).padStart(2, '0'),
      `${identificador}.${extension}`,
    ].join('/');
    const rutaAbsoluta = join(this.rutaRaiz, ...rutaRelativa.split('/'));
    const existente = await this.resumirSiExiste(rutaAbsoluta);
    const nombreOriginal = mensaje.multimedia.nombreOriginal?.slice(0, 255) ?? null;

    if (existente !== null) {
      return {
        identificadorArchivo: identificador,
        rutaRelativa,
        hashSha256: existente.hashSha256,
        tamanoBytes: existente.tamanoBytes,
        tipoMime: mensaje.multimedia.tipoMime,
        nombreOriginal,
        extension,
      };
    }

    await mkdir(dirname(rutaAbsoluta), { recursive: true });
    const temporal = `${rutaAbsoluta}.${process.pid}.${randomUUID()}.tmp`;
    const archivo = await open(temporal, 'wx', 0o600);
    const hash = createHash('sha256');
    let tamanoBytes = 0;

    try {
      const flujo = await mensaje.multimedia.abrirFlujo();

      for await (const fragmento of flujo) {
        const datos = Buffer.from(fragmento);

        if (datos.length === 0) {
          continue;
        }

        hash.update(datos);
        tamanoBytes += datos.length;
        await this.escribirCompleto(archivo, datos);
      }

      await archivo.sync();
    } catch (error) {
      await archivo.close().catch(() => undefined);
      await rm(temporal, { force: true });
      throw error;
    }

    await archivo.close();

    try {
      await rename(temporal, rutaAbsoluta);
    } catch (error) {
      await rm(temporal, { force: true });
      throw error;
    }

    return {
      identificadorArchivo: identificador,
      rutaRelativa,
      hashSha256: hash.digest('hex'),
      tamanoBytes,
      tipoMime: mensaje.multimedia.tipoMime,
      nombreOriginal,
      extension,
    };
  }

  private identificador(mensaje: MensajeProveedor): string {
    const identidad = mensaje.mensajeProveedorId
      ?? mensaje.eventoExternoId
      ?? createHash('sha256').update(mensaje.contenidoCrudo, 'utf8').digest('hex');

    return createHash('sha256')
      .update(`${this.claveConexion}\n${mensaje.chatId}\n${identidad}\n${mensaje.tipoMensaje}`, 'utf8')
      .digest('hex');
  }

  private extensionSegura(nombreOriginal: string | null, tipoMime: string | null): string {
    const extensionNombre = nombreOriginal
      ? extname(nombreOriginal).replace(/^\./, '').toLowerCase()
      : '';

    if (/^[a-z0-9]{1,16}$/.test(extensionNombre)) {
      return extensionNombre;
    }

    const porMime: Record<string, string> = {
      'image/jpeg': 'jpg',
      'image/png': 'png',
      'image/webp': 'webp',
      'image/gif': 'gif',
      'video/mp4': 'mp4',
      'video/3gpp': '3gp',
      'audio/ogg': 'ogg',
      'audio/mpeg': 'mp3',
      'audio/mp4': 'm4a',
      'application/pdf': 'pdf',
      'application/zip': 'zip',
      'text/plain': 'txt',
    };

    const mimeBase = tipoMime?.split(';', 1)[0]?.trim().toLowerCase() ?? '';
    return porMime[mimeBase] ?? 'bin';
  }

  private segmentoSeguro(valor: string): string {
    const seguro = valor.replace(/[^A-Za-z0-9_-]/g, '_').slice(0, 64);
    return seguro || 'conexion';
  }

  private fechaSegura(valor: string): Date {
    const fecha = new Date(valor);
    return Number.isNaN(fecha.getTime()) ? new Date() : fecha;
  }

  private async escribirCompleto(archivo: FileHandle, datos: Buffer): Promise<void> {
    let desplazamiento = 0;

    while (desplazamiento < datos.length) {
      const resultado = await archivo.write(
        datos,
        desplazamiento,
        datos.length - desplazamiento,
        null,
      );

      if (resultado.bytesWritten <= 0) {
        throw new Error('No fue posible completar la escritura durable de multimedia.');
      }

      desplazamiento += resultado.bytesWritten;
    }
  }

  private async resumirSiExiste(ruta: string): Promise<{
    hashSha256: string;
    tamanoBytes: number;
  } | null> {
    try {
      const informacion = await stat(ruta);

      if (!informacion.isFile()) {
        throw new Error('La ruta multimedia existente no corresponde a un archivo.');
      }

      const hash = createHash('sha256');

      for await (const fragmento of createReadStream(ruta)) {
        hash.update(fragmento as Buffer);
      }

      return {
        hashSha256: hash.digest('hex'),
        tamanoBytes: informacion.size,
      };
    } catch (error) {
      if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
        return null;
      }

      throw error;
    }
  }
}
