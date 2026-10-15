import { resolve } from 'node:path';

export interface ConfiguracionPasarela {
  mensajeroUrl: string;
  secreto: string;
  claveConexion: string;
  rutaSesion: string;
  intervaloAutorizacionesMs: number;
  intervaloGruposMs: number;
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

export function cargarConfiguracion(): ConfiguracionPasarela {
  const secreto = requerido('MENSAJERO_PASARELA_SECRETO');

  if (secreto.length < 32) {
    throw new Error('MENSAJERO_PASARELA_SECRETO debe tener al menos 32 caracteres.');
  }

  return {
    mensajeroUrl: requerido('MENSAJERO_URL').replace(/\/+$/, ''),
    secreto,
    claveConexion: requerido('MENSAJERO_CLAVE_CONEXION'),
    rutaSesion: resolve(process.cwd(), process.env.WHATSAPP_RUTA_SESION?.trim() || './datos/sesion'),
    intervaloAutorizacionesMs: enteroPositivo('WHATSAPP_INTERVALO_AUTORIZACIONES_MS', 30_000),
    intervaloGruposMs: enteroPositivo('WHATSAPP_INTERVALO_GRUPOS_MS', 300_000),
    nivelRegistro: process.env.NIVEL_REGISTRO?.trim() || 'info',
  };
}
