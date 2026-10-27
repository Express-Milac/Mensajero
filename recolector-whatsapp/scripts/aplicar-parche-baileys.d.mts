export interface ResultadoParcheBaileys {
  contenido: string;
  aplicadas: string[];
  yaAplicadas: string[];
}

export function corregirRecepcionBaileys(contenido: string): ResultadoParcheBaileys;
