import { createHash, randomUUID } from 'node:crypto';
import { mkdir, open, readFile, readdir, rename, rm } from 'node:fs/promises';
import { join } from 'node:path';

export interface TareaDurable {
  id: string;
  ruta: string;
  cuerpo: Record<string, unknown>;
  creadaEn: string;
  claveDeduplicacion: string;
}

export class ColaDurable {
  private readonly rutaPendientes: string;

  public constructor(rutaDatos: string) {
    this.rutaPendientes = join(rutaDatos, 'cola', 'pendientes');
  }

  public async preparar(): Promise<void> {
    await mkdir(this.rutaPendientes, { recursive: true });
  }

  public async agregar(
    ruta: string,
    cuerpo: Record<string, unknown>,
    claveDeduplicacion?: string,
  ): Promise<TareaDurable> {
    await this.preparar();
    const serializado = JSON.stringify(cuerpo);
    const clave = claveDeduplicacion
      ?? createHash('sha256').update(`${ruta}\n${serializado}`, 'utf8').digest('hex');
    const destino = join(this.rutaPendientes, `${clave}.json`);
    const temporal = `${destino}.${process.pid}.${randomUUID()}.tmp`;
    const tarea: TareaDurable = {
      id: randomUUID(),
      ruta,
      cuerpo,
      creadaEn: new Date().toISOString(),
      claveDeduplicacion: clave,
    };

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
      const existente = await this.leer(destino);

      if (existente !== null) {
        return existente;
      }

      throw error;
    }

    return tarea;
  }

  public async listar(): Promise<TareaDurable[]> {
    await this.preparar();
    const nombres = (await readdir(this.rutaPendientes))
      .filter((nombre) => nombre.endsWith('.json'));
    const tareas: TareaDurable[] = [];

    for (const nombre of nombres) {
      const tarea = await this.leer(join(this.rutaPendientes, nombre));

      if (tarea !== null) {
        tareas.push(tarea);
      }
    }

    return tareas.sort((a, b) => a.creadaEn.localeCompare(b.creadaEn));
  }

  public async confirmar(tarea: TareaDurable): Promise<void> {
    await rm(join(this.rutaPendientes, `${tarea.claveDeduplicacion}.json`), { force: true });
  }

  public async cantidad(): Promise<number> {
    return (await this.listar()).length;
  }

  private async leer(ruta: string): Promise<TareaDurable | null> {
    try {
      return JSON.parse(await readFile(ruta, 'utf8')) as TareaDurable;
    } catch (error) {
      if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
        return null;
      }

      throw error;
    }
  }
}
