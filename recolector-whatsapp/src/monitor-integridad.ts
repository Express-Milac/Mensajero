import { mkdir, readFile, writeFile } from 'node:fs/promises';
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
    try {
      return JSON.parse(await readFile(this.rutaEstado, 'utf8')) as EstadoEjecucion;
    } catch (error) {
      if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
        return null;
      }

      throw error;
    }
  }

  private async guardar(estado: EstadoEjecucion): Promise<void> {
    await writeFile(this.rutaEstado, JSON.stringify(estado), {
      encoding: 'utf8',
      mode: 0o600,
    });
  }
}
