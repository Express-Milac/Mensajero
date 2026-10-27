import { resolve } from 'node:path';

export interface ConfiguracionRecolector {
  mensajeroUrl: string;
  secreto: string;
  claveConexion: string;
  rutaDatos: string;
  rutaSesion: string;
  rutaAlmacenamiento: string;
  intervaloAutorizacionesMs: number;
  intervaloGruposMs: number;
  intervaloColaMs: number;
  maxReintentoMs: number;
  intervaloLatidoMs: number;
  nivelRegistro: string;
}

function requerido(nombre: string): string {
  const valor = process.env[nombre]?.trim();

  if (!valor) {
    throw new Error(`Falta la variable de entorno ${nombre}.`);
  }

  return valor;
}

function enteroPositivo(nombre: string, valorPredeterminado: number): number {
  const crudo = process.env[nombre]?.trim();

  if (!crudo) {
    return valorPredeterminado;
  }

  const valor = Number(crudo);

  if (!Number.isInteger(valor) || valor < 1000) {
    throw new Error(`${nombre} debe ser un entero de al menos 1000 ms.`);
  }

  return valor;
}

export function cargarConfiguracion(): ConfiguracionRecolector {
  const secreto = requerido('MENSAJERO_PASARELA_SECRETO');

  if (secreto.length < 32) {
    throw new Error('MENSAJERO_PASARELA_SECRETO debe tener al menos 32 caracteres.');
  }

  const rutaDatos = resolve(process.cwd(), process.env.RECOLECTOR_RUTA_DATOS?.trim() || './datos');
  const rutaAlmacenamientoExplicita = process.env.RECOLECTOR_RUTA_ALMACENAMIENTO?.trim();
  const rutaAlmacenamiento = resolve(
    process.cwd(),
    rutaAlmacenamientoExplicita || '../storage/app/mensajero',
  );

  if (rutaAlmacenamientoExplicita) {
    console.warn(
      '[Mensajero] RECOLECTOR_RUTA_ALMACENAMIENTO está definida explícitamente; debe apuntar a la misma raíz privada que Laravel.',
    );
  } else {
    console.info(
      '[Mensajero] Se usa la raíz multimedia predeterminada compartida con Laravel para el POC local.',
    );
  }

  return {
    mensajeroUrl: requerido('MENSAJERO_URL').replace(/\/+$/, ''),
    secreto,
    claveConexion: requerido('MENSAJERO_CLAVE_CONEXION'),
    rutaDatos,
    rutaSesion: resolve(rutaDatos, 'sesion'),
    rutaAlmacenamiento,
    intervaloAutorizacionesMs: enteroPositivo('RECOLECTOR_INTERVALO_AUTORIZACIONES_MS', 15_000),
    intervaloGruposMs: enteroPositivo('RECOLECTOR_INTERVALO_GRUPOS_MS', 300_000),
    intervaloColaMs: enteroPositivo('RECOLECTOR_INTERVALO_COLA_MS', 2_000),
    maxReintentoMs: enteroPositivo('RECOLECTOR_MAX_REINTENTO_MS', 60_000),
    intervaloLatidoMs: enteroPositivo('RECOLECTOR_INTERVALO_LATIDO_MS', 30_000),
    nivelRegistro: process.env.NIVEL_REGISTRO?.trim() || 'info',
  };
}
