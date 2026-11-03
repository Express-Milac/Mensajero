<?php

namespace App\Logging;

use DateTimeInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use RuntimeException;

/**
 * Un solo archivo JSONL: maximo de eventos y bytes bajo bloqueo entre procesos.
 *
 * El archivo .bloqueo coordina todos los escritores; no contiene mensajes.
 * La compactacion escribe en un temporal y lo renombra atomicamente para no
 * exponer un laravel.log truncado si el proceso muere mientras compacta.
 */
final class ArchivoLimitadoHandler extends AbstractProcessingHandler
{
    private string $ruta;
    private int $maximoEventos;
    private int $maximoBytes;

    public function __construct(
        string $ruta,
        int $maximoEventos = 1000,
        int $maximoBytes = 5242880,
        $nivel = Logger::WARNING,
        bool $propagar = true
    ) {
        if ($maximoEventos < 1 || $maximoBytes < 1024) {
            throw new \InvalidArgumentException('Limites invalidos para registro acotado.');
        }

        $this->ruta = $ruta;
        $this->maximoEventos = $maximoEventos;
        $this->maximoBytes = $maximoBytes;

        parent::__construct($nivel, $propagar);

        $formato = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
        $formato->includeStacktraces(true);
        $this->setFormatter($formato);
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function write(array $record): void
    {
        $registro = rtrim((string) $record['formatted'], "\r\n")."\n";

        // Impedir que una excepcion descomunal ocupe ella sola todo el archivo.
        // La version abreviada conserva fecha, nivel, mensaje y tamano original.
        if (strlen($registro) > min(65536, $this->maximoBytes)) {
            $fecha = $record['datetime'] ?? null;
            $abreviado = [
                'datetime' => $fecha instanceof DateTimeInterface ? $fecha->format(DATE_ATOM) : null,
                'channel' => (string) ($record['channel'] ?? 'mensajero'),
                'level_name' => (string) ($record['level_name'] ?? 'WARNING'),
                'message' => substr((string) ($record['message'] ?? ''), 0, 4096),
                'registro_abreviado' => true,
                'bytes_originales' => strlen($registro),
            ];
            $registro = json_encode(
                $abreviado,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            )."\n";
        }

        if (strlen($registro) > $this->maximoBytes) {
            throw new RuntimeException('El registro individual excede el limite del archivo.');
        }

        $bloqueoRuta = $this->ruta.'.bloqueo';
        $bloqueo = @fopen($bloqueoRuta, 'c+b');
        if ($bloqueo === false) {
            throw new RuntimeException('No se pudo abrir el bloqueo del registro Laravel.');
        }

        try {
            // El directorio storage/logs tiene herencia de grupo www-data.
            // Estos permisos permiten uso compartido por Apache y los workers.
            @chmod($bloqueoRuta, 0664);
            if (! flock($bloqueo, LOCK_EX)) {
                throw new RuntimeException('No fue posible bloquear el registro Laravel.');
            }

            try {
                $this->escribirBloqueado($registro);
            } finally {
                flock($bloqueo, LOCK_UN);
            }
        } finally {
            fclose($bloqueo);
        }
    }

    private function escribirBloqueado(string $registro): void
    {
        $existe = is_file($this->ruta);
        $tamano = $existe ? @filesize($this->ruta) : 0;
        if ($tamano === false) {
            throw new RuntimeException('No fue posible medir el archivo de registros.');
        }

        // Leer como maximo los ultimos 5 MB; no cargar registros historicos
        // enormes en RAM si el archivo venia de una configuracion anterior.
        $contenido = '';
        $parcial = false;
        if ($existe) {
            $origen = @fopen($this->ruta, 'rb');
            if ($origen === false) {
                throw new RuntimeException('No fue posible leer los registros existentes.');
            }
            try {
                $inicio = max(0, $tamano - $this->maximoBytes);
                if ($inicio > 0) {
                    $parcial = true;
                    if (fseek($origen, $inicio) !== 0) {
                        throw new RuntimeException('No fue posible localizar la cola del registro.');
                    }
                }
                $contenido = stream_get_contents($origen);
                if ($contenido === false) {
                    throw new RuntimeException('No fue posible leer la cola del registro.');
                }
            } finally {
                fclose($origen);
            }
        }

        if ($parcial) {
            // El primer renglon puede estar cortado por empezar a media linea.
            $salto = strpos($contenido, "\n");
            $contenido = $salto === false ? '' : substr($contenido, $salto + 1);
        }

        $lineas = $contenido === ''
            ? []
            : array_values(array_filter(explode("\n", rtrim($contenido, "\n")), static function ($linea) {
                return $linea !== '';
            }));

        $lineas[] = rtrim($registro, "\n");
        $bytes = 0;
        foreach ($lineas as $linea) {
            $bytes += strlen($linea) + 1;
        }

        $recorte = false;
        while (count($lineas) > $this->maximoEventos || $bytes > $this->maximoBytes) {
            $primera = array_shift($lineas);
            $bytes -= strlen($primera) + 1;
            $recorte = true;
        }

        // Si no hay que compactar, escribir al final preserva la eficiencia.
        // Compactar siempre mediante archivo temporal + rename atomico.
        if (! $parcial && ! $recorte && $tamano + strlen($registro) <= $this->maximoBytes
            && ($contenido === '' || substr($contenido, -1) === "\n")) {
            $salida = @fopen($this->ruta, 'ab');
            if ($salida === false) {
                throw new RuntimeException('No fue posible escribir el registro Laravel.');
            }
            try {
                $this->escribirCompleto($salida, $registro);
                if (! fflush($salida)) {
                    throw new RuntimeException('No fue posible vaciar el buffer de registros.');
                }
                @chmod($this->ruta, 0664);
            } finally {
                fclose($salida);
            }
            return;
        }

        $temporal = @tempnam(dirname($this->ruta), '.laravel-limitado-');
        if ($temporal === false) {
            throw new RuntimeException('No fue posible crear el temporal de compactacion.');
        }

        try {
            $salida = @fopen($temporal, 'wb');
            if ($salida === false) {
                throw new RuntimeException('No fue posible abrir el temporal de compactacion.');
            }

            try {
                $this->escribirCompleto($salida, implode("\n", $lineas)."\n");
                if (! fflush($salida)) {
                    throw new RuntimeException('No fue posible completar el temporal de registros.');
                }
            } finally {
                fclose($salida);
            }

            if (! @chmod($temporal, 0664) || ! @rename($temporal, $this->ruta)) {
                throw new RuntimeException('No fue posible sustituir atomicamente el registro.');
            }
        } finally {
            if (is_file($temporal)) {
                @unlink($temporal);
            }
        }
    }

    /**
     * @param resource $archivo
     */
    private function escribirCompleto($archivo, string $contenido): void
    {
        $largo = strlen($contenido);
        $desplazamiento = 0;
        while ($desplazamiento < $largo) {
            $escritos = fwrite($archivo, substr($contenido, $desplazamiento));
            if ($escritos === false || $escritos === 0) {
                throw new RuntimeException('Escritura parcial del registro Laravel.');
            }
            $desplazamiento += $escritos;
        }
    }
}
