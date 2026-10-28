<?php

/**
 * Diagnóstico estrictamente de solo lectura del PHP 7.4 y SQL Server de test.
 * No ejecuta migraciones, no escribe datos ni inicia colas o envíos HTTP.
 */
$base = dirname(__DIR__);

if (PHP_VERSION_ID !== 70413) {
    fwrite(STDERR, 'BLOQUEADO: este diagnóstico exige PHP 7.4.13 real.'.PHP_EOL);
    exit(1);
}

if (! extension_loaded('pdo_sqlsrv') || ! in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, 'BLOQUEADO: PHP 7.4.13 no tiene cargado PDO_SQLSRV.'.PHP_EOL);
    exit(1);
}

if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlsrv'
    || getenv('DB_DATABASE') !== 'mensajero_integracion_test'
    || ! in_array(getenv('DB_URL'), ['', false], true)
    || getenv('IA_HABILITADA') !== 'false'
    || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false'
    || getenv('MENSAJERO_IA_LOTES_HABILITADOS') !== 'false'
    || getenv('QUEUE_CONNECTION') !== 'sync') {
    fwrite(STDERR, 'BLOQUEADO: variables del proceso no corresponden al ensayo aislado.'.PHP_EOL);
    exit(1);
}

if (is_file($base.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, 'BLOQUEADO: hay configuracion Laravel en cache.'.PHP_EOL);
    exit(1);
}

if (! is_file($base.'/vendor/autoload.php')) {
    fwrite(STDERR, 'BLOQUEADO: Composer vendor no existe en este clon.'.PHP_EOL);
    exit(1);
}

$etapa = 'inicio_laravel';

try {
    require $base.'/vendor/autoload.php';
    $app = require $base.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if (! $app->environment('testing')
        || config('database.default') !== 'sqlsrv'
        || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
        || config('mensajero.ia.habilitada') !== false
        || config('mensajero.ia.envio_autorizado') !== false
        || config('mensajero.ia.lotes_habilitados') !== false) {
        throw new RuntimeException('La configuracion efectiva no es segura.');
    }

    // Mostrar exclusivamente la politica TLS EFECTIVA, nunca el DSN,
    // servidor, usuario, contraseña u otros valores arbitrarios.
    $encriptar = config('database.connections.sqlsrv.encrypt');
    $confiar = config('database.connections.sqlsrv.trust_server_certificate');

    $normalizarTls = static function ($valor): string {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        $normalizado = strtolower(trim((string) $valor));
        return in_array($normalizado, ['yes', 'no', 'true', 'false', '1', '0', 'strict'], true)
            ? $normalizado : 'no_reconocido';
    };

    echo 'CONFIG_TLS_ENCRYPT: '.$normalizarTls($encriptar).PHP_EOL;
    echo 'CONFIG_TLS_TRUST_SERVER_CERTIFICATE: '.$normalizarTls($confiar).PHP_EOL;
    echo 'TIPO_TLS_TRUST: '.gettype($confiar).PHP_EOL;

    $etapa = 'crear_conexion';
    $conexion = Illuminate\Support\Facades\DB::connection('sqlsrv');
    if ($conexion->getDriverName() !== 'sqlsrv') {
        throw new RuntimeException('El controlador de SQL Server es incorrecto.');
    }

    $etapa = 'abrir_conexion_sql';
    $conexion->getPdo();

    // ÚNICAS operaciones SQL: dos SELECT de solo lectura.
    $etapa = 'consultar_nombre_fisico';
    $fisica = $conexion->selectOne('SELECT DB_NAME() AS nombre');
    if (! isset($fisica->nombre) || $fisica->nombre !== 'mensajero_integracion_test') {
        throw new RuntimeException('La base SQL FISICA no es la base de integración.');
    }

    $etapa = 'consultar_catalogo_tablas';
    $tablas = $conexion->selectOne(
        "SELECT COUNT(*) AS cantidad FROM sys.tables WHERE name LIKE 'mensajero[_]%'"
    );
    echo 'PHP_VERIFICADO: '.PHP_VERSION.PHP_EOL;
    echo 'BASE_SQL_FISICA_VERIFICADA: mensajero_integracion_test'.PHP_EOL;
    echo 'TABLAS_MENSAJERO_EN_BASE_TEST: '.(int) $tablas->cantidad.PHP_EOL;
    echo 'DIAGNOSTICO_SQL74_SOLO_LECTURA_APROBADO'.PHP_EOL;
} catch (Throwable $error) {
    // Clasificacion SEGURA: nunca mostrar getMessage(), errorInfo[2],
    // DSN, servidor, usuario, contraseña, SQL ni credenciales.
    $sqlEstado = 'NO_DISPONIBLE';
    $codigoNativo = 'NO_DISPONIBLE';
    $clasificacion = 'no_identificado';
    $causa = $error;

    for ($intento = 0; $intento < 5 && $causa instanceof Throwable; $intento++) {
        if ($causa instanceof PDOException && isset($causa->errorInfo)
            && is_array($causa->errorInfo)) {
            $info = $causa->errorInfo;

            if (isset($info[0]) && is_string($info[0])
                && preg_match('/\\A[A-Z0-9]{5}\\z/D', $info[0])) {
                $sqlEstado = $info[0];
            }

            if (isset($info[1]) && (is_int($info[1]) ||
                (is_string($info[1]) && preg_match('/\\A-?[0-9]{1,11}\\z/D', $info[1])))) {
                $codigoNativo = (string) $info[1];
            }
        }

        // Solo inspeccionar internamente indicadores conocidos,
        // sin volver a imprimir texto potencialmente sensible.
        $detalle = strtolower($causa->getMessage());
        if (strpos($detalle, 'certificate') !== false
            || strpos($detalle, 'certificado') !== false
            || strpos($detalle, 'ssl provider') !== false
            || strpos($detalle, 'tls handshake') !== false) {
            $clasificacion = 'tls_o_certificado';
        } elseif (strpos($detalle, 'login failed') !== false
            || strpos($detalle, 'inicio de sesion') !== false
            || strpos($detalle, 'authentication failed') !== false) {
            $clasificacion = 'autenticacion';
        } elseif (strpos($detalle, 'cannot open database') !== false
            || strpos($detalle, 'no se puede abrir la base') !== false) {
            $clasificacion = 'base_inaccesible';
        } elseif (strpos($detalle, 'timeout') !== false
            || strpos($detalle, 'timed out') !== false
            || strpos($detalle, 'tiempo de espera') !== false) {
            $clasificacion = 'red_o_tiempo_espera';
        } elseif (strpos($detalle, 'data source name not found') !== false
            || strpos($detalle, 'driver not found') !== false) {
            $clasificacion = 'controlador_odbc';
        }

        $causa = $causa->getPrevious();
    }

    // El código SQLSTATE identifica el subsistema aunque no haya
    // un texto reconocible; no se adivina la causa final.
    fwrite(STDERR, 'BLOQUEADO_SQL74'.PHP_EOL);
    fwrite(STDERR, 'ETAPA: '.$etapa.PHP_EOL);
    fwrite(STDERR, 'TIPO: '.get_class($error).PHP_EOL);
    fwrite(STDERR, 'SQLSTATE: '.$sqlEstado.PHP_EOL);
    fwrite(STDERR, 'CODIGO_NATIVO: '.$codigoNativo.PHP_EOL);
    fwrite(STDERR, 'CATEGORIA_ORIENTATIVA: '.$clasificacion.PHP_EOL);
    fwrite(STDERR, 'Se omitieron todos los textos de error y datos de conexion.'.PHP_EOL);
    exit(1);
}
