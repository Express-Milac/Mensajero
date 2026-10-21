import assert from 'node:assert/strict';
import test from 'node:test';
import { IdentidadesRecolector, telefonoDeJid } from '../src/identidad-remitente.js';

test('prefiere el nombre guardado en la agenda al nombre de perfil', () => {
  const identidades = new IdentidadesRecolector();
  const id = '5214611234567@s.whatsapp.net';
  identidades.registrarContactos([{ id, name: 'Rosa - Operaciones', notify: 'Ro' }]);

  const resultado = identidades.resolver({
    key: { participant: id },
    pushName: 'Nombre del perfil',
  }, id);

  assert.deepEqual(resultado, {
    nombreRemitente: 'Rosa - Operaciones',
    fuenteNombreRemitente: 'contacto',
    telefonoRemitente: '5214611234567',
  });

  // Actualizar notify no puede reemplazar un nombre guardado.
  identidades.registrarContactos([{ id, notify: 'Nombre modificado en WhatsApp' }]);
  assert.equal(identidades.resolver({ key: { participant: id } }, id).nombreRemitente, 'Rosa - Operaciones');
});

test('extrae telefono del participante alternativo solo si la clave esta vinculada', () => {
  const identidades = new IdentidadesRecolector();
  const id = '9988776655@lid';
  const clave = { participant: id, participantAlt: '5214611234567@s.whatsapp.net' };

  assert.equal(identidades.resolver({ key: clave }, id).telefonoRemitente, '5214611234567');
  assert.equal(
    identidades.resolver({
      key: { participant: id, participantPn: '5214611234567@s.whatsapp.net' },
    }, id).telefonoRemitente,
    '5214611234567',
  );
  assert.equal(
    identidades.resolver({ key: { ...clave, participant: 'otra-persona@lid' } }, id).telefonoRemitente,
    null,
  );
  assert.equal(identidades.resolver({ key: { participant: id } }, id).telefonoRemitente, null);
  assert.equal(telefonoDeJid(id), null);
});

test('recupera nombre de agenda mediante el identificador alternativo de la misma clave', () => {
  const identidades = new IdentidadesRecolector();
  const id = '9988776655@lid';
  const pn = '5214611234567@s.whatsapp.net';
  identidades.registrarContactos([{ id: pn, name: 'Alejandra - Logistica' }]);

  assert.deepEqual(identidades.resolver({
    key: { participant: id, participantAlt: pn },
    pushName: 'Ali',
  }, id), {
    nombreRemitente: 'Alejandra - Logistica',
    fuenteNombreRemitente: 'contacto',
    telefonoRemitente: '5214611234567',
  });

  assert.equal(identidades.resolver({ key: { participant: id }, pushName: 'Ali' }, id).nombreRemitente, 'Ali');
});

test('usa pushName si no hay contacto, y no confunde LID con telefono', () => {
  const identidades = new IdentidadesRecolector();
  assert.deepEqual(identidades.resolver({
    key: { participant: '88224466@lid' },
    pushName: '  Manuel Sistemas  ',
  }, '88224466@lid'), {
    nombreRemitente: 'Manuel Sistemas',
    fuenteNombreRemitente: 'perfil',
    telefonoRemitente: null,
  });

  assert.deepEqual(identidades.resolver({
    key: { participant: '12345678@lid' },
    pushName: 'Falsa atribucion',
  }, null), {
    nombreRemitente: null,
    fuenteNombreRemitente: null,
    telefonoRemitente: null,
  });
});

test('rechaza numeros invalidos y nombres de control', () => {
  const identidades = new IdentidadesRecolector();
  const id = '123456789@lid';
  identidades.registrarContactos([{ id, name: '\u0000\u0001', notify: '  Alicia  ' }]);

  assert.equal(identidades.resolver({ key: { participant: id } }, id).nombreRemitente, 'Alicia');
  assert.equal(
    identidades.resolver({
      key: { participant: id, participantPn: '123456789@incorrecto' },
    }, id).telefonoRemitente,
    null,
  );
});
