import { createHash, createHmac } from 'node:crypto';

export const VERSION_FIRMA = 'v1';

export function hashCuerpo(cuerpo: string): string {
  return createHash('sha256').update(cuerpo, 'utf8').digest('hex');
}

export function generarFirma(
  identificador: string,
  marcaTiempo: string,
  cuerpo: string,
  secreto: string,
): string {
  const canonico = [VERSION_FIRMA, identificador, marcaTiempo, hashCuerpo(cuerpo)].join('\n');
  const firma = createHmac('sha256', secreto).update(canonico, 'utf8').digest('hex');

  return `sha256=${firma}`;
}
