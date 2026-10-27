import assert from 'node:assert/strict';
import test from 'node:test';
import { corregirRecepcionBaileys } from '../scripts/aplicar-parche-baileys.mjs';

test('corrige offline=0 sin alterar offline=1', () => {
  const original = [
    'const isOffline = !!node.attrs.offline',
    "await upsertMessage(msg, node.attrs.offline ? 'append' : 'notify')",
  ].join('\n');

  const resultado = corregirRecepcionBaileys(original);

  assert.equal(resultado.aplicadas.length, 2);
  assert.match(resultado.contenido, /const isOffline = node\.attrs\.offline === '1'/);
  assert.match(
    resultado.contenido,
    /upsertMessage\(msg, node\.attrs\.offline === '1' \? 'append' : 'notify'\)/,
  );
});

test('el parche es idempotente', () => {
  const corregido = [
    "const isOffline = node.attrs.offline === '1'",
    "await upsertMessage(msg, node.attrs.offline === '1' ? 'append' : 'notify')",
  ].join('\n');

  const resultado = corregirRecepcionBaileys(corregido);

  assert.equal(resultado.aplicadas.length, 0);
  assert.equal(resultado.yaAplicadas.length, 2);
  assert.equal(resultado.contenido, corregido);
});

test('falla cerrado si cambia la dependencia', () => {
  assert.throws(
    () => corregirRecepcionBaileys('contenido inesperado'),
    /Parche Baileys incompatible/,
  );
});
