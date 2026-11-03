import assert from 'node:assert/strict';
import test from 'node:test';
import { PlanificadorReintentosMultimedia } from '../src/multimedia/planificador-reintentos.js';

test('espera por tarea y permite continuar con otras descargas', () => {
  const plan = new PlanificadorReintentosMultimedia();
  const ahora = 1_000_000;

  assert.equal(plan.listo('foto-a', ahora), true);
  const primero = plan.registrarFallo('foto-a', ahora);
  assert.equal(primero.intentos, 1);
  assert.equal(primero.siguienteMs, ahora + 5 * 60_000);
  assert.equal(plan.listo('foto-a', ahora + 60_000), false);
  assert.equal(plan.listo('foto-a', ahora + 5 * 60_000), true);
  assert.equal(plan.listo('foto-b', ahora), true);

  const segundo = plan.registrarFallo('foto-a', ahora + 5 * 60_000);
  assert.equal(segundo.siguienteMs, ahora + 15 * 60_000);
  assert.equal(plan.listo('foto-a', ahora + 10 * 60_000), false);
});

test('limita los fallos repetidos a seis horas y no borra tareas', () => {
  const plan = new PlanificadorReintentosMultimedia();
  let ahora = 0;

  for (let i = 1; i <= 20; i++) {
    const registro = plan.registrarFallo('foto-vieja', ahora);
    const espera = registro.siguienteMs - ahora;
    assert.ok(espera >= 5 * 60_000);
    assert.ok(espera <= 6 * 60 * 60_000);
    ahora = registro.siguienteMs;
  }

  plan.confirmar('foto-vieja');
  assert.equal(plan.listo('foto-vieja', 0), true);
});
