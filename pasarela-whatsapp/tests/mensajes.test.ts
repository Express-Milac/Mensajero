import assert from 'node:assert/strict';
import test from 'node:test';
import { clasificarTipoMensaje } from '../src/mensajes.js';

test('clasifica texto, multimedia y pegatinas', () => {
  assert.equal(clasificarTipoMensaje({ conversation: 'Hola' }), 'texto');
  assert.equal(clasificarTipoMensaje({ imageMessage: { mimetype: 'image/jpeg' } }), 'foto');
  assert.equal(clasificarTipoMensaje({ videoMessage: { mimetype: 'video/mp4' } }), 'video');
  assert.equal(clasificarTipoMensaje({ audioMessage: { mimetype: 'audio/ogg' } }), 'audio');
  assert.equal(clasificarTipoMensaje({ documentMessage: { fileName: 'archivo.pdf' } }), 'documento');
  assert.equal(clasificarTipoMensaje({ stickerMessage: { mimetype: 'image/webp' } }), 'sticker');
});

test('desenvuelve mensajes efímeros antes de clasificarlos', () => {
  assert.equal(
    clasificarTipoMensaje({
      ephemeralMessage: {
        message: {
          viewOnceMessageV2: {
            message: { imageMessage: { mimetype: 'image/jpeg' } },
          },
        },
      },
    }),
    'foto',
  );
});
