import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readdir, rm, utimes, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { ColaDurable } from '../src/cola/cola-durable.js';

test('la cola durable conserva una tarea hasta recibir confirmación', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-cola-'));

  try {
    const cola = new ColaDurable(ruta);
    const primera = await cola.agregar('/evento', { valor: 1 }, 'misma-clave');
    const repetida = await cola.agregar('/evento', { valor: 1 }, 'misma-clave');

    assert.equal(primera.claveDeduplicacion, repetida.claveDeduplicacion);
    assert.equal((await cola.listar()).length, 1);

    await cola.confirmar(primera);
    assert.equal(await cola.cantidad(), 0);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});

test('limpia temporales huérfanos antiguos sin borrar temporales recientes', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-cola-temporales-'));

  try {
    const pendientes = join(ruta, 'cola', 'pendientes');
    await mkdir(pendientes, { recursive: true });

    const antiguo = join(pendientes, 'antiguo.json.100.tmp');
    const reciente = join(pendientes, 'reciente.json.101.tmp');
    await writeFile(antiguo, '{}', 'utf8');
    await writeFile(reciente, '{}', 'utf8');

    const haceDiezMinutos = new Date(Date.now() - 10 * 60 * 1000);
    await utimes(antiguo, haceDiezMinutos, haceDiezMinutos);

    const cola = new ColaDurable(ruta);
    await cola.preparar();

    const archivos = await readdir(pendientes);
    assert.equal(archivos.includes('antiguo.json.100.tmp'), false);
    assert.equal(archivos.includes('reciente.json.101.tmp'), true);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});
