import { randomUUID } from 'node:crypto';
import { mkdir, open, readFile, rename, rm, stat } from 'node:fs/promises';
import { join } from 'node:path';

interface EstadoEjecucion {
  apagadoLimpio: boolean;
  ultimoLatidoEn: string;
}

export interface HuecoIntegridad {
  inicioEn: string;
  finEn: string;
  causa: string;
}

export class MonitorIntegridad {
  private readonly rutaEstado: string;

  public constructor(private readonly rutaDatos: string) {
    this.rutaEstado = join(rutaDatos, 'estado-ejecucion.json');
  }

  public async iniciar(): Promise<HuecoIntegridad | null> {
    await mkdir(this.rutaDatos, { recursive: true });
    const anterior = await this.leer();
    const ahora = new Date().toISOString();
    await this.guardar({ apagadoLimpio: false, ultimoLatidoEn: ahora });

    if (anterior !== null && !anterior.apagadoLimpio) {
      return {
        inicioEn: anterior.ultimoLatidoEn,
        finEn: ahora,
        causa: 'reinicio_no_confirmado',
      };
    }

    return null;
  }

  public async latido(): Promise<void> {
    await this.guardar({
      apagadoLimpio: false,
      ultimoLatidoEn: new Date().toISOString(),
    });
  }

  public async cerrar(): Promise<void> {
    await this.guardar({
      apagadoLimpio: true,
      ultimoLatidoEn: new Date().toISOString(),
    });
  }

  private async leer(): Promise<EstadoEjecucion | null> {
    let contenido: string;
    try {
      contenido = await readFile(this.rutaEstado, 'utf8');
    } catch (error) {
      if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
        return null;
      }
      throw error;
    }

    try {
      const valor: unknown = JSON.parse(contenido);
      if (typeof valor === 'object' && valor !== null) {
        const estado = valor as Record<string, unknown>;
        if (
          typeof estado.apagadoLimpio === 'boolean'
          && typeof estado.ultimoLatidoEn === 'string'
          && Number.isFinite(Date.parse(estado.ultimoLatidoEn))
        ) {
          return estado as unknown as EstadoEjecucion;
        }
      }
    } catch (error) {
      if (!(error instanceof SyntaxError)) {
        throw error;
      }
    }

    // Un archivo truncado no puede impedir el arranque. Se conserva íntegro
    // y se registra el hueco desde la marca de modificación aproximada.
    const informacion = await stat(this.rutaEstado);
    const respaldo = `${this.rutaEstado}.corrupto-${Date.now()}-${randomUUID()}`;
    await rename(this.rutaEstado, respaldo);
    console.warn('[Recolector] Estado de integridad inválido conservado en respaldo; se registrará periodo sin integridad confirmada.');
    return {
      apagadoLimpio: false,
      ultimoLatidoEn: informacion.mtime.toISOString(),
    };
  }

  private async guardar(estado: EstadoEjecucion): Promise<void> {
    const temporal = `${this.rutaEstado}.${process.pid}.${randomUUID()}.tmp`;
    try {
      const archivo = await open(temporal, 'wx', 0o600);
      try {
        await archivo.writeFile(JSON.stringify(estado), 'utf8');
        await archivo.sync();
      } finally {
        await archivo.close();
      }
      await rename(temporal, this.rutaEstado);
    } catch (error) {
      await rm(temporal, { force: true });
      throw error;
    }
  }

}
