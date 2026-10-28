<?php

/**
 * Crea una BASE NUEVA y vacia, exclusivamente en SQL Server Docker local,
 * para las pruebas SQL PHP 7.4. No elimina bases ni usa el esquema existente.
 * Solo ejecutar desde el orquestador PowerShell que verifica el mapeo Docker.
 */
if (PHP_VERSION_ID !== 70413 || ! extension_loaded('pdo_sqlsrv')) {
    fwrite(STDERR, "BLOQUEADO: requiere PHP 7.4.13 con PDO_SQLSRV.\n");
    exit(1);
}

$host = getenv('DB_HOST');
$puerto = getenv('DB_PORT');
$nombreFuente = getenv('DB_DATABASE');
$usuario = getenv('DB_USERNAME');
$clave = getenv('DB_PASSWORD');

if (getenv('MENSAJERO_SQL_DOCKER_VERIFICADO') !== 'mensajero-sqlserver'
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlsrv'
    || ! in_array(getenv('DB_URL'), ['', false], true)
    || ! in_array($host, ['127.0.0.1', 'localhost'], true)
    || ! is_string($puerto) || preg_match('/\A[0-9]{1,5}\z/D', $puerto) !== 1
    || (int) $puerto < 1 || (int) $puerto > 65535
    || $nombreFuente !== 'mensajero_test'
    || ! is_string($usuario) || $usuario === ''
    || ! is_string($clave) || $clave === ''
    || getenv('DB_ENCRYPT') !== 'yes'
    || getenv('DB_TRUST_SERVER_CERTIFICATE') !== 'true'
    || getenv('IA_HABILITADA') !== 'false'
    || getenv('MENSAJERO_IA_ENVIO_AUTORIZADO') !== 'false'
    || getenv('MENSAJERO_IA_LOTES_HABILITADOS') !== 'false'
    || getenv('QUEUE_CONNECTION') !== 'sync') {
    fwrite(STDERR, "BLOQUEADO: no se verifico el ambiente Docker de pruebas.\n");
    exit(1);
}

$nombreNuevo = 'mensajero_php74_'.bin2hex(random_bytes(5)).'_test';
$dsnBase = 'sqlsrv:Server='.$host.','.$puerto.
    ';Database=master;Encrypt=yes;TrustServerCertificate=1;LoginTimeout=8';

try {
    $pdo = new PDO($dsnBase, $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $fisica = $pdo->query('SELECT DB_NAME()')->fetchColumn();
    if ($fisica !== 'master') {
        throw new RuntimeException('No se llego a la base master de Docker.');
    }

    // La base nueva tiene un nombre generado internamente, no del usuario.
    // Si por improbable colision de identificador existiera, abortar.
    $busqueda = $pdo->prepare('SELECT COUNT(*) FROM sys.databases WHERE name = ?');
    $busqueda->execute([$nombreNuevo]);
    if ((int) $busqueda->fetchColumn() !== 0) {
        throw new RuntimeException('Colision en el nombre de base desechable.');
    }

    $pdo->exec('CREATE DATABASE ['.$nombreNuevo.']');

    $dsnNueva = 'sqlsrv:Server='.$host.','.$puerto.
        ';Database='.$nombreNuevo.';Encrypt=yes;TrustServerCertificate=1;LoginTimeout=8';
    $nueva = new PDO($dsnNueva, $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    if ($nueva->query('SELECT DB_NAME()')->fetchColumn() !== $nombreNuevo
        || (int) $nueva->query('SELECT COUNT(*) FROM sys.tables')->fetchColumn() !== 0) {
        throw new RuntimeException('La nueva base no esta vacia o no coincide.');
    }

    echo 'BASE_DESECHABLE_CREADA: '.$nombreNuevo.PHP_EOL;
    echo "BASE_VERIFICADA_VACIA: SI\n";
    echo "NO_SE_MODIFICO_MENSAJERO_TEST: SI\n";
} catch (Throwable $error) {
    $tipo = get_class($error);
    $estado = 'no_disponible';
    $codigo = 'no_disponible';
    if ($error instanceof PDOException && isset($error->errorInfo)
        && is_array($error->errorInfo)) {
        if (isset($error->errorInfo[0])
            && preg_match('/\A[A-Z0-9]{5}\z/D', (string) $error->errorInfo[0])) {
            $estado = (string) $error->errorInfo[0];
        }
        if (isset($error->errorInfo[1])
            && preg_match('/\A-?[0-9]{1,11}\z/D', (string) $error->errorInfo[1])) {
            $codigo = (string) $error->errorInfo[1];
        }
    }
    // No revelar DSN, contrasenas, rutas de disco ni texto de error.
    fwrite(STDERR, 'BLOQUEADO: crear base de pruebas falló. Tipo='.$tipo.
        ' SQLSTATE='.$estado.' Codigo='.$codigo.PHP_EOL);
    exit(1);
}
