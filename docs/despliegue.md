# Instalación y operación

**IMPORTANTE — Rama PHP 7.4 de MILAC:** las instrucciones históricas que siguen
corresponden a la rama Laravel 13 / PHP 8.4 y NO deben ejecutarse sobre
`compatibilidad/php74-milac-20261027`. Para entregar esta rama al compañero
instalador consulte [ENTREGA-INSTALADOR-MILAC-PHP74.md](ENTREGA-INSTALADOR-MILAC-PHP74.md).
No se autoriza modificar Apache, Composer global, tablas corporativas,
permisos o trabajadores hasta contar con ventana y autorización.

Entorno objetivo: servidor propio de MILAC, PHP 8.4+, extensiones `sqlsrv`, `pdo_sqlsrv`, `fileinfo`, ODBC 18, Composer 2, SQL Server y Redis.

Mensajero usa una base SQL Server corporativa existente; no se crea una base productiva separada. Todos los objetos propios comienzan con `mensajero_`.

## Acceso temporal
`MENSAJERO_MODO_AUTENTICACION=deshabilitado` solo es válido detrás de controles de red internos. En ese modo `/tablero` funciona sin usuario, mientras `/estado` permanece prohibido. El diagnóstico administrativo puede ejecutarse con `php artisan mensajero:diagnostico`.

`local` existe para desarrollo/pruebas. `corporativo` está reservado a la identidad definitiva y falla cerrado.

## Producción
Servir únicamente `public/`, `APP_DEBUG=false`, SQL cifrado, Redis privado, permisos mínimos, archivos privados y registros sin contenido sensible. Antes de integrar, las migraciones deben certificarse en SQL Server `_test`. No vincular el número empresarial hasta superar una prueba controlada de la pasarela.


## Servicio continuo de análisis Gemini (piloto autorizado)

Estado confirmado: el 22/10/2026, el mensaje ficticio WhatsApp #74 del grupo interno 10020 produjo un resultado Gemini **pendiente_revision** en SQL Server y se visualizó en /mensajes/ia. **No certifica producción ni disponibilidad 24/7.**

El modo permanente es por **configuración privada en .env** y **procesos supervisados**, nunca activación global desde código ni secretos en GitHub. Instalar tras autorizar explícitamente transmisión de contenido, proyecto Google, condiciones de facturación y lista de grupos.

Para Windows POC, los scripts son scripts/activar-gemini.ps1 (configuración persistente, solo grupo seleccionado y fecha de corte actual) y scripts/ejecutar-gemini-windows.ps1 (trabajador IA aislado con certificado CA verificado). Además deben estar operativos el recolector, Redis, SQL Server, el programador y el trabajador de normalización; el script de Gemini no los levanta.

Para servidor MILAC **no usar artisan serve**: PHP-FPM + HTTPS y un supervisor (systemd o equivalente) para cada proceso. Crear exactamente una instancia del recolector y del programador, comenzar con **un** trabajador IA y supervisar los reinicios. Colocar clave de Google bajo permisos restringidos en .env o gestor privado de secretos. Mantener Redis accesible solo internamente y evitar duplicar workers IA al desplegar.

Tras cambios de .env: limpiar configuración PHP y reiniciar trabajadores para que relean política/credenciales. La detención segura requiere cortar el trabajador IA, desactivar las banderas de envío y reiniciar normalización; no borrar capturas SQL ni colas sin auditoría.

Antes de operar grupos reales: incorporar autenticación corporativa, límites monetarios, medición de cuota compartida, alertas, conciliación de publicaciones IA y revisión/aprobación humana auditada. La clave compartida puede afectar a otros endpoints del mismo proyecto Google.

Procedimientos, comandos, SSL y recuperación: [Operación permanente Gemini](operacion-gemini-permanente.md). Prompt y salida: [Canalización IA](canalizacion-ia.md).
