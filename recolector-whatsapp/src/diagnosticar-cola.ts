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

function resumirRespuesta(respuesta: string | null): unknown {
  if (!respuesta) {
    return null;
  }

  try {
    const json = JSON.parse(respuesta) as Record<string, unknown>;
    return {
      mensaje: typeof json.mensaje === 'string' ? json.mensaje : undefined,
      message: typeof json.message === 'string' ? json.message : undefined,
      errors: typeof json.errors === 'object' && json.errors !== null ? json.errors : undefined,
    };
  } catch {
    return respuesta.slice(0, 300);
  }
}

async function ejecutar(): Promise<void> {
  const tareas = await cola.listar();

  if (tareas.length === 0) {
    console.log('No hay tareas pendientes en la cola durable.');
    return;
  }

  console.log(`Tareas pendientes: ${tareas.length}`);

  for (const tarea of tareas) {
    try {
      await cliente.enviar(tarea.ruta, tarea.cuerpo);
      console.log(JSON.stringify({
        ruta: tarea.ruta,
        creadaEn: tarea.creadaEn,
        resultado: 'aceptada_por_laravel',
        nota: 'La tarea NO fue eliminada por este diagnóstico.',
      }));
    } catch (error) {
      if (error instanceof ErrorMensajero) {
        console.log(JSON.stringify({
          ruta: tarea.ruta,
          creadaEn: tarea.creadaEn,
          resultado: 'rechazada',
          estadoHttp: error.estadoHttp,
          respuesta: resumirRespuesta(error.respuesta),
        }));
        continue;
      }

      console.log(JSON.stringify({
        ruta: tarea.ruta,
        creadaEn: tarea.creadaEn,
        resultado: 'error_local',
        error: error instanceof Error ? error.message : 'Error desconocido',
      }));
    }
  }
}

ejecutar().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
});
