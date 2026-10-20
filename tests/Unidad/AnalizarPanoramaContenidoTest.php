<?php

namespace Tests\Unidad;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Mensajes\AnalizarPanoramaContenido;
use App\Services\Normalizacion\ExtraerContenidoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AnalizarPanoramaContenidoTest extends TestCase
{
    public function test_detecta_menciones_literales_sin_inventar_acuerdos(): void
    {
        $mensajes = new Collection([
            $this->evento(1, 10, 'La unidad TR-1156 llegó a entregar al cliente.', 'remitente-a'),
            $this->evento(2, 10, 'Quedamos en confirmar la descarga pendiente.', 'remitente-b'),
            $this->evento(3, 20, 'Recibido. Gracias.', 'remitente-b'),
            $this->evento(4, 20, 'Este mensaje no tiene un dato de importancia inferible.', null),
        ]);

        $resultado = (new AnalizarPanoramaContenido)->ejecutar($mensajes, new ExtraerContenidoWhatsapp);

        $this->assertSame(4, $resultado['mensajesRevisados']);
        $this->assertSame(2, $resultado['participantesMuestra']);
        $this->assertSame(1, $resultado['totalAcuerdos']);
        $this->assertSame(1, $resultado['totalRevision']);
        $this->assertSame(2, $resultado['acuerdos'][0]['evento_id']);
        $this->assertSame(2, $resultado['revision'][0]['evento_id']);
        $this->assertContains('Logística y entregas', array_column($resultado['temas'], 'nombre'));
        $this->assertContains('Unidades y operadores', array_column($resultado['temas'], 'nombre'));
        $this->assertSame(2, $resultado['remitentes'][0]['mensajes']);
        $this->assertSame('Persona sin identificar', $resultado['remitentes'][0]['nombre']);
    }

    public function test_ignora_texto_ilegible_y_no_usa_metadatos_secretos(): void
    {
        $evento = $this->evento(5, 20, 'Sin novedad.', 'remitente-oculto');
        $evento->contenido = '{"message":{"conversation":"Sin novedad.","messageContextInfo":{"secret":"pendiente urgente"}}}';
        $corrupto = $this->evento(6, 20, 'No válido', 'x');
        $corrupto->contenido = '{json-corrupto';

        $resultado = (new AnalizarPanoramaContenido)->ejecutar(
            new Collection([$evento, $corrupto]),
            new ExtraerContenidoWhatsapp,
        );

        $this->assertSame(0, $resultado['totalRevision']);
        $this->assertSame([], $resultado['temas']);
        $this->assertSame([], $resultado['acuerdos']);
    }

    private function evento(int $id, int $grupo, string $texto, ?string $remitente): EventoCrudoWhatsapp
    {
        return new EventoCrudoWhatsapp([
            'id' => $id,
            'conexion_id' => 7,
            'chat_monitoreado_id' => $grupo,
            'contenido' => json_encode(['message' => ['conversation' => $texto]], JSON_THROW_ON_ERROR),
            'recibido_en' => CarbonImmutable::parse('2026-10-20T16:00:00Z'),
            'id_remitente_proveedor' => $remitente,
        ]);
    }
}
