import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

export function corregirRecepcionBaileys(contenido) {
  const reglas = [
    {
      nombre: 'clasificacion_nodo_offline',
      original: 'const isOffline = !!node.attrs.offline',
      corregido: "const isOffline = node.attrs.offline === '1'",
    },
    {
      nombre: 'clasificacion_upsert_offline',
      original: "upsertMessage(msg, node.attrs.offline ? 'append' : 'notify')",
      corregido: "upsertMessage(msg, node.attrs.offline === '1' ? 'append' : 'notify')",
    },
  ];

  let resultado = contenido;
  const aplicadas = [];
  const yaAplicadas = [];

  for (const regla of reglas) {
    if (resultado.includes(regla.corregido)) {
      yaAplicadas.push(regla.nombre);
      continue;
    }

    const ocurrencias = resultado.split(regla.original).length - 1;

    if (ocurrencias !== 1) {
      throw new Error(
        `Parche Baileys incompatible: ${regla.nombre} esperaba 1 coincidencia y encontró ${ocurrencias}.`,
      );
    }

    resultado = resultado.replace(regla.original, regla.corregido);
    aplicadas.push(regla.nombre);
  }

  return { contenido: resultado, aplicadas, yaAplicadas };
}

async function principal() {
  const ruta = resolve(
    process.cwd(),
    'node_modules',
    '@whiskeysockets',
    'baileys',
    'lib',
    'Socket',
    'messages-recv.js',
  );

  const original = await readFile(ruta, 'utf8');
  const resultado = corregirRecepcionBaileys(original);

  if (resultado.aplicadas.length > 0) {
    await writeFile(ruta, resultado.contenido, 'utf8');
    console.log(
      `[Mensajero] Parche Baileys aplicado: ${resultado.aplicadas.join(', ')}.`,
    );
  } else {
    console.log('[Mensajero] Parche Baileys ya estaba aplicado.');
  }
}

if (process.argv[1] && resolve(process.argv[1]) === resolve(new URL(import.meta.url).pathname.replace(/^\/(.:)/, '$1'))) {
  principal().catch((error) => {
    console.error(`[Mensajero] No fue posible aplicar el parche Baileys: ${error.message}`);
    process.exitCode = 1;
  });
}
