import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtemp, readFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { AlmacenMultimediaDurable } from '../src/multimedia/almacen-multimedia-durable.js';
import type { MensajeProveedor } from '../src/tipos.js';

async function* flujoPrueba(): AsyncGenerator<Uint8Array> {
  yield Buffer.from('hola ', 'utf8');
  yield Buffer.from('multimedia', 'utf8');
}

function semanticaBase(): Pick<
  MensajeProveedor,
  | 'tipoEvento'
  | 'remitenteProveedorId'
  | 'mensajeCitadoProveedorId'
  | 'mensajeObjetivoProveedorId'
> {
  return {
    tipoEvento: 'mensaje',
    remitenteProveedorId: '521000000000@s.whatsapp.net',
    mensajeCitadoProveedorId: null,
    mensajeObjetivoProveedorId: null,
  };
}

test('persiste multimedia de forma durable con hash, tamaño y ruta privada', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-multimedia-'));

  try {
    const almacen = new AlmacenMultimediaDurable(ruta, 'recolector-pruebas');
    const mensaje: MensajeProveedor = {
      chatId: 'grupo-prueba@g.us',
      ...semanticaBase(),
      tipoMensaje: 'documento',
      eventoExternoId: 'evento-001',
      mensajeProveedorId: 'mensaje-001',
      contenidoCrudo: '{"documentMessage":{}}',
      recibidoEn: '2026-10-16T18:00:00.000Z',
      multimedia: {
        tipoMime: 'application/pdf',
        nombreOriginal: 'reporte.pdf',
        abrirFlujo: async () => flujoPrueba(),
      },
    };

    const guardado = await almacen.guardar(mensaje);
    assert.ok(guardado);
    assert.equal(guardado.extension, 'pdf');
    assert.equal(guardado.tamanoBytes, Buffer.byteLength('hola multimedia'));
    assert.equal(
      guardado.hashSha256,
      createHash('sha256').update('hola multimedia', 'utf8').digest('hex'),
    );
    assert.match(
      guardado.rutaRelativa,
      /^multimedia\/whatsapp\/recolector-pruebas\/2026\/10\/[a-f0-9]{64}\.pdf$/,
    );

    const rutaArchivo = join(ruta, ...guardado.rutaRelativa.split('/'));
    const contenido = await readFile(rutaArchivo, 'utf8');
    assert.equal(contenido, 'hola multimedia');

    const archivo = await stat(rutaArchivo);
    const directorio = await stat(join(ruta, 'multimedia', 'whatsapp', 'recolector-pruebas', '2026', '10'));
    assert.equal(archivo.mode & 0o777, 0o640, 'www-data debe poder leer mediante el grupo compartido');
    assert.equal(directorio.mode & 0o7777, 0o2750, 'el directorio debe ser privado y atravesable por el grupo');
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});

test('reutiliza un archivo durable existente sin volver a descargarlo', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-multimedia-'));

  try {
    const almacen = new AlmacenMultimediaDurable(ruta, 'recolector-pruebas');
    let descargas = 0;
    const mensaje: MensajeProveedor = {
      chatId: 'grupo-prueba@g.us',
      ...semanticaBase(),
      tipoMensaje: 'foto',
      eventoExternoId: 'evento-002',
      mensajeProveedorId: 'mensaje-002',
      contenidoCrudo: '{"imageMessage":{}}',
      recibidoEn: '2026-10-16T18:00:00.000Z',
      multimedia: {
        tipoMime: 'image/jpeg',
        nombreOriginal: null,
        abrirFlujo: async () => {
          descargas += 1;
          return flujoPrueba();
        },
      },
    };

    const primero = await almacen.guardar(mensaje);
    const segundo = await almacen.guardar(mensaje);

    assert.ok(primero);
    assert.ok(segundo);
    assert.equal(descargas, 1);
    assert.equal(segundo.rutaRelativa, primero.rutaRelativa);
    assert.equal(segundo.hashSha256, primero.hashSha256);
    assert.equal(segundo.tamanoBytes, primero.tamanoBytes);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});
