<?php

namespace App\Console\Commands;

use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\ControlInterpretacionGemini;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico de solo lectura: no publica trabajos ni consulta Google.
 */
final class EstadoGemini extends Command
{
    protected $signature = 'mensajero:estado-gemini
        {--grupo=10020 : Grupo autorizado que se desea diagnosticar}
        {--limite=10 : Mensajes recientes que se deben mostrar (1-20)}';

    protected $description = 'Verifica la configuración y los resultados recientes de Gemini sin enviar datos ni gastar cuota.';

    public function handle(PoliticaEnvioGemini $politica): int
    {
        $grupoId = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupoId === false || $grupoId < 1 || $limite === false || $limite < 1 || $limite > 20) {
            $this->error('Indique un grupo válido y un límite de 1 a 20.');
            return self::FAILURE;
        }

        $ia = (array) config('mensajero.ia', []);
        $control = app(ControlInterpretacionGemini::class);
        $autorizado = $control->estado($grupoId)['habilitado'];
        $inicio = $politica->fechaInicioGrupo($grupoId);
        $configurada = $politica->configuracionCompleta();
        $globalEncendido = $control->estado(0)['habilitado'];
        $grupoEncendido = $control->estado($grupoId)['habilitado'];
        $automatica = $configurada && $control->corteEfectivoGrupo($grupoId) !== null;
        $normaliza = config('mensajero.normalizacion.habilitada') === true;

        $this->info('ESTADO GEMINI | Grupo interno '.$grupoId);
        $this->line('Politica IA completa: '.($configurada ? 'SI' : 'NO'));
        $this->line('Control general Gemini: '.($globalEncendido ? 'ENCENDIDO' : 'APAGADO'));
        $this->line('Control de este grupo: '.($grupoEncendido ? 'ENCENDIDO' : 'APAGADO'));
        $this->line('Interpretacion automatica efectiva: '.($automatica ? 'SI' : 'NO'));
        $this->line('Normalizacion habilitada (configuracion nueva): '.($normaliza ? 'SI' : 'NO'));
        $this->line('Grupo autorizado desde el panel: '.($autorizado ? 'SI' : 'NO'));
        $this->line('Corte global mínimo: '.($inicio?->format(DATE_ATOM) ?? 'NO CONFIGURADO'));
        $this->line('Corte web efectivo: '.($control->corteEfectivoGrupo($grupoId)?->format(DATE_ATOM) ?? 'APAGADO'));
        $this->warn('La configuracion NO demuestra que los trabajadores ya iniciados se hayan reiniciado.');

        try {
            if (! Schema::hasTable('mensajero_analisis_operativos_ia')) {
                $this->error('No existe la tabla mensajero_analisis_operativos_ia.');
                return self::FAILURE;
            }

            $resumen = DB::table('mensajero_analisis_operativos_ia')
                ->where('chat_monitoreado_id', $grupoId)
                ->select('estado')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('estado')
                ->get();

            foreach ($resumen as $fila) {
                $this->line('Resultado guardado '.$fila->estado.': '.$fila->total);
            }

            if (Schema::hasTable('mensajero_control_publicaciones_gemini')) {
                $publicaciones = DB::table('mensajero_control_publicaciones_gemini')
                    ->where('chat_monitoreado_id', $grupoId)
                    ->select('estado')->selectRaw('COUNT(*) as total')
                    ->groupBy('estado')->get();
                foreach ($publicaciones as $publicacion) {
                    $this->line('Control de envíos IA '.$publicacion->estado.': '.$publicacion->total);
                }
                $this->warn('Los estados reservado/publicado sin resultado NO deben reenviarse automáticamente.');
            }

            if ($inicio === null) {
                $this->warn('Sin corte temporal no es posible seleccionar mensajes recientes autorizados.');
            } else {
                $fechaUtc = $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

                $base = DB::table('mensajero_mensajes_whatsapp as m')
                    ->where('m.chat_monitoreado_id', $grupoId)
                    ->where('m.estado_captura', 'normalizado')
                    ->where(function ($consulta): void {
                        $consulta->where('m.tipo_mensaje', 'texto')
                            ->orWhere(function ($media): void {
                                $media->whereIn('m.tipo_mensaje', ['foto', 'video', 'documento'])
                                    ->whereNotNull('m.pie_multimedia');
                            });
                    })
                    ->where('m.recibido_en', '>=', $fechaUtc);

                $sinResultado = (clone $base)
                    ->whereNotExists(function ($consulta): void {
                        $consulta->selectRaw('1')
                            ->from('mensajero_analisis_operativos_ia as a')
                            ->whereColumn('a.mensaje_whatsapp_id', 'm.id');
                    })->count();

                $this->line('Textos y leyendas normalizados desde el corte sin resultado IA: '.$sinResultado);

                $ultimos = (clone $base)
                    ->leftJoin('mensajero_analisis_operativos_ia as a', 'a.mensaje_whatsapp_id', '=', 'm.id')
                    ->orderByDesc('m.id')
                    ->limit($limite)
                    ->get(['m.id', 'm.recibido_en', 'a.estado']);

                if ($ultimos->isEmpty()) {
                    $this->warn('No se encontraron textos o leyendas posteriores al corte.');
                } else {
                    $this->table(
                        ['ID de mensaje', 'Recibido (UTC)', 'Resultado IA en SQL'],
                        $ultimos->map(static fn ($m): array => [
                            (string) $m->id,
                            (string) $m->recibido_en,
                            $m->estado === null ? 'SIN RESULTADO' : (string) $m->estado,
                        ])->all()
                    );
                }
            }
        } catch (Throwable $error) {
            $this->error('No se pudo consultar SQL Server: '.class_basename($error));
            return self::FAILURE;
        }

        try {
            $cola = (string) ($ia['cola'] ?? 'mensajero_ia');
            if (! preg_match('/\A[a-zA-Z0-9_-]{1,90}\z/D', $cola)) {
                throw new \RuntimeException('Nombre de cola inválido');
            }

            $clave = 'queues:'.$cola;
            $redis = Redis::connection();
            $this->line('Redis IA, pendientes: '.$redis->llen($clave));
            $this->line('Redis IA, reservados: '.$redis->zcard($clave.':reserved'));
            $this->line('Redis IA, retrasados: '.$redis->zcard($clave.':delayed'));
        } catch (Throwable $error) {
            $this->warn('No se pudieron consultar las colas Redis: '.class_basename($error));
        }

        $this->warn('Este diagnóstico no comprueba que el programador ni los trabajadores estén vivos.');
        $this->warn('No se publica ningún trabajo y no se realiza ninguna solicitud a Google.');

        return self::SUCCESS;
    }
}
