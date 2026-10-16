import { createHash, randomUUID } from 'node:crypto';
import { mkdir, open, readFile, readdir, rename, rm } from 'node:fs/promises';
import { join } from 'node:path';
import type { MensajeProveedor } from '../tipos.js';

export interface MensajeDescargaMultimediaDurable {
  chatId: string;
  tipoMensaje: string;
  eventoExternoId: string | null;
  mensajeProveedorId: string | null;
  contenidoCrudo: string;
  recibidoEn: string;
}

export interface TareaDescargaMultimediaDurable {
  clave: string;
  creadaEn: string;
  mensaje: MensajeDescargaMultimediaDurable;
}

export class ColaDescargaMultimediaDurable {
  private readonly rutaPendientes: string;

  public constructor(rutaDatos: string) {
    this.rutaPendientes = join(rutaDatos, 'descargas-multimedia', 'pendientes');
  }

  public async preparar(): Promise<void> {
    await mkdir(this.rutaPendientes, { recursive: true });
  }

  public async agregar(mensaje: MensajeProveedor): Promise<TareaDescargaMultimediaDurable> {
    await this.preparar();
    const clave = this.claveMensaje(mensaje);
    const destino = join(this.rutaPendientes, `${clave}.json`);
    const existente = await this.leer(destino);

    if (existente !== null) {
      return existente;
    }

    const tarea: TareaDescargaMultimediaDurable = {
      clave,
      creadaEn: new Date().toISOString(),
      mensaje: {
        chatId: mensaje.chatId,
        tipoMensaje: mensaje.tipoMensaje,
        eventoExternoId: mensaje.eventoExternoId,
        mensajeProveedorId: mensaje.mensajeProveedorId,
        contenidoCrudo: mensaje.contenidoCrudo,
        recibidoEn: mensaje.recibidoEn,
      },
    };
    const temporal = `${destino}.${process.pid}.${randomUUID()}.tmp`;
    const archivo = await open(temporal, 'wx', 0o600);

    try {
      await archivo.writeFile(JSON.stringify(tarea), 'utf8');
      await archivo.sync();
    } finally {
      await archivo.close();
    }

    try {
      await rename(temporal, destino);
    } catch (error) {
      await rm(temporal, { force: true });
      const creadaPorOtroFlujo = await this.leer(destino);

      if (creadaPorOtroFlujo !== null) {
        return creadaPorOtroFlujo;
      }

      throw error;
    }

    return tarea;
  }

  public async listar(): Promise<TareaDescargaMultimediaDurable[]> {
    await this.preparar();
    const nombres = (await readdir(this.rutaPendientes))
      .filter((nombre) => nombre.endsWith('.json'));
    const tareas: TareaDescargaMultimediaDurable[] = [];

    for (const nombre of nombres) {
      const tarea = await this.leer(join(this.rutaPendientes, nombre));

      if (tarea !== null) {
        tareas.push(tarea);
      }
    }

    return tareas.sort((a, b) => a.creadaEn.localeCompare(b.creadaEn));
  }

  public async confirmar(tarea: TareaDescargaMultimediaDurable): Promise<void> {
    await rm(join(this.rutaPendientes, `${tarea.clave}.json`), { force: true });
  }

  public async cantidad(): Promise<number> {
    return (await this.listar()).length;
  }

  private claveMensaje(mensaje: MensajeProveedor): string {
    const identidad = mensaje.mensajeProveedorId
      ?? mensaje.eventoExternoId
      ?? createHash('sha256').update(mensaje.contenidoCrudo, 'utf8').digest('hex');

    return createHash('sha256')
      .update(`${mensaje.chatId}\n${identidad}\n${mensaje.tipoMensaje}`, 'utf8')
      .digest('hex');
  }

  private async leer(ruta: string): Promise<TareaDescargaMultimediaDurable | null> {
    try {
      return JSON.parse(await readFile(ruta, 'utf8')) as TareaDescargaMultimediaDurable;
    } catch (error) {
      if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
        return null;
      }

      throw error;
    }
  }
}
