# Revisión de IAVE y GPSGestor

Revisión local inicial: 2026-10-10; ampliada el 2026-10-12 con Valera. Sin llamadas de login,
consultas SQL ni cambios en otros proyectos.

## Corrección tras revisar Valera

No es necesario obtener copias locales de settings.php/dependencies.php ni credenciales SQL
para continuar desarrollando la integración. La ausencia de esos archivos aquí no implica
que la integración en el servidor requiera provisionar otra conexión.

`../Valera/private/hoja-liquidacion-connection.php` implementa el mecanismo concreto:

1. Resuelve MilacServices mediante MML_MILACSERVICES_ROOT o directorio hermano.
2. Carga vendor/autoload.php y config/settings.php del servicio existente.
3. Construye Slim\\App, obtiene su contenedor e incluye config/dependencies.php.
4. Recupera dbLis como PDO y verifica SELECT DB_NAME() contra milacdb.
5. Evita cargar rutas o ejecutar app->run(); captura la salida de las fábricas y devuelve
   errores sanitizados para no revelar datos de conexión.

Su alternativa local Windows usa PDO_ODBC y DSN LIS101, con parámetros privados/variables
MML_LIS_*. No hay que copiar sus credenciales al repositorio de Mensajero. No confundir esta
alternativa de Valera con compatibilidad ODBC ya implementada en Laravel.

`../Valera/jobs/comprobar-conexion-liquidaciones.php` demuestra cómo validar la conexión
sin modificar registros. El bootstrap coincide con el job de GPSGestor ya revisado.

`../Valera/private/mml.php` define el login central Mml/api/login y la ruta Mml/api/sso.
`../Valera/login.php` mantiene la sesión en servidor. Las referencias antiguas a Valera/api/login
en README no sustituyen al endpoint que utiliza actualmente el código.

### Aplicación a Mensajero

Continuar con adaptador de autenticación central y autorización propia, sin exigir previamente
SSH, secretos o una base nueva. La identidad del administrador inicial puede configurarse al
instalar, sin bloquear el desarrollo. Base propia es una opción, no prerrequisito impuesto.

Para reutilizar dbLis, preparar integración dentro del runtime de MilacServices o un puente
interno autenticado. No incluir directamente su autoloader Slim y dependencias antiguas en
Laravel 13 sin comprobar compatibilidad: el ejemplo Valera es PHP independiente. Mantener
Laravel/PHP 8.4 según el requerimiento original y aislar el componente compatible con el
servidor existente. Definir operaciones específicas del puente, nunca un endpoint de SQL libre.

Las pruebas de contrato pueden hacerse localmente con dobles. La disponibilidad real de
dbLis y la compatibilidad de runtime solo se certificarán donde exista MilacServices.
No se ha implementado ni desplegado todavía este puente.

## Evidencia de código

- `../iave/src/pages/Login.tsx`: usuario y contraseña; imagen corporativa y formulario dividido.
- `../iave/src/router.tsx:68`: POST JSON `{name,password}` a `/api/loginv2` del servicio IAVE;
  maneja BLOCKED, NO_ACCESS, EXITO y respuesta array; conserva datos de presentación en sessionStorage.
- `../GPSGestor/private/gps.php:136`: intenta IAVE y después VALERA; exige `autorizado`,
  elimina campos sensibles de la respuesta y abre sesión con rol pendiente.
- `../GPSGestor/private/gps.php:227`: IAVE recibe JSON; VALERA form-urlencoded. URLs:
  `https://track.milac.com.mx/MilacServices/Iave/api/loginv2` y
  `https://track.milac.com.mx/MilacServices/Mml/api/login`.
- Después del login central, GPSGestor consulta `/access/me` con HMAC. No basta con tener
  credenciales centrales válidas para entrar al módulo.
- `../GPSGestor/MilacServices/app/Controllers/GPSGestor/AbstractGpsController.php`:
  obtiene PDO de `dbLis`; verifica identidad central activa y `accesos_gps_sis` activo.
  `id_usuario` es identificador alfanumérico, no necesariamente email ni entero.
- `../GPSGestor/MilacServices/app/Controllers/GPSGestor/AccessController.php`: autorización
  del módulo separada del catálogo de usuarios; roles admin/responsible.
- `../GPSGestor/MilacServices/bin/gps-warehouse-sync.php:5`: carga las conexiones desde
  `/var/www/html/MilacServices/config/settings.php` y `config/dependencies.php` en el servidor.

## SQL Server

GPSGestor no conecta a SQL desde el navegador. Su frontend PHP consume MilacServices;
Slim 3 obtiene PDO del contenedor. El código identifica `dbLis` como conexión usada para
inventario y tablas operativas; la guía técnica señala `milacdb`. `dbMilac` se usa para otras
consultas empresariales. Son nombres de servicios: no inferir servidor o base solo del alias.

No se localizaron settings.php/dependencies.php fuera de dependencias/releases en el árbol
local examinado. Por tanto se conoce el mecanismo y la ubicación de configuración, pero
no se verificaron host, puerto, credenciales, opciones TLS ni conectividad. No se copiaron secretos.

## Implicaciones para Mensajero

**Problema:** la base inicial usa usuarios/password locales por email; no coincide con el
patrón de identidad corporativa observado.

**Opciones:** mantener identidad aislada; reutilizar login central mediante adaptador servidor;
o agregar endpoint dedicado Mensajero en MilacServices.

**Decisión recomendada:** identidad central, sesión Laravel y autorización propia de Mensajero,
con roles administrador/supervisor/visualizador y permisos por chat. Guardar identificador
central y autorizaciones, sin copiar contraseñas. Mantener SQL operativo configurable y
aislado de tablas corporativas. No conceder permisos por el rol devuelto por IAVE/VALERA.

**Ventajas:** una identidad corporativa, revocación y trazabilidad coherentes.
**Desventajas:** dependencia del servicio de autenticación y necesidad de validar estado central.
**Riesgos:** reutilizar IAVE/VALERA puede exigir permisos de esos módulos. Un endpoint dedicado
evitaría ese acoplamiento. Debe verificarse el contrato del backend antes de adoptarlo.

No copiar el acceso de vista previa encontrado en GPSGestor. sessionStorage de IAVE no es
prueba de identidad para el backend. No persistir respuesta de autenticación completa ni
contraseña. La sesión solo queda autorizada al completar validación central y local.

La guía existente describe PHP 7.4.13 en el servidor compartido (dato documental, no verificado
en vivo). Laravel 13/PHP 8.4 no puede alojarse en ese mismo runtime. Mantener runtime separado
para Mensajero, conservando los servicios existentes; no actualizar PHP global del servidor.

## Estado

Esta revisión documenta el ajuste necesario; no reemplaza todavía el login local implementado.
Antes de habilitar autenticación corporativa: implementar adaptador y pruebas de bloqueo,
respuesta inválida, timeout, falta/revocación de permiso y sesión; confirmar endpoint para
Mensajero y recuperar configuración de conexión por canal autorizado. No ejecutar migraciones
de Laravel sobre milacdb por asumir que es la base de destino.
