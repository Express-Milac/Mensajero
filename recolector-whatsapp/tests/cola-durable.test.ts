import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
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
