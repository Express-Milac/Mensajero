/**
 * Control de reintentos en memoria: nunca modifica la cola durable en disco.
 * Los fallos repetidos de descarga (incluidos Boom 500 de Baileys) no deben
 * generar cientos de solicitudes ni saturar el registro de eventos.
 */
export class PlanificadorReintentosMultimedia {
  private readonly fallos = new Map<string, {
    intentos: number;
    siguienteMs: number;
  }>();

  public constructor(
    private readonly primeraEsperaMs = 5 * 60_000,
    private readonly esperaMaximaMs = 6 * 60 * 60_000,
  ) {}

  public listo(clave: string, ahoraMs = Date.now()): boolean {
    return (this.fallos.get(clave)?.siguienteMs ?? 0) <= ahoraMs;
  }

  public registrarFallo(clave: string, ahoraMs = Date.now()): {
    intentos: number;
    siguienteMs: number;
  } {
    const intentos = (this.fallos.get(clave)?.intentos ?? 0) + 1;
    const factor = 2 ** Math.min(16, intentos - 1);
    const espera = Math.min(this.primeraEsperaMs * factor, this.esperaMaximaMs);
    const registro = { intentos, siguienteMs: ahoraMs + espera };
    this.fallos.set(clave, registro);
    return registro;
  }

  public confirmar(clave: string): void {
    this.fallos.delete(clave);
  }
}
