# Storage privado y portable

Flysystem mediante Storage::disk(config('filesystems.default')). Disco inicial
mensajero_local, root configurable con MENSAJERO_STORAGE_PATH. Sin storage:link para evidencia.
Guardar disk y ruta relativa, nunca ruta física como única referencia. Clave generada:
groups/{id}/{YYYY}/{MM}/{DD}/{media-id}.{ext-validada}. No usar filename del emisor como ruta.

Job: revalidar chat autorizado; descargar stream con límites de bytes y tiempo; detectar MIME
real (fileinfo), comparar allowlist de tipos, checksum SHA256; escribir archivo temporal,
publicar a clave definitiva y finalmente marcar ready. Reintento comprueba checksum y no
duplica archivos. Un fallo de SQL tras escribir requiere reconciliación de huérfanos; no
borrar automáticamente archivos cuya relación esté en duda. Documentos activos se descargan
como attachment; previews seguros y análisis antivirus se incorporan en milestone 6.

Acceso mediante controller autenticado y policy del chat; no URLs públicas permanentes.
El archivo original nunca se reemplaza por thumbnail o transcripción. Limitar rangos y
streaming para video. Alertar inmediatamente fallos de lectura/escritura y umbral de espacio.

Cambio de volumen: pausar descargas, copiar conservando rutas, verificar conteo/checksum,
cambiar root configurado, probar lectura y restaurar workers. Para cambiar nombre de disk,
mantener el anterior resoluble y migrar registros solo tras verificar copia; cambiar el default
no migra archivos existentes. Backups independientes y pruebas de restauración.

Pruebas previstas: permisos, disco lleno, duplicado, descarga interrumpida, MIME incorrecto,
tamaño excesivo, cambio de disco, URL/path malicioso y acceso no autorizado.
