import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { ColaDescargaMultimediaDurable } from '../src/multimedia/cola-descarga-multimedia-durable.js';
import type { MensajeProveedor } from '../src/tipos.js';

function mensajePrueba(): MensajeProveedor {
  return {
    chatId: '120363000000000000@g.us',
    tipoEvento: 'mensaje',
    tipoMensaje: 'foto',
    eventoExternoId: 'EVENTO-MULTIMEDIA-001',
    mensajeProveedorId: 'MENSAJE-MULTIMEDIA-001',
    remitenteProveedorId: '521000000000@s.whatsapp.net',
    mensajeCitadoProveedorId: null,
    mensajeObjetivoProveedorId: null,
    contenidoCrudo: JSON.stringify({
      key: {
        remoteJid: '120363000000000000@g.us',
        id: 'MENSAJE-MULTIMEDIA-001',
        participant: '521000000000@s.whatsapp.net',
      },
      message: {
        imageMessage: {
          mimetype: 'image/jpeg',
        },
      },
      messageTimestamp: 1_792_176_000,
    }),
    recibidoEn: '2026-10-16T18:00:00.000Z',
    multimedia: {
      tipoMime: 'image/jpeg',
      nombreOriginal: null,
      abrirFlujo: async () => (async function* flujo() {
        yield Buffer.from('contenido-prueba');
      }()),
    },
  };
}

test('conserva una descarga multimedia pendiente entre instancias hasta confirmarla', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-descarga-durable-'));

  try {
    const primera = new ColaDescargaMultimediaDurable(ruta);
    const tarea = await primera.agregar(mensajePrueba());

    assert.equal(await primera.cantidad(), 1);
    assert.equal(tarea.mensaje.tipoMensaje, 'foto');
    assert.equal(tarea.mensaje.mensajeProveedorId, 'MENSAJE-MULTIMEDIA-001');
    assert.equal('multimedia' in tarea.mensaje, false);

    const segunda = new ColaDescargaMultimediaDurable(ruta);
    const pendientes = await segunda.listar();

    assert.equal(pendientes.length, 1);
    assert.equal(pendientes[0]?.clave, tarea.clave);
    assert.equal(pendientes[0]?.mensaje.contenidoCrudo, tarea.mensaje.contenidoCrudo);

    await segunda.confirmar(tarea);
    assert.equal(await segunda.cantidad(), 0);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});

test('deduplica la misma descarga multimedia por identidad del mensaje', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-descarga-dedup-'));

  try {
    const cola = new ColaDescargaMultimediaDurable(ruta);
    const mensaje = mensajePrueba();
    const primera = await cola.agregar(mensaje);
    const segunda = await cola.agregar(mensaje);

    assert.equal(primera.clave, segunda.clave);
    assert.equal(await cola.cantidad(), 1);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});
