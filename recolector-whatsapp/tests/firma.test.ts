import assert from 'node:assert/strict';
import { createHash, createHmac } from 'node:crypto';
import test from 'node:test';
import { generarFirma } from '../src/seguridad/firma.js';

test('la firma HMAC coincide con el contrato Laravel', () => {
  const cuerpo = '{"hola":"mundo"}';
  const secreto = 'a'.repeat(32);
  const identificador = 'prueba-1234567890';
  const marcaTiempo = '1700000000';
  const hash = createHash('sha256').update(cuerpo, 'utf8').digest('hex');
  const esperada = createHmac('sha256', secreto)
    .update(`v1\n${identificador}\n${marcaTiempo}\n${hash}`, 'utf8')
    .digest('hex');

  assert.equal(
    generarFirma(identificador, marcaTiempo, cuerpo, secreto),
    `sha256=${esperada}`,
  );
});
