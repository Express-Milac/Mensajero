<?php

namespace App\Console\Commands;

use App\Models\ChatMonitoreado;
use App\Services\Normalizacion\MedirEstadoBuzonWhatsapp;
use Illuminate\Console\Command;

/**
 * Diagnóstico de atrasos, solo de lectura y sin acceder a Redis.
 * Inicialmente restringido a SQL Server _test.
 */
final class VigilarBuzonWhatsapp extends Command
{
    protected $signature = 'mensajero:vigilar-buzon
        {--minutos=30 : Umbral de retraso en minutos, entre 5 y 1440}
        {--grupo= : Restringe el diagnóstico a un grupo activo y monitoreado}
        {--desglose : Mostrar conteos por ID de grupo y antigüedad sin datos personales}
        {--clasificar : Contar avisos por tipo y versión del contrato, requiere --grupo}
        {--alertar : Devuelve código de error si se detectan avisos atrasados}';

    protected $description = 'Muestra métricas de atrasos del buzón SQL sin publicar ni modificar mensajes';

    public function handle(MedirEstadoBuzonWhatsapp $medidor): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlsrv'
            || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')) {
            $this->error('Diagnóstico bloqueado: requiere SQL Server _test y entorno local/de pruebas.');

            return self::FAILURE;
        }

        $minutos = filter_var($this->option('minutos'), FILTER_VALIDATE_INT);
        $grupoTexto = $this->option('grupo');
        $grupoId = $grupoTexto === null ? null : filter_var($grupoTexto, FILTER_VALIDATE_INT);

        if ($minutos === false || $minutos < 5 || $minutos > 1440
            || ($grupoTexto !== null && ($grupoId === false || $grupoId < 1))) {
            $this->error('Indique --minutos entre 5 y 1440 y un --grupo positivo cuando se especifique.');

            return self::FAILURE;
        }

        if ($this->option('clasificar') === true && $grupoId === null) {
            $this->error('La clasificación requiere --grupo con ID exacto para limitar la consulta.');

            return self::FAILURE;
        }

        if ($grupoId !== null && ! ChatMonitoreado::query()
            ->whereKey($grupoId)
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->exists()) {
            $this->error('Grupo no encontrado, inactivo o no autorizado.');

            return self::FAILURE;
        }

        $medidas = $medidor->ejecutar($minutos, $grupoId);
        $alerta = $medidas['pendientes_atrasados'] > 0
            || $medidas['publicados_antiguos'] > 0
            || $medidas['reservas_vencidas'] > 0;

        $this->components->info('Diagnóstico de buzón SQL Server (solo lectura).');
        $this->line('Umbral de atraso: '.$minutos.' minutos.');
        $this->line('Pendientes de publicación: '.$medidas['pendientes_publicacion']);
        $this->line('Pendientes de publicación atrasados: '.$medidas['pendientes_atrasados']);
        $this->line('Publicados sin normalizar: '.$medidas['publicados_sin_normalizar']);
        $this->line('Publicados sin normalizar antiguos: '.$medidas['publicados_antiguos']);
        $this->line('Reservas vencidas sin publicar: '.$medidas['reservas_vencidas']);

        if ($alerta) {
            $this->warn('Atención: existen avisos atrasados. Un aviso antiguo NO prueba que Redis haya perdido el trabajo.');
        } else {
            $this->components->info('No se detectaron atrasos según el filtro y umbral indicados.');
        }

        if ($this->option('desglose') === true) {
            $desglose = $medidor->desglosarPendientes($minutos, $grupoId);

            if ($desglose['grupos'] === []) {
                $this->line('Desglose: no hay avisos pendientes de publicación en los grupos consultados.');
            } else {
                $this->line('Desglose de pendientes por grupo (ID únicamente; sin mensajes ni nombres):');

                foreach ($desglose['grupos'] as $fila) {
                    $this->line(sprintf(
                        'Grupo #%d, conexión #%d: %d pendiente(s), %d atrasado(s), %d mayor(es) a 24 h, %d mayor(es) a 7 días; prefijo de ensayo: %s.',
                        $fila['grupo_id'],
                        $fila['conexion_id'],
                        $fila['pendientes'],
                        $fila['atrasados'],
                        $fila['mayores_24h'],
                        $fila['mayores_7d'],
                        $fila['prefijo_ensayo'] ? 'sí' : 'no',
                    ));
                }

                if ($desglose['truncado']) {
                    $this->warn('Solo se muestran los primeros 50 grupos. El desglose no incluye a todos.');
                }
            }

            $this->line('Un prefijo de ensayo no demuestra por sí mismo que todos los datos sean ficticios.');
        }

        if ($this->option('clasificar') === true) {
            $clasificacion = $medidor->clasificarPendientes($grupoId);

            if ($clasificacion['categorias'] === []) {
                $this->line('Clasificación: el grupo no tiene avisos pendientes de publicación.');
            } else {
                $this->line('Clasificación de avisos pendientes del grupo #'.$grupoId.':');

                foreach ($clasificacion['categorias'] as $categoria) {
                    $this->line(sprintf(
                        'Evento: %s; mensaje: %s; contrato: %s; pendientes: %d.',
                        $categoria['tipo_evento'],
                        $categoria['tipo_mensaje'],
                        $categoria['version_contrato'],
                        $categoria['cantidad'],
                    ));
                }

                if ($clasificacion['truncado']) {
                    $this->warn('Clasificación limitada a 50 combinaciones; el listado es incompleto.');
                }
            }

            $this->line('La clasificación no determina si los datos son ficticios o reales.');
        }

        $this->line('Sin escrituras SQL, publicaciones Redis ni tareas programadas.');

        return $alerta && $this->option('alertar') === true
            ? self::FAILURE
            : self::SUCCESS;
    }
}
