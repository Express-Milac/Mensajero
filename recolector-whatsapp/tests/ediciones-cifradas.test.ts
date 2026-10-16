import assert from 'node:assert/strict';
import { createCipheriv, createHmac } from 'node:crypto';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { proto } from '@whiskeysockets/baileys';
import { AlmacenContextoEdicionesDurable } from '../src/ediciones/almacen-contexto-ediciones-durable.js';
import {
  descifrarEdicionCifradaWhatsapp,
  esEdicionCifradaWhatsapp,
  extraerContextoEdicionOriginal,
} from '../src/ediciones/ediciones-cifradas.js';
import { analizarSemanticaMensaje } from '../src/mensajes.js';

function hmacSha256(datos: Uint8Array, clave: Uint8Array): Buffer {
  return createHmac('sha256', Buffer.from(clave))
    .update(Buffer.from(datos))
    .digest();
}

function derivarClave(
  secreto: Uint8Array,
  mensajeId: string,
  remitenteOriginal: string,
  editor: string,
): Buffer {
  const informacion = Buffer.concat([
    Buffer.from(mensajeId),
    Buffer.from(remitenteOriginal),
    Buffer.from(editor),
    Buffer.from('Message Edit'),
    Buffer.from([1]),
  ]);
  const extraida = hmacSha256(secreto, new Uint8Array(32));
  return hmacSha256(informacion, extraida);
}

function cifrarGcm(textoPlano: Uint8Array, clave: Uint8Array, iv: Uint8Array): Buffer {
  const cifrador = createCipheriv('aes-256-gcm', Buffer.from(clave), Buffer.from(iv));
  const cifrado = Buffer.concat([
    cifrador.update(Buffer.from(textoPlano)),
    cifrador.final(),
  ]);
  return Buffer.concat([cifrado, cifrador.getAuthTag()]);
}

test('extrae y conserva de forma durable el contexto criptografico de un mensaje original', async () => {
  const raiz = await mkdtemp(join(tmpdir(), 'mensajero-contexto-edicion-'));

  try {
    const secreto = Buffer.alloc(32, 7);
    const mensaje = {
      key: {
        remoteJid: '120363000000000000@g.us',
        id: 'MENSAJE-ORIGINAL-001',
        fromMe: false,
        participant: '5211111111111@s.whatsapp.net',
        participantAlt: '111111111111111@lid',
      },
      message: {
        conversation: 'original',
        messageContextInfo: {
          messageSecret: secreto,
        },
      },
    };
    const contexto = extraerContextoEdicionOriginal(mensaje);

    assert.ok(contexto);
    assert.equal(contexto.secretoMensaje.byteLength, 32);
    assert.equal(contexto.remitentesProveedor.length, 2);

    const almacen = new AlmacenContextoEdicionesDurable(raiz);
    await almacen.preparar();
    await almacen.guardar(contexto);

    const otraInstancia = new AlmacenContextoEdicionesDurable(raiz);
    await otraInstancia.preparar();
    const recuperado = await otraInstancia.obtener(
      contexto.chatId,
      contexto.mensajeProveedorId,
    );

    assert.ok(recuperado);
    assert.deepEqual(Buffer.from(recuperado.secretoMensaje), secreto);
    assert.deepEqual(recuperado.remitentesProveedor, contexto.remitentesProveedor);
  } finally {
    await rm(raiz, { recursive: true, force: true });
  }
});

test('descifra secretEncryptedMessage MESSAGE_EDIT y produce la forma de edicion normalizada', () => {
  const grupo = '120363000000000000@g.us';
  const objetivo = 'MENSAJE-OBJETIVO-001';
  const remitente = '5211111111111@s.whatsapp.net';
  const secreto = Buffer.alloc(32, 19);
  const iv = Buffer.alloc(12, 23);
  const plano = proto.Message.encode({
    protocolMessage: {
      type: proto.Message.ProtocolMessage.Type.MESSAGE_EDIT,
      key: {
        remoteJid: grupo,
        id: objetivo,
        fromMe: false,
        participant: remitente,
      },
      editedMessage: {
        conversation: 'texto editado',
      },
    },
  }).finish();
  const clave = derivarClave(secreto, objetivo, remitente, remitente);
  const carga = cifrarGcm(plano, clave, iv);
  const mensaje = {
    key: {
      remoteJid: grupo,
      id: 'ENVOLTURA-EDICION-001',
      fromMe: false,
      participant: remitente,
    },
    message: {
      secretEncryptedMessage: {
        targetMessageKey: {
          remoteJid: grupo,
          id: objetivo,
          fromMe: false,
          participant: remitente,
        },
        encPayload: carga,
        encIv: iv,
        secretEncType: proto.Message.SecretEncryptedMessage.SecretEncType.MESSAGE_EDIT,
      },
    },
    messageTimestamp: 1_792_184_000,
  };

  assert.equal(esEdicionCifradaWhatsapp(mensaje.message), true);

  const resultado = descifrarEdicionCifradaWhatsapp(mensaje, {
    chatId: grupo,
    mensajeProveedorId: objetivo,
    desdeMiCuenta: false,
    remitentesProveedor: [remitente],
    secretoMensaje: new Uint8Array(secreto),
    guardadoEn: new Date().toISOString(),
  });

  assert.equal(resultado.causaFallo, null);
  assert.ok(resultado.actualizacion);
  assert.equal(resultado.actualizacion.key.id, objetivo);

  const semantica = analizarSemanticaMensaje(resultado.actualizacion.update.message);
  assert.equal(semantica.tipoEvento, 'edicion');
  assert.equal(semantica.tipoMensaje, 'texto');
});

test('falla cerrado si el secreto del original no autentica la edicion cifrada', () => {
  const grupo = '120363000000000000@g.us';
  const objetivo = 'MENSAJE-OBJETIVO-002';
  const remitente = '5211111111111@s.whatsapp.net';
  const secretoCorrecto = Buffer.alloc(32, 31);
  const secretoIncorrecto = Buffer.alloc(32, 32);
  const iv = Buffer.alloc(12, 33);
  const plano = proto.Message.encode({
    protocolMessage: {
      type: proto.Message.ProtocolMessage.Type.MESSAGE_EDIT,
      editedMessage: { conversation: 'no debe salir' },
    },
  }).finish();
  const carga = cifrarGcm(
    plano,
    derivarClave(secretoCorrecto, objetivo, remitente, remitente),
    iv,
  );
  const resultado = descifrarEdicionCifradaWhatsapp({
    key: {
      remoteJid: grupo,
      id: 'ENVOLTURA-EDICION-002',
      participant: remitente,
    },
    message: {
      secretEncryptedMessage: {
        targetMessageKey: {
          remoteJid: grupo,
          id: objetivo,
          participant: remitente,
        },
        encPayload: carga,
        encIv: iv,
        secretEncType: proto.Message.SecretEncryptedMessage.SecretEncType.MESSAGE_EDIT,
      },
    },
  }, {
    chatId: grupo,
    mensajeProveedorId: objetivo,
    desdeMiCuenta: false,
    remitentesProveedor: [remitente],
    secretoMensaje: new Uint8Array(secretoIncorrecto),
    guardadoEn: new Date().toISOString(),
  });

  assert.equal(resultado.actualizacion, null);
  assert.equal(resultado.causaFallo, 'autenticacion_gcm_fallida');
});
