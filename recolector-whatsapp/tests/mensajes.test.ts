import assert from 'node:assert/strict';
import test from 'node:test';
import {
  analizarSemanticaMensaje,
  clasificarTipoMensaje,
  desarrollarContenido,
} from '../src/mensajes.js';

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

test('extrae cita de una respuesta sin perder el tipo de contenido', () => {
  const semantica = analizarSemanticaMensaje({
    extendedTextMessage: {
      text: 'respuesta',
      contextInfo: {
        stanzaId: 'MENSAJE-CITADO-001',
      },
    },
  });

  assert.deepEqual(semantica, {
    tipoEvento: 'mensaje',
    tipoMensaje: 'texto',
    mensajeCitadoProveedorId: 'MENSAJE-CITADO-001',
    mensajeObjetivoProveedorId: null,
  });
});

test('distingue edicion, eliminacion y reaccion con su mensaje objetivo', () => {
  const edicion = analizarSemanticaMensaje({
    protocolMessage: {
      type: 14,
      key: { id: 'MENSAJE-OBJETIVO-001' },
      editedMessage: {
        extendedTextMessage: { text: 'texto corregido' },
      },
    },
  });
  const eliminacion = analizarSemanticaMensaje({
    protocolMessage: {
      type: 0,
      key: { id: 'MENSAJE-OBJETIVO-002' },
    },
  });
  const reaccion = analizarSemanticaMensaje({
    reactionMessage: {
      text: '👍',
      key: { id: 'MENSAJE-OBJETIVO-003' },
    },
  });

  assert.equal(edicion.tipoEvento, 'edicion');
  assert.equal(edicion.tipoMensaje, 'texto');
  assert.equal(edicion.mensajeObjetivoProveedorId, 'MENSAJE-OBJETIVO-001');
  assert.equal(eliminacion.tipoEvento, 'eliminacion');
  assert.equal(eliminacion.tipoMensaje, 'eliminacion');
  assert.equal(eliminacion.mensajeObjetivoProveedorId, 'MENSAJE-OBJETIVO-002');
  assert.equal(reaccion.tipoEvento, 'reaccion');
  assert.equal(reaccion.tipoMensaje, 'reaccion');
  assert.equal(reaccion.mensajeObjetivoProveedorId, 'MENSAJE-OBJETIVO-003');
});

test('clasifica ubicacion, contacto y contenido desconocido sin descartarlos', () => {
  assert.equal(clasificarTipoMensaje({ locationMessage: { degreesLatitude: 20 } }), 'ubicacion');
  assert.equal(clasificarTipoMensaje({ contactMessage: { displayName: 'Prueba' } }), 'contacto');
  assert.equal(clasificarTipoMensaje({ pollCreationMessageV3: { name: 'Encuesta' } }), 'otro');

  const desconocido = analizarSemanticaMensaje({
    pollCreationMessageV3: { name: 'Encuesta' },
  });

  assert.equal(desconocido.tipoEvento, 'mensaje');
  assert.equal(desconocido.tipoMensaje, 'otro');
});
