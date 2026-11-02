<?php

namespace App\Console\Commands;

use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use Illuminate\Console\Command;

/**
 * Despacho protegido por grupo. Conserva las validaciones y las reservas
 * transaccionales de mensajero:despachar-buzon para cada grupo.
 */
final class DespacharGruposProtegidos extends Command
{
    protected $signature = 'mensajero:despachar-grupos-protegidos
        {--limite=20 : Máximo total de publicaciones por ejecución (1-20)}
        {--solo-validar : Comprobar permisos y cortes sin publicar trabajos}';

    protected $description = 'Despacha los grupos autorizados con cortes UTC individuales y límite global';

    public function handle(ControlInterpretacionGemini $control, PoliticaEnvioGemini $politica): int
    {
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($limite === false || $limite < 1 || $limite > 20) {
            $this->error('El límite total debe estar entre 1 y 20.');
            return self::FAILURE;
        }

        // Ningún grupo se despacha si no están activos los controles globales.
        if (config('mensajero.normalizacion.habilitada', false) !== true
            || config('mensajero.normalizacion.despacho_automatico_protegido', false) !== true
            || ! $politica->configuracionCompleta()
            || ! $control->estado(0)['habilitado']) {
            $this->error('No está habilitada la política completa de despacho protegido.');
            return self::FAILURE;
        }

        // Nunca usar "ahora" como corte predeterminado, ni aceptar fechas
        // ambiguas. Cada grupo usará el máximo de los tres cortes autorizados.
        $corteConfigurado = trim((string) config('mensajero.normalizacion.despacho_automatico_desde_utc', ''));
        $fechaConfigurada = \DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $corteConfigurado,
            new \DateTimeZone('UTC')
        );

        if ($fechaConfigurada === false
            || $fechaConfigurada->format('Y-m-d\TH:i:s\Z') !== $corteConfigurado) {
            $this->error('Falta un corte explícito válido de despacho en UTC.');
            return self::FAILURE;
        }

        $grupos = $control->gruposHabilitados();
        sort($grupos, SORT_NUMERIC);

        // La interfaz permite como máximo seis grupos; la carga global no
        // puede superar el límite explícito de cada ejecución.
        if ($grupos === [] || count($grupos) > 6 || count($grupos) > $limite
            || count($grupos) !== count(array_unique($grupos))) {
            $this->error('Se requieren entre 1 y 6 grupos únicos, con límite suficiente para todos.');
            return self::FAILURE;
        }

        $utc = new \DateTimeZone('UTC');

        // Validar TODOS los grupos antes de publicar cualquier trabajo.
        // Al despachar, el comando interno vuelve a verificar cada permiso.
        foreach ($grupos as $grupo) {
            $grupo = (int) $grupo;
            $cortePolitica = $politica->fechaInicioGrupo($grupo);
            $corteGrupo = $control->corteEfectivoGrupo($grupo);

            if ($grupo < 1 || $cortePolitica === null || $corteGrupo === null
                || ! $control->permite($grupo, $corteGrupo)) {
                $this->error('Grupo '.$grupo.' sin autorización o corte válido. Ningún trabajo publicado.');
                return self::FAILURE;
            }

            $desde = max(
                $fechaConfigurada->format('Y-m-d H:i:s.u'),
                $cortePolitica->setTimezone($utc)->format('Y-m-d H:i:s.u'),
                $corteGrupo->setTimezone($utc)->format('Y-m-d H:i:s.u')
            );
            $this->line('Grupo '.$grupo.' | corte efectivo UTC: '.$desde);
        }

        if ($this->option('solo-validar')) {
            $this->info('VALIDACIÓN CORRECTA. No se publicó ningún trabajo ni se consultó Google.');
            return self::SUCCESS;
        }

        $totalGrupos = count($grupos);
        $cuotaBase = intdiv($limite, $totalGrupos);
        $excedente = $limite % $totalGrupos;
        $grupoAnterior = config('mensajero.normalizacion.despacho_automatico_grupo', 0);
        $fallos = 0;

        try {
            foreach ($grupos as $indice => $grupo) {
                $cuota = $cuotaBase + ($indice < $excedente ? 1 : 0);

                // Modificar solamente la configuración de esta ejecución.
                // El comando interno selecciona/reserva en SQL por grupo y
                // fecha, y vuelve a comprobar la autorización individual.
                config(['mensajero.normalizacion.despacho_automatico_grupo' => (int) $grupo]);

                $this->line('Grupo '.$grupo.' | límite: '.$cuota);
                try {
                    $codigo = $this->call('mensajero:despachar-buzon', [
                        '--automatico-protegido' => true,
                        '--limite' => $cuota,
                    ]);

                    if ($codigo !== self::SUCCESS) {
                        $fallos++;
                        $this->error('Despacho incompleto del grupo '.$grupo.'.');
                    }
                } catch (\Throwable $error) {
                    $fallos++;
                    $this->error('Excepción del grupo '.$grupo.': '.get_class($error));
                }
            }
        } finally {
            config(['mensajero.normalizacion.despacho_automatico_grupo' => $grupoAnterior]);
        }

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
