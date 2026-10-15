# Almacenamiento de archivos

Fotografías, videos, audios, documentos y demás archivos no se guardan en SQL Server. La base conserva metadatos, disco lógico y ruta relativa.

## Primera etapa

El disco `mensajero_local` usa Flysystem y apunta por defecto a `storage/app/mensajero`. La raíz puede cambiar mediante `MENSAJERO_RUTA_ALMACENAMIENTO` sin modificar controladores ni servicios de dominio.

Ejemplo de referencia persistida:

```text
disco = mensajero_local
ruta = grupos/{id}/2026/10/15/imagenes/{archivo}
```

## Reglas

- Nunca persistir como única referencia una ruta física absoluta.
- Generar nombres seguros; no confiar en nombres enviados por usuarios.
- Validar MIME, tamaño y checksum.
- Mantener archivos privados.
- Descargar multimedia en trabajos asíncronos, no dentro de la recepción principal.
- Preparar migración futura a volumen dedicado, NAS, MinIO o almacenamiento compatible con S3.
- Salud del sistema debe validar lectura, escritura, permisos y espacio libre.

El archivo original se conserva aunque posteriormente exista transcripción o interpretación por IA.
