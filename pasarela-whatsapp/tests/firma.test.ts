import assert from 'node:assert/strict';
import { createHash, createHmac } from 'node:crypto';
import test from 'node:test';
import { generarFirma } from '../src/seguridad/firma.js';

test('la firma HMAC coincide con el contrato v1 de Laravel', () => {
  const identificador = 'pasarela-1234567890abcdef';
  const marcaTiempo = '1760568000';
  const cuerpo = '{"clave_conexion":"prueba-local"}';
  const secreto = 'x'.repeat(32);
  const hash = createHash('sha256').update(cuerpo, 'utf8').digest('hex');
  const canonico = ['v1', identificador, marcaTiempo, hash].join('\n');
  const esperada = `sha256=${createHmac('sha256', secreto).update(canonico, 'utf8').digest('hex')}`;

  assert.equal(generarFirma(identificador, marcaTiempo, cuerpo, secreto), esperada);
});
