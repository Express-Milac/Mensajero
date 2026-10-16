import assert from 'node:assert/strict';
import test from 'node:test';
import { clasificarTipoMensaje, desarrollarContenido } from '../src/mensajes.js';

test('clasifica texto, foto, video, audio, documento y sticker', () => {
  assert.equal(clasificarTipoMensaje({ conversation: 'hola' }), 'texto');
  assert.equal(clasificarTipoMensaje({ imageMessage: {} }), 'foto');
  assert.equal(clasificarTipoMensaje({ videoMessage: {} }), 'video');
  assert.equal(clasificarTipoMensaje({ audioMessage: {} }), 'audio');
  assert.equal(clasificarTipoMensaje({ documentMessage: {} }), 'documento');
  assert.equal(clasificarTipoMensaje({ stickerMessage: {} }), 'sticker');
});

test('no confunde texto protobuf con sticker cuando otros campos vienen en null', () => {
  const contenidoProto = {
    stickerMessage: null,
    imageMessage: null,
    videoMessage: null,
    audioMessage: null,
    documentMessage: null,
    conversation: 'CAPTURA-TEXTO-001',
    extendedTextMessage: null,
  };

  assert.equal(clasificarTipoMensaje(contenidoProto), 'texto');
});

test('desenvuelve mensajes efímeros y de vista única', () => {
  const contenido = desarrollarContenido({
    ephemeralMessage: {
      message: {
        viewOnceMessageV2: {
          message: { imageMessage: { caption: 'evidencia' } },
        },
      },
    },
  });

  assert.ok(contenido);
  assert.ok('imageMessage' in contenido);
});
