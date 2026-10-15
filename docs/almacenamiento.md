# Almacenamiento privado y portable

El disco inicial es `mensajero_local`. Su raíz se configura con `MENSAJERO_RUTA_ALMACENAMIENTO`; si queda vacía se utiliza `storage/app/mensajero`.

SQL Server solo guarda metadatos y rutas relativas. Fotos, video, audio y documentos viven en el almacenamiento privado. No se crean enlaces públicos permanentes para la evidencia.

La descarga multimedia futura deberá revalidar que el chat siga autorizado, limitar tamaño/tiempo, detectar el tipo real con `fileinfo`, calcular SHA-256, escribir de forma temporal y publicar de manera idempotente.

Para mover el volumen: pausar descargas, copiar conservando rutas, verificar conteos y sumas, cambiar la raíz configurada y probar lectura antes de reactivar trabajadores. Las copias de seguridad de SQL Server y archivos deben coordinarse y probar restauración.
