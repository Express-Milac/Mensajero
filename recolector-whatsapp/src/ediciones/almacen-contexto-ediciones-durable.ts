import { createHash, randomUUID } from 'node:crypto';
import {
  mkdir,
  open,
  readFile,
  readdir,
  rename,
  rm,
  stat,
} from 'node:fs/promises';
import { join } from 'node:path';

const RETENCION_CONTEXTO_MS = 7 * 24 * 60 * 60 * 1000;

export interface ContextoEdicionParaGuardar {
  chatId: string;
  mensajeProveedorId: string;
  desdeMiCuenta: boolean;
  remitentesProveedor: string[];
  secretoMensaje: Uint8Array;
}

export interface ContextoEdicionDisponible extends ContextoEdicionParaGuardar {
  guardadoEn: string;
}

interface ContextoEdicionSerializado {
  version: 1;
  chatId: string;
  mensajeProveedorId: string;
  desdeMiCuenta: boolean;
  remitentesProveedor: string[];
  secretoMensajeBase64: string;
  guardadoEn: string;
}

function esErrorCodigo(error: unknown, codigo: string): boolean {
  return error instanceof Error
    && 'code' in error
    && (error as NodeJS.ErrnoException).code === codigo;
}

export class AlmacenContextoEdicionesDurable {
  private readonly rutaDirectorio: string;

  public constructor(rutaDatos: string) {
    this.rutaDirectorio = join(rutaDatos, 'contexto-ediciones');
  }

  public async preparar(): Promise<void> {
    await mkdir(this.rutaDirectorio, { recursive: true, mode: 0o700 });
    await this.limpiarAntiguos();
  }

  public async guardar(contexto: ContextoEdicionParaGuardar): Promise<void> {
    if (contexto.secretoMensaje.byteLength !== 32) {
      throw new Error('El secreto de mensaje para edición debe contener exactamente 32 bytes.');
    }

    await mkdir(this.rutaDirectorio, { recursive: true, mode: 0o700 });

    const rutaFinal = this.rutaContexto(contexto.chatId, contexto.mensajeProveedorId);

    try {
      await readFile(rutaFinal, 'utf8');
      return;
    } catch (error) {
      if (!esErrorCodigo(error, 'ENOENT')) {
        throw error;
      }
    }

    const serializado: ContextoEdicionSerializado = {
      version: 1,
      chatId: contexto.chatId,
      mensajeProveedorId: contexto.mensajeProveedorId,
      desdeMiCuenta: contexto.desdeMiCuenta,
      remitentesProveedor: [...new Set(contexto.remitentesProveedor.filter(Boolean))],
      secretoMensajeBase64: Buffer.from(contexto.secretoMensaje).toString('base64'),
      guardadoEn: new Date().toISOString(),
    };
    const rutaTemporal = `${rutaFinal}.${process.pid}.${randomUUID()}.tmp`;
    const archivo = await open(rutaTemporal, 'wx', 0o600);

    try {
      await archivo.writeFile(JSON.stringify(serializado), 'utf8');
      await archivo.sync();
    } finally {
      await archivo.close();
    }

    try {
      await rename(rutaTemporal, rutaFinal);
    } catch (error) {
      await rm(rutaTemporal, { force: true });

      try {
        await readFile(rutaFinal, 'utf8');
        return;
      } catch {
        throw error;
      }
    }
  }

  public async obtener(
    chatId: string,
    mensajeProveedorId: string,
  ): Promise<ContextoEdicionDisponible | null> {
    const ruta = this.rutaContexto(chatId, mensajeProveedorId);
    let contenido: string;

    try {
      contenido = await readFile(ruta, 'utf8');
    } catch (error) {
      if (esErrorCodigo(error, 'ENOENT')) {
        return null;
      }

      throw error;
    }

    const valor = JSON.parse(contenido) as Partial<ContextoEdicionSerializado>;

    if (
      valor.version !== 1
      || valor.chatId !== chatId
      || valor.mensajeProveedorId !== mensajeProveedorId
      || typeof valor.desdeMiCuenta !== 'boolean'
      || !Array.isArray(valor.remitentesProveedor)
      || typeof valor.secretoMensajeBase64 !== 'string'
      || typeof valor.guardadoEn !== 'string'
    ) {
      throw new Error('El contexto durable de edición no cumple el formato esperado.');
    }

    const secretoMensaje = Buffer.from(valor.secretoMensajeBase64, 'base64');

    if (secretoMensaje.byteLength !== 32) {
      throw new Error('El contexto durable de edición contiene un secreto inválido.');
    }

    return {
      chatId,
      mensajeProveedorId,
      desdeMiCuenta: valor.desdeMiCuenta,
      remitentesProveedor: valor.remitentesProveedor.filter(
        (remitente): remitente is string => typeof remitente === 'string' && remitente !== '',
      ),
      secretoMensaje: new Uint8Array(secretoMensaje),
      guardadoEn: valor.guardadoEn,
    };
  }

  private rutaContexto(chatId: string, mensajeProveedorId: string): string {
    const clave = createHash('sha256')
      .update(`${chatId}\n${mensajeProveedorId}`, 'utf8')
      .digest('hex');

    return join(this.rutaDirectorio, `${clave}.json`);
  }

  private async limpiarAntiguos(): Promise<void> {
    let archivos;

    try {
      archivos = await readdir(this.rutaDirectorio, { withFileTypes: true });
    } catch (error) {
      if (esErrorCodigo(error, 'ENOENT')) {
        return;
      }

      throw error;
    }

    const limite = Date.now() - RETENCION_CONTEXTO_MS;

    for (const archivo of archivos) {
      if (!archivo.isFile() || !archivo.name.endsWith('.json')) {
        continue;
      }

      const ruta = join(this.rutaDirectorio, archivo.name);

      try {
        const informacion = await stat(ruta);

        if (informacion.mtimeMs < limite) {
          await rm(ruta, { force: true });
        }
      } catch (error) {
        if (!esErrorCodigo(error, 'ENOENT')) {
          throw error;
        }
      }
    }
  }
}
