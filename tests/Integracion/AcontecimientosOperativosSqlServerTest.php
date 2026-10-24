<?php

namespace Tests\Integracion;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\ContactoWhatsapp;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Operacion\RegistrarAcontecimientoOperativo;
use App\Services\Operacion\VincularEvidenciasOperativas;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AcontecimientosOperativosSqlServerTest extends TestCase
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

    public function test_acontecimiento_solo_una_vez_y_adjuntos_tentativos_sin_modificar_originales(): void
    {
        DB::beginTransaction();
        try {
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'operacion-test-'.bin2hex(random_bytes(6)),
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'operacion-test-'.bin2hex(random_bytes(6)).'@g.us',
                'nombre_grupo' => 'Grupo operacional ficticio',
                'tipo_chat' => 'grupo',
                'es_activo' => true,
                'es_monitoreado' => true,
                'es_visible_tablero' => true,
            ]);
            config(['mensajero.ia.grupos_autorizados' => [(int) $grupo->id]]);

            $remitente = ContactoWhatsapp::query()->create([
                'conexion_id' => $conexion->id,
                'id_contacto_proveedor' => 'emisor-ficticio',
            ]);
            $ajeno = ContactoWhatsapp::query()->create([
                'conexion_id' => $conexion->id,
                'id_contacto_proveedor' => 'otro-emisor-ficticio',
            ]);

            $texto = $this->mensaje($conexion, $grupo, $remitente, 'texto', 'Entrega de unidad ficticia', '2026-10-23 16:00:00');
            $foto = $this->mensaje($conexion, $grupo, $remitente, 'foto', null, '2026-10-23 16:00:30');
            $fotoAjena = $this->mensaje($conexion, $grupo, $ajeno, 'foto', null, '2026-10-23 16:00:40');

            $analisisId = $this->analisis($grupo, $texto);
            $servicio = app(RegistrarAcontecimientoOperativo::class);
            $id = $servicio->desdeAnalisis($analisisId);

            $this->assertNotNull($id);
            $this->assertSame($id, $servicio->desdeAnalisis($analisisId));
            $this->assertSame(1, DB::table('mensajero_acontecimientos_operativos')->where('analisis_ia_id', $analisisId)->count());
            $this->assertDatabaseHas('mensajero_evidencias_acontecimientos', [
                'acontecimiento_id' => $id,
                'mensaje_whatsapp_id' => $texto->id,
                'vinculo' => 'sustento_ia',
            ]);
            $this->assertDatabaseHas('mensajero_evidencias_acontecimientos', [
                'acontecimiento_id' => $id,
                'mensaje_whatsapp_id' => $foto->id,
                'vinculo' => 'posible_adjunta',
            ]);
            $this->assertDatabaseMissing('mensajero_evidencias_acontecimientos', [
                'acontecimiento_id' => $id,
                'mensaje_whatsapp_id' => $fotoAjena->id,
            ]);

            $this->get('/operacion')
                ->assertOk()
                ->assertSee('Operación interpretada')
                ->assertSee('Entrega de unidad ficticia')
                ->assertSee('Posible adjunto')
                ->assertSee('Original #'.$foto->id);

            // Un segundo acontecimiento cercano del mismo emisor convierte
            // la posible asignación de la fotografía en ambigua: se retira.
            $otroTexto = $this->mensaje($conexion, $grupo, $remitente, 'texto', 'Otro reporte operativo', '2026-10-23 16:00:45');
            $otroId = $servicio->desdeAnalisis($this->analisis($grupo, $otroTexto));
            $this->assertNotNull($otroId);
            $this->assertDatabaseMissing('mensajero_evidencias_acontecimientos', [
                'acontecimiento_id' => $id,
                'mensaje_whatsapp_id' => $foto->id,
                'vinculo' => 'posible_adjunta',
            ]);
            $this->assertDatabaseMissing('mensajero_evidencias_acontecimientos', [
                'acontecimiento_id' => $otroId,
                'mensaje_whatsapp_id' => $foto->id,
                'vinculo' => 'posible_adjunta',
            ]);

            $this->assertSame('generado_ia', DB::table('mensajero_acontecimientos_operativos')
                ->where('id', $id)->value('estado'));
            $this->get('/operacion')
                ->assertOk()
                ->assertSee('Generado por IA')
                ->assertDontSee('Validar interpretación')
                ->assertDontSee('Descartar interpretación');
            $this->patch('/operacion/'.$id.'/revision', ['decision' => 'validado'])->assertNotFound();


            // El mensaje original y el archivo potencial permanecen intactos.
            $this->assertSame('foto', $foto->fresh()->tipo_mensaje);
            $this->assertNotNull($foto->fresh()->evento_crudo_id);
        } finally {
            DB::rollBack();
        }
    }

    private function mensaje(ConexionWhatsapp $conexion, ChatMonitoreado $grupo, ContactoWhatsapp $remitente, string $tipo, ?string $texto, string $fecha): MensajeWhatsapp
    {
        $externo = 'evidencia-'.bin2hex(random_bytes(8));
        $crudo = EventoCrudoWhatsapp::query()->create([
            'conexion_id' => $conexion->id,
            'chat_monitoreado_id' => $grupo->id,
            'id_evento_externo' => $externo,
            'clave_deduplicacion' => hash('sha256', $externo),
            'tipo_evento' => 'mensaje',
            'tipo_mensaje' => $tipo,
            'contenido' => json_encode(['message' => ['conversation' => $texto]], JSON_THROW_ON_ERROR),
            'hash_contenido_sha256' => hash('sha256', (string) $texto),
            'estado_procesamiento' => 'normalizado',
            'recibido_en' => $fecha,
        ]);

        return MensajeWhatsapp::query()->create([
            'conexion_id' => $conexion->id,
            'chat_monitoreado_id' => $grupo->id,
            'contacto_remitente_id' => $remitente->id,
            'evento_crudo_id' => $crudo->id,
            'id_mensaje_proveedor' => $externo,
            'tipo_mensaje' => $tipo,
            'contenido_texto' => $texto,
            'recibido_en' => $fecha,
            'estado_captura' => 'normalizado',
        ]);
    }

    private function analisis(ChatMonitoreado $grupo, MensajeWhatsapp $mensaje): int
    {
        return DB::table('mensajero_analisis_operativos_ia')->insertGetId([
            'mensaje_whatsapp_id' => $mensaje->id,
            'chat_monitoreado_id' => $grupo->id,
            'estado' => 'identificado_ia',
            'categoria' => 'logistica',
            'titulo' => (string) $mensaje->contenido_texto,
            'resumen' => 'Se reportó un acontecimiento, todavía sin confirmación independiente.',
            'evidencias_json' => json_encode([(int) $mensaje->id], JSON_THROW_ON_ERROR),
            'modelo' => 'modelo-ficticio',
            'hash_contexto' => hash('sha256', 'analisis-'.$mensaje->id),
            'creado_en' => '2026-10-23 16:01:00',
        ]);
    }
}
