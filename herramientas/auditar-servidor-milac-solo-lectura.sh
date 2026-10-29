#!/usr/bin/env bash
# Auditoria previa al despliegue de Mensajero MILAC (sin privilegios).
# SOLO LECTURA: no inicia Laravel, no consulta BD, no usa Redis, no lee .env,
# no cambia Apache, no instala paquetes ni escribe archivos.
set -u

echo '=== INVENTARIO PREVIO SERVIDOR MILAC: SOLO LECTURA ==='

if [ -r /etc/os-release ]; then
    sed -n 's/^PRETTY_NAME=//p' /etc/os-release | tr -d '"'
else
    echo 'SISTEMA: no_disponible'
fi

if command -v php >/dev/null 2>&1; then
    version="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
    printf 'PHP_CLI: %s\n' "$version"

    for extension in PDO pdo_sqlsrv sqlsrv mbstring curl openssl xml zip intl fileinfo bcmath ctype tokenizer json; do
        if php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$extension" >/dev/null 2>&1; then
            printf 'EXTENSION_CLI_%s: SI\n' "$extension"
        else
            printf 'EXTENSION_CLI_%s: NO\n' "$extension"
        fi
    done
else
    echo 'PHP_CLI: no_disponible'
fi

if command -v composer >/dev/null 2>&1; then
    composer --version --no-ansi 2>/dev/null | head -n 1
else
    echo 'COMPOSER_ACTUAL: no_disponible'
fi

if [ -L /etc/apache2/mods-enabled/php7.4.load ] \
    && [ -r /etc/apache2/mods-enabled/php7.4.load ] \
    && grep -q 'libphp7.4.so' /etc/apache2/mods-enabled/php7.4.load; then
    echo 'MOD_PHP74_CONFIGURADO: SI'
else
    echo 'MOD_PHP74_CONFIGURADO: NO_O_INDETERMINADO'
fi

if [ -e /etc/apache2/mods-enabled/rewrite.load ]; then
    echo 'MOD_REWRITE_CONFIGURADO: SI'
else
    echo 'MOD_REWRITE_CONFIGURADO: NO_O_INDETERMINADO'
fi

if command -v apache2ctl >/dev/null 2>&1; then
    # No divulgar rutas de llaves/certificados. Sin permisos el error SSL puede ser falso.
    if apache2ctl -t >/dev/null 2>&1; then
        echo 'SINTAXIS_APACHE_USUARIO_ACTUAL: OK'
    else
        echo 'SINTAXIS_APACHE_USUARIO_ACTUAL: NO_CONCLUYENTE_REQUIERE_ADMIN'
    fi
else
    echo 'SINTAXIS_APACHE_USUARIO_ACTUAL: comando_no_disponible'
fi

ruta_publicada='/var/www/html/Mensajero'
raiz_esperada='/home/sistemas/laravelProjects/Mensajero/public'
if [ -e "$ruta_publicada" ] || [ -L "$ruta_publicada" ]; then
    resuelta="$(readlink -f "$ruta_publicada" 2>/dev/null || true)"
    if [ "$resuelta" = "$raiz_esperada" ]; then
        echo 'RUTA_PUBLICADA_ES_PUBLIC: SI'
    else
        echo 'RUTA_PUBLICADA_ES_PUBLIC: NO_VERIFICADA'
    fi
else
    echo 'RUTA_PUBLICADA_ES_PUBLIC: SIN_ENLACE'
fi

for ruta in \
    '/home/sistemas/laravelProjects/Mensajero' \
    '/home/sistemas/laravelProjects/Mensajero/public' \
    '/home/sistemas/laravelProjects/Mensajero/storage' \
    '/home/sistemas/laravelProjects/Mensajero/bootstrap/cache'; do
    caso=''
    case "$ruta" in
        */Mensajero) caso='DIRECTORIO_APLICACION' ;;
        */public) caso='DIRECTORIO_PUBLICO' ;;
        */storage) caso='DIRECTORIO_STORAGE' ;;
        */bootstrap/cache) caso='DIRECTORIO_CACHE' ;;
    esac
    if [ -d "$ruta" ]; then
        if [ -r "$ruta" ] && [ -x "$ruta" ]; then
            printf '%s: PRESENTE_Y_LEGIBLE_USUARIO_ACTUAL\n' "$caso"
        else
            printf '%s: PRESENTE_PERO_SIN_LECTURA_USUARIO_ACTUAL\n' "$caso"
        fi
    else
        printf '%s: AUSENTE_O_NO_ACCESIBLE\n' "$caso"
    fi
done

echo 'PHP_WEB_REAL: PENDIENTE_VALIDAR_EN_VHOST_PRIVADO'
echo 'EXTENSIONES_PHP_WEB: PENDIENTE_VALIDAR_EN_VHOST_PRIVADO'
echo 'TLS_SQL_CORPORATIVO: PENDIENTE_VALIDACION_CERTIFICADO'
echo 'ACCESO_WEB_AUTENTICADO: PENDIENTE_COMPROBACION'
echo 'MIGRACIONES_EJECUTADAS: NO'
echo 'SERVICIOS_MODIFICADOS: NO'
echo 'FIN_AUDITORIA_SOLO_LECTURA'
