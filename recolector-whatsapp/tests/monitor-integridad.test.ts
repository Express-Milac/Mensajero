import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, rm, utimes, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { MonitorIntegridad } from '../src/monitor-integridad.js';

test('persiste latidos validos y registra cierre limpio', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-integridad-'));
  try {
    const monitor = new MonitorIntegridad(ruta);
    assert.equal(await monitor.iniciar(), null);
    await monitor.latido();
    const datos = JSON.parse(await readFile(join(ruta, 'estado-ejecucion.json'), 'utf8'));
    assert.equal(datos.apagadoLimpio, false);
    assert.ok(Number.isFinite(Date.parse(datos.ultimoLatidoEn)));
    await monitor.cerrar();
    const final = JSON.parse(await readFile(join(ruta, 'estado-ejecucion.json'), 'utf8'));
    assert.equal(final.apagadoLimpio, true);
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});

test('resguarda estado truncado y conserva la interrupcion estimada', async () => {
  const ruta = await mkdtemp(join(tmpdir(), 'mensajero-integridad-corrupta-'));
  try {
    const archivo = join(ruta, 'estado-ejecucion.json');
    await writeFile(archivo, '', 'utf8');
    const fecha = new Date('2026-11-01T21:12:15.000Z');
    await utimes(archivo, fecha, fecha);
    const monitor = new MonitorIntegridad(ruta);
    const hueco = await monitor.iniciar();
    assert.equal(hueco?.causa, 'reinicio_no_confirmado');
    assert.equal(hueco?.inicioEn, fecha.toISOString());
    const restaurado = JSON.parse(await readFile(archivo, 'utf8'));
    assert.equal(restaurado.apagadoLimpio, false);
    const nombres = await readdir(ruta);
    const respaldos = nombres.filter((n) => n.startsWith('estado-ejecucion.json.corrupto-'));
    assert.equal(respaldos.length, 1);
    assert.equal(await readFile(join(ruta, respaldos[0]!), 'utf8'), '');
  } finally {
    await rm(ruta, { recursive: true, force: true });
  }
});
