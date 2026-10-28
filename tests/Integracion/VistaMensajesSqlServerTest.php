<?php

namespace Tests\Integracion;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Enums\Rol;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use App\Models\Usuario;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VistaMensajesSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        config([
            'mensajero.autenticacion.modo' => 'deshabilitado',
            'mensajero.consulta_local_sin_autenticacion' => true,
        ]);
    }

    public function test_el_diagnostico_administrativo_tiene_el_mismo_tema_claro_y_permiso_de_acceso(): void
    {
        config(['mensajero.autenticacion.modo' => 'local']);

        $administrador = new Usuario([
            'nombre' => 'Administrador de prueba',
            'email' => 'visual@example.test',
        ]);
        $administrador->id = 150;
        $administrador->rol = Rol::Administrador;

        $this->actingAs($administrador)
            ->get('/mensajes/tecnico')
            ->assertOk()
            ->assertSee('Diagnóstico técnico')
            ->assertSee('pagina-diagnostico', false)
            ->assertSee('css/panorama-claro.css')
            ->assertSee('css/catalogo-grupos.css')
            ->assertSee('css/interfaz-unificada.css')
            ->assertSee('class="panorama-claro pagina-tecnica"', false)
            ->assertSee('MENSAJES NORMALIZADOS');
    }

    public function test_consulta_local_muestra_grupo_y_evidencia_escapada_sin_normalizar_ni_modificar_datos(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'vista-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'vista-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Grupo de prueba visual '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
            ]);

            $contenido = '<script>alert(1)</script>';
            $secretoPrueba = 'mensaje_secreto_'.$sufijo;
            $identificadorPrivado = 'contacto-'.$sufijo.'@s.whatsapp.net';
            $captura = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'vista-evento-'.$sufijo,
                mensajeProveedorId: 'vista-evento-'.$sufijo,
                contenidoCrudo: json_encode([
                    'key' => [
                        'remoteJid' => 'grupo-interno-'.$sufijo.'@g.us',
                        'participantPn' => $identificadorPrivado,
                    ],
                    'message' => [
                        'conversation' => 'Llegó '.$contenido,
                        'messageContextInfo' => ['messageSecret' => $secretoPrueba],
                    ],
                ], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC'),
                versionContrato: 'v3-recolector',
                remitenteProveedorId: '5219990000000@s.whatsapp.net',
            ));

            $this->assertSame('aceptado', $captura->estado);

            PeriodoIntegridadWhatsapp::query()->create([
                'conexion_id' => $conexion->id,
                'identificador_periodo' => hash('sha256', 'intervalo-vista-'.$sufijo),
                'inicio_en' => CarbonImmutable::parse('2026-10-19T11:00:00Z'),
                'fin_en' => CarbonImmutable::parse('2026-10-19T11:00:05Z'),
                'causa' => 'desconexion_whatsapp',
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('/tablero')
                ->assertOk()
                ->assertSee('Lo que está pasando en los grupos')
                ->assertSee('Mensajero MILAC')
                ->assertSee('EXPRESS MILAC')
                ->assertSee('Grupos en seguimiento')
                ->assertDontSee('centro-metricas', false)
                ->assertSee('Temas clave del día')
                ->assertSee('Posibles acuerdos mencionados')
                ->assertSee('Menciones para revisar')
                ->assertSee('Comprobando mensajes...')
                ->assertSee('data-buscar-conversaciones', false)
                ->assertSee('lista-grupos-directivos centro-lista-grupos modo-lista', false)
                ->assertSee('Ver mensaje original')
                ->assertSee('Grupo de prueba visual '.$sufijo)
                ->assertSee('Llegó '.$contenido)
                ->assertDontSee($contenido, false)
                ->assertDontSee($secretoPrueba, false)
                ->assertDontSee($identificadorPrivado, false)
                ->assertSee('Abrir conversación')
                ->assertSee('data-carrusel-mensajes', false)
                ->assertSee('data-grupo-id="'.$grupo->id.'"', false)
                ->assertSee('Carrusel de mensajes de Grupo de prueba visual '.$sufijo)
                ->assertDontSee('carrusel-grupos-vertical', false)
                ->assertSee('js/panorama.js')
                ->assertSee('data-panorama-url')
                ->assertSee('css/interfaz-unificada.css')
                ->assertSee('css/panorama-claro.css')
                ->assertSee('class="panorama-claro', false);

            // La actualización entrega solo los fragmentos, sin revelar
            // secretos ni renderizar un documento HTML completo.
            $respuestaActualizacion = $this->get('/mensajes/actualizacion');
            $respuestaActualizacion
                ->assertOk()
                ->assertHeader('Cache-Control')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertSee('data-panorama-indicadores', false)
                ->assertSee('Temas clave del día')
                ->assertSee('Grupo de prueba visual '.$sufijo)
                ->assertSee('data-panorama-tarjetas', false)
                ->assertSee('data-grupo-id="'.$grupo->id.'"', false)
                ->assertSee('data-carrusel-mensajes', false)
                ->assertSee('Llegó '.$contenido)
                ->assertDontSee($contenido, false)
                ->assertDontSee($secretoPrueba, false)
                ->assertDontSee($identificadorPrivado, false)
                ->assertDontSee('<!doctype html>', false)
                ->assertDontSee('css/interfaz-unificada.css', false)
                ->assertDontSee('<body', false);

            // Symfony puede reordenar las directivas del encabezado.
            // Verificar su conjunto, no la serialización de su orden.
            $this->assertEqualsCanonicalizing(
                ['max-age=0', 'no-store', 'private'],
                array_map('trim', explode(',', (string) $respuestaActualizacion->headers->get('Cache-Control'))),
            );

            // Otro mensaje aparece en la respuesta de actualización,
            // sin necesidad de normalizarlo ni volver a cargar la página.
            $mensajeNuevo = 'Nuevo mensaje visible sin recargar '.$sufijo;
            $capturaNueva = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'vista-nuevo-'.$sufijo,
                mensajeProveedorId: 'vista-nuevo-'.$sufijo,
                contenidoCrudo: json_encode(['message' => ['conversation' => $mensajeNuevo]], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC')->addSecond(),
                versionContrato: 'v3-recolector',
                remitenteProveedorId: '5219990000000@s.whatsapp.net',
            ));
            $this->assertSame('aceptado', $capturaNueva->estado);

            $this->get('/mensajes/actualizacion?actividad=hoy&orden=recientes')
                ->assertOk()
                ->assertSee($mensajeNuevo)
                ->assertSee('Grupo de prueba visual '.$sufijo)
                ->assertSee('Abrir conversación');

            // Opciones de menú independientes: cada destino tiene
            // una pantalla y una función distinta.
            $this->get('/mensajes')
                ->assertOk()
                ->assertSee('Explorar conversaciones')
                ->assertSee('Conversaciones recientes')
                ->assertSee('Grupo de prueba visual '.$sufijo)
                ->assertSee('Carrusel de mensajes de Grupo de prueba visual '.$sufijo)
                ->assertSee('css/interfaz-unificada.css')
                ->assertDontSee('Temas clave del día');

            $this->get('/grupos?estado=monitoreados&origen=recolector')
                ->assertOk()
                ->assertSee('Gestor de grupos')
                ->assertSee('Habilitar grupos')
                ->assertSee('Grupo de prueba visual '.$sufijo)
                // El gestor actual muestra "Conversación" como acción;
                // verificar además que lleve al historial del grupo correcto.
                ->assertSee('href="'.route('mensajes.grupo', $grupo->id).'" class="gestor-ver-grupo"', false)
                ->assertSee('Conversación')
                ->assertSee('Acceso controlado')
                ->assertSee('css/interfaz-unificada.css');

            $this->get('/grupos?estado=no_monitoreados')
                ->assertOk()
                ->assertDontSee('Grupo de prueba visual '.$sufijo);

            $this->get('/tablero')
                ->assertOk()
                ->assertSee('Lo que está pasando en los grupos')
                ->assertSee('Grupo de prueba visual '.$sufijo);

            $this->get('/mensajes?actividad=hoy')
                ->assertOk()
                ->assertSee('Grupo de prueba visual '.$sufijo);

            $this->get('/mensajes?orden=nombre')
                ->assertOk()
                ->assertSee('Grupo de prueba visual '.$sufijo);

            // Los indicadores de integridad no deben dominar el panorama.
            $this->get('/mensajes')->assertDontSee('MENSAJES NORMALIZADOS');

            // El diagnóstico exige un administrador, también en el POC sin login.
            $this->get('/mensajes/tecnico')->assertForbidden();

            $this->get('/mensajes/grupos/'.$grupo->id)
                ->assertOk()
                ->assertSee('Llegó '.$contenido)
                ->assertDontSee($contenido, false)
                ->assertSee('Ver evidencia')
                ->assertSee('Conversación registrada')
                ->assertSee('mensaje-burbuja', false)
                ->assertSee('separador-fecha', false)
                ->assertSee('Registro de conectividad')
                ->assertSee('css/interfaz-unificada.css')
                ->assertSee('1 intervalo registrado')
                ->assertSee('Desconexión de WhatsApp')
                ->assertSee('Estado «Capturado»')
                ->assertDontSee('Integridad pendiente de conciliación');

            // Filtros funcionales de consulta: sobre el histórico crudo,
            // sin activar normalización ni alterar el contenido de la evidencia.
            $diaLocal = CarbonImmutable::now((string) config('app.timezone'))->format('Y-m-d');

            $this->get('/mensajes/grupos/'.$grupo->id.'?tipo_mensaje=texto&desde='.$diaLocal.'&hasta='.$diaLocal)
                ->assertOk()
                ->assertSee('Filtrar mensajes')
                ->assertSee('Llegó '.$contenido);

            $this->get('/mensajes/grupos/'.$grupo->id.'?tipo_mensaje=audio')
                ->assertOk()
                ->assertDontSee('Llegó '.$contenido);

            $this->get('/mensajes/grupos/'.$grupo->id.'?desde=2001-01-01&hasta=2001-01-02')
                ->assertOk()
                ->assertDontSee('Llegó '.$contenido);

            $this->get('/mensajes/grupos/'.$grupo->id.'?hasta='.$diaLocal)
                ->assertOk()
                ->assertSee('Llegó '.$contenido);

            $this->get('/mensajes/eventos/'.$captura->eventoCrudoId)
                ->assertOk()
                ->assertSee('v3-recolector')
                ->assertSee('Vista protegida del contenido crudo')
                ->assertSee('Identificadores y verificación SHA-256')
                ->assertSee('detalle-tecnico', false)
                ->assertSee('pagina-detalle-evidencia', false)
                ->assertSee('css/interfaz-unificada.css')
                ->assertSee('css/panorama-claro.css')
                ->assertSee('class="panorama-claro pagina-evidencia', false)
                ->assertSee('Contenido del mensaje')
                ->assertSee('OCULTO EN LA VISTA')
                // El número legítimo puede mostrarse como identificación
                // en una página autorizada, pero nunca el JID íntegro
                // ni secretos de la evidencia original.
                ->assertSee('Teléfono +5219990000000')
                ->assertDontSee($secretoPrueba, false)
                ->assertDontSee($identificadorPrivado, false)
                ->assertDontSee('5219990000000@s.whatsapp.net', false)
                ->assertDontSee($contenido, false);

            $crudoGuardado = EventoCrudoWhatsapp::query()->findOrFail($captura->eventoCrudoId);
            $this->assertStringContainsString($secretoPrueba, $crudoGuardado->contenido);
            $this->assertSame(hash('sha256', $crudoGuardado->contenido), $crudoGuardado->hash_contenido_sha256);

            $this->assertDatabaseHas('mensajero_eventos_crudos_whatsapp', [
                'id' => $captura->eventoCrudoId,
                'estado_procesamiento' => 'capturado',
            ]);
            $this->assertDatabaseMissing('mensajero_mensajes_whatsapp', [
                'evento_crudo_id' => $captura->eventoCrudoId,
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_separa_visualmente_grupos_por_proveedor_y_filtra_sin_cambiar_autorizaciones(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(5));
            $conexionRecolector = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'origen-recolector-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $conexionPasarela = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'origen-pasarela-'.$sufijo,
                'proveedor' => 'pasarela_integracion',
                'estado' => 'conectado',
            ]);

            $grupoRecolector = ChatMonitoreado::query()->create([
                'conexion_id' => $conexionRecolector->id,
                'id_chat_proveedor' => 'recolector-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Captura recolector '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
            ]);
            $grupoPasarela = ChatMonitoreado::query()->create([
                'conexion_id' => $conexionPasarela->id,
                'id_chat_proveedor' => 'pasarela-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Captura pasarela '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('/mensajes')
                ->assertOk()
                ->assertSee('Explorar conversaciones')
                ->assertSee('Conversaciones recientes')
                ->assertDontSee('Temas clave del día')
                ->assertSee('Captura recolector '.$sufijo)
                ->assertSee('Captura pasarela '.$sufijo)
                ->assertSee('Izquierda: eventos interpretados · Derecha: mensajes originales')
                ->assertSee('Carrusel de mensajes de Captura recolector '.$sufijo)
                ->assertSee('Carrusel de mensajes de Captura pasarela '.$sufijo)
                ->assertSee('data-grupo-id="'.$grupoRecolector->id.'"', false)
                ->assertSee('data-grupo-id="'.$grupoPasarela->id.'"', false)
                ->assertDontSee('carrusel-grupos-vertical', false);

            // Sin mensajes capturados hoy, el filtro debe omitir los dos.
            $this->get('/mensajes?actividad=hoy')
                ->assertOk()
                ->assertDontSee('Captura recolector '.$sufijo)
                ->assertDontSee('Captura pasarela '.$sufijo);

            $this->get('/grupos?origen=recolector')
                ->assertOk()
                ->assertSee('Captura recolector '.$sufijo)
                ->assertDontSee('Captura pasarela '.$sufijo);

            $this->get('/grupos?origen=otras')
                ->assertOk()
                ->assertSee('Captura pasarela '.$sufijo)
                ->assertDontSee('Captura recolector '.$sufijo);

            $this->assertTrue($grupoRecolector->fresh()->es_monitoreado);
            $this->assertTrue($grupoPasarela->fresh()->es_monitoreado);
        } finally {
            DB::rollBack();
        }
    }

    public function test_interruptor_unico_apaga_y_reactiva_captura_y_tablero_con_auditoria(): void
    {
        DB::beginTransaction();

        try {
            config([
                'mensajero.gestion_local_grupos_habilitada' => true,
                'mensajero.autenticacion.modo' => 'deshabilitado',
                'mensajero.consulta_local_sin_autenticacion' => true,
            ]);

            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'estado-unico-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'estado-unico-'.$sufijo.'@g.us',
                'nombre_grupo' => 'Grupo estado único '.$sufijo,
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
                'es_visible_tablero' => true,
            ]);

            $this->get('/tablero')->assertOk()->assertSee('Grupo estado único '.$sufijo);
            $this->get('/mensajes')->assertOk()->assertSee('Grupo estado único '.$sufijo);

            $this->patch('/grupos/'.$grupo->id.'/monitoreo', [
                'es_monitoreado' => 0,
            ])->assertRedirect();

            $this->assertFalse($grupo->fresh()->es_monitoreado);
            $this->assertFalse($grupo->fresh()->es_visible_tablero);
            $this->get('/tablero')->assertOk()->assertDontSee('Grupo estado único '.$sufijo);
            $this->get('/mensajes')->assertOk()->assertDontSee('Grupo estado único '.$sufijo);
            $this->get('/grupos?estado=no_monitoreados')
                ->assertOk()
                ->assertSee('Grupo estado único '.$sufijo)
                ->assertSee('Deshabilitado');

            // El historial existente no se elimina. El acceso directo se
            // bloquea mientras la captura y visibilidad estén desactivadas.
            $this->get('/mensajes/grupos/'.$grupo->id)->assertNotFound();

            $rechazo = app(CapturarEventoWhatsappEntrante::class)->ejecutar(new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'desactivado-'.$sufijo,
                mensajeProveedorId: 'desactivado-'.$sufijo,
                contenidoCrudo: json_encode(['message' => ['conversation' => 'No capturar']], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC'),
                versionContrato: 'v3-recolector',
                remitenteProveedorId: '5219990000000@s.whatsapp.net',
            ));
            $this->assertNotSame('aceptado', $rechazo->estado);
            $this->assertSame(0, EventoCrudoWhatsapp::query()
                ->where('id_evento_externo', 'desactivado-'.$sufijo)->count());

            $this->patch('/grupos/'.$grupo->id.'/monitoreo', [
                'es_monitoreado' => 1,
            ])->assertRedirect();

            $this->assertTrue($grupo->fresh()->es_monitoreado);
            $this->assertTrue($grupo->fresh()->es_visible_tablero);
            $this->get('/tablero')->assertOk()->assertSee('Grupo estado único '.$sufijo);
            $this->get('/mensajes/grupos/'.$grupo->id)->assertOk();

            $this->assertSame(2, DB::table('mensajero_auditoria_visibilidad_grupos')
                ->where('chat_monitoreado_id', $grupo->id)->count());
            $this->assertSame(2, DB::table('mensajero_auditoria_monitoreo_chats')
                ->where('chat_monitoreado_id', $grupo->id)->count());

            // La antigua ruta que cambiaba solamente la vista ya no existe.
            $this->patch('/grupos/'.$grupo->id.'/visibilidad', [
                'es_visible_tablero' => 0,
            ])->assertNotFound();
        } finally {
            DB::rollBack();
        }
    }

    public function test_un_grupo_no_autorizado_no_expone_evidencia_previamente_capturada(): void
    {
        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'grupo-vista-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);

            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'grupo-privado-'.$sufijo.'@g.us',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => false,
                'es_activo' => true,
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('/mensajes/grupos/'.$grupo->id)
                ->assertNotFound();
        } finally {
            DB::rollBack();
        }
    }
}
