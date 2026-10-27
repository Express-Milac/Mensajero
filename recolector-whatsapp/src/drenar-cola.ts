import 'dotenv/config';
import { ClienteMensajero, ErrorMensajero } from './cliente-mensajero.js';
import { ColaDurable } from './cola/cola-durable.js';
import { cargarConfiguracion } from './configuracion.js';

const configuracion = cargarConfiguracion();
const cliente = new ClienteMensajero(
  configuracion.mensajeroUrl,
  configuracion.secreto,
  configuracion.claveConexion,
);
const cola = new ColaDurable(configuracion.rutaDatos);

async function ejecutar(): Promise<void> {
  const tareas = await cola.listar();

  if (tareas.length === 0) {
    console.log('No hay tareas pendientes en la cola durable.');
    return;
  }

  console.log(`Tareas por drenar: ${tareas.length}`);
  let confirmadas = 0;
  let fallidas = 0;

  for (const tarea of tareas) {
    try {
      await cliente.enviar(tarea.ruta, tarea.cuerpo);
      await cola.confirmar(tarea);
      confirmadas += 1;
      console.log(JSON.stringify({
        ruta: tarea.ruta,
        creadaEn: tarea.creadaEn,
        resultado: 'confirmada_y_retirada',
      }));
    } catch (error) {
      fallidas += 1;

      if (error instanceof ErrorMensajero) {
        console.log(JSON.stringify({
          ruta: tarea.ruta,
          creadaEn: tarea.creadaEn,
          resultado: 'permanece_pendiente',
          estadoHttp: error.estadoHttp,
          error: error.message,
        }));
        continue;
      }

      console.log(JSON.stringify({
        ruta: tarea.ruta,
        creadaEn: tarea.creadaEn,
        resultado: 'permanece_pendiente',
        error: error instanceof Error ? error.message : 'Error desconocido',
      }));
    }
  }

  console.log(JSON.stringify({
    confirmadas,
    fallidas,
    pendientesRestantes: await cola.cantidad(),
  }));

  if (fallidas > 0) {
    process.exitCode = 1;
  }
}

ejecutar().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
