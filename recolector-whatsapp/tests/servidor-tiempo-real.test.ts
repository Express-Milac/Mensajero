import test from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import type { AddressInfo } from 'node:net';
import { credencialValida, iniciarServidorTiempoReal } from '../src/tiempo-real/servidor.js';

const secreto = 'secreto-de-prueba-con-mas-de-32-caracteres';
const origen = 'http://127.0.0.1:8000';

test('rechaza credenciales alteradas, vencidas y las acepta válidas', () => {
  const ahora = Math.floor(Date.now() / 1000);
  const caduca = String(ahora + 60);
  const aleatorio = '0123456789abcdef0123456789abcdef';
  const firma = createHmac('sha256', secreto).update(caduca + '.' + aleatorio).digest('hex');

  assert.equal(credencialValida(caduca, aleatorio, firma, secreto, ahora), true);
  assert.equal(credencialValida(caduca, aleatorio, 'f'.repeat(64), secreto, ahora), false);
  assert.equal(credencialValida(caduca, aleatorio, firma, secreto, ahora + 61), false);
  assert.equal(credencialValida(caduca, aleatorio, firma, secreto, ahora - 60), false);
});

test('SSE acepta aviso firmado y no entrega contenido de WhatsApp', async (t) => {
  const logger = { info() {}, warn() {}, error() {} };
  const servidor = await iniciarServidorTiempoReal(secreto, origen, 0, logger);
  t.after(() => {
    servidor.closeAllConnections();
    servidor.close();
  });

  const puerto = (servidor.address() as AddressInfo).port;
  const base = 'http://127.0.0.1:' + puerto;
  const ahora = Math.floor(Date.now() / 1000);
  const caduca = String(ahora + 60);
  const aleatorio = '0123456789abcdef0123456789abcdef';
  const firma = createHmac('sha256', secreto).update(caduca + '.' + aleatorio).digest('hex');

  const noAutorizado = await fetch(base + '/eventos?caduca=' + caduca, {
    headers: { Origin: origen },
  });
  assert.equal(noAutorizado.status, 403);

  const controlador = new AbortController();
  t.after(() => controlador.abort());
  const respuesta = await fetch(
    base + '/eventos?caduca=' + caduca + '&aleatorio=' + aleatorio + '&firma=' + firma,
    { headers: { Origin: origen }, signal: controlador.signal },
  );
  assert.equal(respuesta.status, 200);
  assert.equal(respuesta.headers.get('content-type')?.startsWith('text/event-stream'), true);
  const lector = respuesta.body?.getReader();
  assert.ok(lector);
  await lector.read(); // Saludo inicial.

  const aviso = JSON.stringify({ grupo_id: 10020, evento_id: 1234, marca_tiempo: ahora });
  const firmaAviso = createHmac('sha256', secreto).update(aviso).digest('hex');
  const respuestaAviso = await fetch(base + '/notificar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Mensajero-Firma-TR': firmaAviso },
    body: aviso,
  });
  assert.equal(respuestaAviso.status, 202);

  const siguiente = await lector.read();
  const datos = new TextDecoder().decode(siguiente.value);
  assert.match(datos, /event: cambio/);
  assert.match(datos, /"grupo_id":10020/);
  assert.match(datos, /"evento_id":1234/);
  assert.doesNotMatch(datos, /contenido_crudo|telefono_remitente|mensaje_original/);

  const incorrecto = await fetch(base + '/notificar', {
    method: 'POST',
    headers: { 'X-Mensajero-Firma-TR': 'a'.repeat(64) },
    body: aviso,
  });
  assert.equal(incorrecto.status, 403);
});
