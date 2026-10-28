import assert from 'node:assert/strict';
import test from 'node:test';
import { validarUrlMensajero } from '../src/configuracion.js';

test('permite HTTP solo en localhost de pruebas', () => {
  assert.equal(validarUrlMensajero('http://127.0.0.1:8000/'), 'http://127.0.0.1:8000');
  assert.equal(validarUrlMensajero('http://localhost:8000'), 'http://localhost:8000');
});

test('exige HTTPS en servidor que no sea local', () => {
  assert.throws(() => validarUrlMensajero('http://mensajero.empresa.test'), /HTTPS/);
  assert.equal(validarUrlMensajero('https://mensajero.empresa.test/Mensajero/'),
    'https://mensajero.empresa.test/Mensajero');
});

test('rechaza URLs con credenciales y parametros inseguros', () => {
  assert.throws(() => validarUrlMensajero('https://usuario:clave@mensajero.empresa.test'), /usuario/);
  assert.throws(() => validarUrlMensajero('https://mensajero.empresa.test/?token=123'), /parametros/);
  assert.throws(() => validarUrlMensajero('ftp://mensajero.empresa.test'), /HTTPS/);
  assert.throws(() => validarUrlMensajero('no-es-url'), /URL valida/);
});
