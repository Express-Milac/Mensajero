# Regla permanente de interfaz: operaciones sin recargar la página

Esta regla rige todos los desarrollos nuevos y revisiones de Mensajero MILAC.

## Comportamiento obligatorio

- Acciones de usuario como activar/desactivar un grupo, guardar configuraciones, asignar remitentes, gestionar filtros, corregir registros o actualizar un estado NO deben provocar recarga completa, redirección ni pérdida de posición.
- Guardar mediante solicitud asíncrona (fetch/AJAX, fragmentos HTML o tecnología equivalente); responder con JSON o fragmento mínimo desde Laravel.
- Mostrar `Guardando...` y deshabilitar temporalmente controles para evitar múltiples peticiones.
- Actualizar únicamente el elemento afectado y los indicadores relacionados después de la confirmación del servidor. Nunca dar por guardado un cambio antes del éxito HTTP y de la transacción SQL Server.
- Ante fallo, conservar los valores previos, avisar dentro de la pantalla y permitir reintentar sin navegar.
- Mantener scroll, foco, texto escrito, filtros, posición en las tarjetas y selección actual.
- Conservar comprobación de permisos, CSRF, validaciones, auditoría, límites, integridad y protección de datos. La ausencia de JavaScript debe fallar de forma segura, no ejecutar accidentalmente una operación con navegación.
- La navegación voluntaria a otra página mediante enlaces sigue existiendo; la prohibición aplica a recargas como consecuencia de actualizar datos o interfaz.

## Primer módulo adaptado: Control de Gemini

`/configuracion/gemini` utiliza `public/js/control-gemini.js` y los controladores responden con JSON para peticiones `Accept: application/json`. El frontend se inicia con los botones deshabilitados hasta que JavaScript prepara las solicitudes; así no hay envíos tradicionales con recarga accidental. Laravel confirma el estado global y de cada grupo antes de que el navegador cambie el texto a «Activo», «Pausado» o «Apagado». No se cambia el proceso recolector ni se llama a Google desde la interfaz.

Los formularios conservaron la lógica del backend para compatibilidad con clientes y pruebas antiguas, pero la interfaz normal usa solo solicitudes asíncronas.

## Validación para cada cambio futuro

Se debe comprobar que:
1. No exista `window.location.reload()`, `location.assign()`, `location.replace()` ni redirección automática después de un guardado.
2. Errores 422/403/419/500 se expliquen en el mismo contexto sin recargas.
3. El servidor no entregue 302 al cliente AJAX tras guardado correcto.
4. El estado no cambie visualmente antes de una respuesta válida.
5. La posición vertical y la entrada del usuario permanezcan estables.
6. Las pruebas y seguridad de servidor sigan funcionando.

Esta política no implica hacer despliegues de archivos nuevos sin actualizar assets en el navegador inicial. Las operaciones diarias posteriores a cargar una versión deben funcionar en caliente, sin recarga de página por cada acción.
