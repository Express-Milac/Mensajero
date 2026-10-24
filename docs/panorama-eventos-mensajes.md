# Panorama operativo por grupo: eventos (izquierda) y mensajes (derecha)

Actualización del 24/10/2026. Se aplica al Centro de mando (/tablero) y a /mensajes porque ambos comparten las tarjetas y su actualización en vivo.

## Presentación

Cada grupo monitorizado conserva una tarjeta común, con dos paneles independientes:

- IZQUIERDA: Eventos interpretados por Gemini. Título, categoría, resumen, hora local, vínculos al mensaje origen y a la bitácora completa. Últimos ocho acontecimientos ya registrados, sin aprobación humana.
- DERECHA: Mensajes originales. Remitente, hora, contenido reciente y acceso al mensaje. Últimos ocho mensajes. No se altera su almacenamiento.

Ambos paneles tienen desplazamiento vertical independiente y conservan su posición al actualizar. En pantallas estrechas la vista se apila (eventos primero, mensajes después).

Los filtros existentes por actividad y orden se aplican a los grupos. La búsqueda local incluye nombre de grupo, textos de mensajes y títulos/resúmenes de eventos visibles. Solo se consultan grupos monitorizados.

## Fotografías y documentos

Cada evento muestra contadores por tipo de fuente: fotos, documentos, videos y audios. Se muestran hasta dos miniaturas originales de fotografías si existe un archivo con estado verificado, y se enlazan por la ruta privada del visor original (que comprueba autorización, integridad SHA256 y tipo MIME). La indicación "+N fotos" incluye imágenes para las que no hay miniatura, de modo que siempre se reconoce su existencia.

Las fotografías vinculadas como posible_adjunta son asociaciones basadas en remitente y proximidad temporal, NO verificaciones semánticas. La interfaz lo aclara. El modelo actual Gemini interpreta textos y pies de multimedia, NO el contenido visual de las imágenes. Abrir el tablero no envía imágenes a Gemini.

## Seguridad y eficiencia

- Todo es lectura: no se publica Redis ni se llama a Google.
- Cada grupo debe estar activo, visible, monitorizado y autorizado para IA. Si no está autorizado para IA, podrá ver sus mensajes, pero no acontecimientos IA.
- Ocho acontecimientos por grupo autorizado y una consulta agregada de fuentes.
- Los archivos se sirven privadamente; nunca se publican rutas del disco.
- Sin validación humana, migraciones ni cambios de datos SQL Server.

## Verificación local

Desde raíz del proyecto:

    git fetch origin fase-2-gemini-eventos-operativos-20261021
    git restore --source=FETCH_HEAD --worktree -- scripts/actualizar-cara-operativa.ps1
    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\actualizar-cara-operativa.ps1
    php artisan test --filter=PanoramaEventosMensajesTest
    php artisan test --filter=OperacionAccesoTest
    php artisan view:cache
    php artisan route:clear

Abrir http://127.0.0.1:8000/tablero y actualizar con Ctrl+F5. En grupos con acontecimientos ya registrados aparecerá el panel Eventos; en los demás, la leyenda "Sin eventos interpretados de este grupo por ahora". Las miniaturas solo aparecen cuando los archivos originales están verificados y disponibles en el almacenamiento privado.

No ejecutar pruebas de integración SQL Server contra bases operativas; requieren una base aislada con sufijo _test.
