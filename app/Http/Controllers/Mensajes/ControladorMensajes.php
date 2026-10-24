<?php

namespace App\Http\Controllers\Mensajes;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use App\Services\Mensajes\AnalizarPanoramaContenido;
use App\Services\Mensajes\IdentificarRemitente;
use App\Services\Mensajes\NotificadorTiempoReal;
use App\Services\Mensajes\PrepararEventosPanorama;
use App\Services\Normalizacion\ExtraerContenidoWhatsapp;
use App\Services\Seguridad\PrepararContenidoEvidencia;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;

final class ControladorMensajes extends Controller
{
    /**
     * Panorama directivo: actividad y contenido verificable de cada grupo.
     * No presenta señales técnicas como indicadores de negocio.
     */
    public function indice(Request $request, ExtraerContenidoWhatsapp $extraer, AnalizarPanoramaContenido $analizador, IdentificarRemitente $identificador): View
    {
        return view('mensajes.indice', $this->datosPanorama($request, $extraer, $analizador, $identificador));
    }

    /**
     * Catálogo de conversaciones consultables: separado del tablero con
     * indicadores directivos. Conserva filtros y carruseles individuales.
     */
    public function conversaciones(Request $request, ExtraerContenidoWhatsapp $extraer, AnalizarPanoramaContenido $analizador, IdentificarRemitente $identificador): View
    {
        return view('mensajes.conversaciones', $this->datosPanorama($request, $extraer, $analizador, $identificador));
    }

    /**
     * Credencial efímera únicamente para recibir avisos de cambios, sin contenido.
     * La autorización de los mensajes sigue verificándose en cada GET a Laravel.
     */
    public function credencialTiempoReal(NotificadorTiempoReal $notificador): JsonResponse
    {
        if (! $notificador->habilitado()) {
            return response()->json(['mensaje' => 'Tiempo real no habilitado.'], 503);
        }

        try {
            return response()->json($notificador->credencial())
                ->header('Cache-Control', 'private, no-store, max-age=0');
        } catch (\RuntimeException $error) {
            return response()->json(['mensaje' => $error->getMessage()], 503);
        }
    }

    /**
     * Fragmento actualizado únicamente cuando llega un aviso nuevo,
     * se restablece la conexión o vuelve a mostrarse la pestaña.
     */
    public function actualizacion(Request $request, ExtraerContenidoWhatsapp $extraer, AnalizarPanoramaContenido $analizador, IdentificarRemitente $identificador): Response
    {
        return response()
            ->view('mensajes.partes.panorama-dinamico', $this->datosPanorama($request, $extraer, $analizador, $identificador))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPanorama(Request $request, ExtraerContenidoWhatsapp $extraer, AnalizarPanoramaContenido $analizador, IdentificarRemitente $identificador): array
    {
        $opciones = $request->validate([
            'actividad' => ['nullable', 'in:todos,hoy,sin_actividad'],
            'orden' => ['nullable', 'in:recientes,actividad,nombre'],
        ]);

        $actividad = $opciones['actividad'] ?? 'todos';
        $orden = $opciones['orden'] ?? 'recientes';
        $zona = (string) config('app.timezone', 'America/Mexico_City');
        $inicioDia = CarbonImmutable::now($zona)->startOfDay()->utc();
        $finDia = CarbonImmutable::now($zona)->endOfDay()->utc();

        $grupos = ChatMonitoreado::query()
            ->with('conexion')
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->where('es_visible_tablero', true)
            ->get();

        // Lectura local; no envía mensajes a Gemini ni modifica la base de datos.
        $eventosPorGrupo = app(PrepararEventosPanorama::class)->ejecutar($grupos);

        $identificadores = $grupos->pluck('id')->all();
        $conteos = $identificadores === []
            ? collect()
            : EventoCrudoWhatsapp::query()
                ->select('chat_monitoreado_id')
                ->selectRaw('COUNT_BIG(*) AS total')
                ->whereIn('chat_monitoreado_id', $identificadores)
                ->where('tipo_evento', 'mensaje')
                ->whereBetween('recibido_en', [
                    $inicioDia->format('Y-m-d H:i:s.u'),
                    $finDia->format('Y-m-d H:i:s.u'),
                ])
                ->groupBy('chat_monitoreado_id')
                ->pluck('total', 'chat_monitoreado_id');

        $tarjetas = $grupos->map(function (ChatMonitoreado $grupo) use ($extraer, $conteos, $identificador, $eventosPorGrupo): array {
            // Se limita la consulta de contenido a ocho mensajes más
            // recientes por grupo. Nunca se descargan todos los eventos.
            $recientes = EventoCrudoWhatsapp::query()
                ->with('mensaje.remitente')
                ->where('chat_monitoreado_id', $grupo->id)
                ->where('tipo_evento', 'mensaje')
                ->orderByDesc('recibido_en')
                ->orderByDesc('id')
                ->limit(8)
                ->get();

            $identificador->precargar($recientes);

            return [
                'grupo' => $grupo,
                'hoy' => (int) ($conteos[$grupo->id] ?? 0),
                'ultima' => $recientes->first()?->recibido_en,
                'eventos' => $eventosPorGrupo[(int) $grupo->id] ?? [],
                'mensajes' => $recientes->map(fn (EventoCrudoWhatsapp $evento): array => [
                    'id' => $evento->id,
                    'remitente' => $identificador->nombre($evento),
                    'telefono_remitente' => $identificador->telefono($evento),
                    'tiene_remitente' => is_string($evento->id_remitente_proveedor) && $evento->id_remitente_proveedor !== '',
                    'texto' => Str::limit($this->resumen($evento, $extraer), 190),
                    'fecha' => $evento->recibido_en,
                ])->all(),
                // No inferir que un grupo sea de ensayo por su nombre.
                'ensayo' => preg_match('/^ensayo-redis-[0-9a-f]{16}$/D', (string) $grupo->conexion?->clave_conexion) === 1,
            ];
        });

        // Muestra acotada de hoy, solo de grupos autorizados: no dispara
        // normalización ni procesa los avisos antiguos del buzón.
        $muestra = $identificadores === []
            ? collect()
            : EventoCrudoWhatsapp::query()
                ->with('mensaje.remitente')
                ->whereIn('chat_monitoreado_id', $identificadores)
                ->where('tipo_evento', 'mensaje')
                ->whereBetween('recibido_en', [
                    $inicioDia->format('Y-m-d H:i:s.u'),
                    $finDia->format('Y-m-d H:i:s.u'),
                ])
                ->orderByDesc('recibido_en')
                ->orderByDesc('id')
                ->limit(300)
                ->get();

        $analisis = $analizador->ejecutar($muestra, $extraer, $identificador);
        $nombresGrupos = $grupos->mapWithKeys(fn (ChatMonitoreado $grupo): array => [
            $grupo->id => $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'),
        ])->all();

        $cantidadConActividad = $tarjetas->filter(fn (array $fila): bool => $fila['hoy'] > 0)->count();
        $mensajesHoy = $tarjetas->sum('hoy');
        $ultimaParticipacion = $tarjetas->pluck('ultima')->filter()->sortDesc()->first();
        $grupoMasActivo = $tarjetas->filter(fn (array $fila): bool => $fila['hoy'] > 0)
            ->sortByDesc('hoy')
            ->first();
        $grupoMasReciente = $tarjetas->filter(fn (array $fila): bool => $fila['ultima'] !== null)
            ->sortByDesc(fn (array $fila): int => $fila['ultima']->timestamp)
            ->first();

        $visibles = $tarjetas->filter(fn (array $fila): bool => match ($actividad) {
            'hoy' => $fila['hoy'] > 0,
            'sin_actividad' => $fila['hoy'] === 0,
            default => true,
        });

        $visibles = match ($orden) {
            'actividad' => $visibles->sort(fn (array $a, array $b): int => ($b['hoy'] <=> $a['hoy'])
                ?: (($b['ultima']?->timestamp ?? 0) <=> ($a['ultima']?->timestamp ?? 0))),
            'nombre' => $visibles->sort(fn (array $a, array $b): int => strcasecmp(
                (string) ($a['grupo']->nombre_interno ?: $a['grupo']->nombre_grupo ?: ''),
                (string) ($b['grupo']->nombre_interno ?: $b['grupo']->nombre_grupo ?: ''),
            )),
            default => $visibles->sort(fn (array $a, array $b): int => ($b['ultima']?->timestamp ?? 0) <=> ($a['ultima']?->timestamp ?? 0)),
        };

        return [
            'tarjetas' => $visibles->values(),
            'totalGrupos' => $tarjetas->count(),
            'cantidadConActividad' => $cantidadConActividad,
            'mensajesHoy' => $mensajesHoy,
            'ultimaParticipacion' => $ultimaParticipacion,
            'grupoMasActivo' => $grupoMasActivo,
            'grupoMasReciente' => $grupoMasReciente,
            'gruposSinActividadHoy' => $tarjetas->count() - $cantidadConActividad,
            'analisis' => $analisis,
            'nombresGrupos' => $nombresGrupos,
            'actividad' => $actividad,
            'orden' => $orden,
        ];
    }

    /**
     * Diagnóstico separado de la consulta directiva. Acceso restringido
     * al rol administrador, sin excepción temporal de administrador.
     */
    public function tecnico(): View
    {
        $grupos = ChatMonitoreado::query()
            ->with('conexion')
            ->withCount('eventosCrudos')
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->orderByDesc('eventos_crudos_count')
            ->orderBy('id')
            ->get();

        $gruposRecolector = $grupos->filter(
            fn (ChatMonitoreado $grupo) => $grupo->conexion?->proveedor === 'recolector_whatsapp',
        );
        $gruposOtrasConexiones = $grupos->reject(
            fn (ChatMonitoreado $grupo) => $grupo->conexion?->proveedor === 'recolector_whatsapp',
        );
        $identificadores = $grupos->pluck('id');
        $conexiones = $grupos->pluck('conexion_id')->unique();

        return view('mensajes.tecnico', [
            'grupos' => $grupos,
            'gruposRecolector' => $gruposRecolector,
            'gruposOtrasConexiones' => $gruposOtrasConexiones,
            'cantidadEventos' => (int) $grupos->sum('eventos_crudos_count'),
            'cantidadNormalizados' => $identificadores->isEmpty()
                ? 0
                : MensajeWhatsapp::query()->whereIn('chat_monitoreado_id', $identificadores)->count(),
            'cantidadPeriodos' => $conexiones->isEmpty()
                ? 0
                : PeriodoIntegridadWhatsapp::query()->whereIn('conexion_id', $conexiones)->count(),
        ]);
    }

    public function grupo(
        Request $request,
        int $chat,
        ExtraerContenidoWhatsapp $extraer,
        IdentificarRemitente $identificador,
    ): View {
        $grupo = ChatMonitoreado::query()
            ->with('conexion')
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->findOrFail($chat);

        $datos = $request->validate([
            'tipo' => ['nullable', 'in:mensaje,edicion,eliminacion,reaccion,otro'],
            'tipo_mensaje' => ['nullable', 'in:texto,foto,video,audio,documento,ubicacion,contacto,otro'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => $request->filled('desde')
                ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde']
                : ['nullable', 'date_format:Y-m-d'],
        ]);
        $tipo = $datos['tipo'] ?? '';
        $tipoMensaje = $datos['tipo_mensaje'] ?? '';
        $desde = $datos['desde'] ?? '';
        $hasta = $datos['hasta'] ?? '';

        $consulta = EventoCrudoWhatsapp::query()
            ->with(['mensaje.remitente', 'archivo'])
            ->where('chat_monitoreado_id', $grupo->id);

        if ($tipo !== '') {
            $consulta->where('tipo_evento', $tipo);
        }

        if ($tipoMensaje !== '') {
            $consulta->where('tipo_mensaje', $tipoMensaje);
        }

        // Se filtra en hora local visible al usuario, almacenando la
        // consulta como UTC porque SQL Server datetime2 no tiene zona.
        $zonaHoraria = (string) config('app.timezone', 'America/Mexico_City');

        if ($desde !== '') {
            $inicioUtc = CarbonImmutable::parse($desde, $zonaHoraria)
                ->startOfDay()->utc()->format('Y-m-d H:i:s.u');
            $consulta->where('recibido_en', '>=', $inicioUtc);
        }

        if ($hasta !== '') {
            $finUtc = CarbonImmutable::parse($hasta, $zonaHoraria)
                ->endOfDay()->utc()->format('Y-m-d H:i:s.u');
            $consulta->where('recibido_en', '<=', $finUtc);
        }

        $eventos = $consulta
            ->orderByDesc('recibido_en')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $identificador->precargar($eventos->getCollection());
        $eventos->getCollection()->transform(fn (EventoCrudoWhatsapp $evento) => [
            'evento' => $evento,
            'resumen' => $this->resumen($evento, $extraer),
            'remitente' => $identificador->nombre($evento),
            'telefono_remitente' => $identificador->telefono($evento),
        ]);

        $periodosConexion = PeriodoIntegridadWhatsapp::query()
            ->where('conexion_id', $grupo->conexion_id);

        $totalPeriodos = (clone $periodosConexion)->count();
        $periodos = $periodosConexion
            ->orderByDesc('inicio_en')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('mensajes.grupo', compact('grupo', 'eventos', 'periodos', 'totalPeriodos', 'tipo', 'tipoMensaje', 'desde', 'hasta'));
    }

    public function evento(
        int $evento,
        ExtraerContenidoWhatsapp $extraer,
        PrepararContenidoEvidencia $preparador,
        IdentificarRemitente $identificador,
    ): View {
        $registro = EventoCrudoWhatsapp::query()
            ->with(['chat', 'mensaje.remitente', 'archivo'])
            ->whereHas('chat', function ($consulta) {
                $consulta->where('tipo_chat', 'grupo')
                    ->where('es_monitoreado', true)
                    ->where('es_visible_tablero', true)
                    ->where('es_activo', true);
            })
            ->findOrFail($evento);

        $resumen = $this->resumen($registro, $extraer);
        $vista = $preparador->ejecutar($registro->contenido);
        $contenido = $vista['contenido'];
        $truncado = $vista['truncado'];

        $identificador->precargar(collect([$registro]));
        $remitente = $identificador->nombre($registro);
        $telefonoRemitente = $identificador->telefono($registro);

        return view('mensajes.evento', compact('registro', 'resumen', 'contenido', 'truncado', 'remitente', 'telefonoRemitente'));
    }

    private function resumen(EventoCrudoWhatsapp $evento, ExtraerContenidoWhatsapp $extraer): string
    {
        if ($evento->tipo_evento !== 'mensaje') {
            return match ($evento->tipo_evento) {
                'edicion' => 'Edición registrada. El mensaje original no ha sido reemplazado.',
                'eliminacion' => 'Eliminación registrada como evidencia.',
                'reaccion' => 'Reacción registrada como evidencia.',
                default => 'Evento registrado sin interpretación adicional.',
            };
        }

        if ($evento->mensaje !== null) {
            $texto = $evento->mensaje->contenido_texto ?: $evento->mensaje->pie_multimedia;
            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        }

        try {
            $datos = $extraer->ejecutar($evento->contenido);
            $texto = $datos['texto'] ?: $datos['pie_multimedia'];

            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        } catch (JsonException) {
            return 'Contenido no interpretable; se conserva la evidencia original.';
        }

        return match ($evento->tipo_mensaje) {
            'foto' => 'Fotografía recibida.',
            'video' => 'Video recibido.',
            'audio' => 'Audio recibido.',
            'documento' => 'Documento recibido.',
            'ubicacion' => 'Ubicación compartida.',
            'contacto' => 'Contacto compartido.',
            default => 'Mensaje recibido sin texto visible.',
        };
    }

}
