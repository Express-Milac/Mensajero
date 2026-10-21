# Mensajero MILAC — tablero con avisos en vivo

## Flujo

1. Baileys recibe el mensaje de un grupo permitido y lo registra en su cola durable.
2. La pasarela Laravel comprueba la firma, los filtros y la deduplicación.
3. SQL Server confirma la transacción de captura.
4. Laravel envía un aviso firmado (solo \`grupo_id\` y \`evento_id\`) al servicio SSE del recolector, en \`127.0.0.1:8765\`.
5. El navegador recibe el aviso por EventSource, consulta a Laravel el fragmento autorizado y anima la inserción de mensajes nuevos. No se recarga la página.

**Importante:** el tablero presenta solo mensajes **confirmados por SQL Server**. El tiempo desde la recepción de WhatsApp hasta ese punto depende de la conexión y de la cola durable. El aviso no contiene texto, números ni archivos. Las consultas de datos conservan los permisos, filtros y la política de evidencia existentes.

## Entorno local

En \`.env\` de Laravel:

\`\`\`dotenv
MENSAJERO_TIEMPO_REAL_HABILITADO=true
MENSAJERO_TIEMPO_REAL_PUERTO=8765
MENSAJERO_TIEMPO_REAL_URL=http://127.0.0.1:8765
\`\`\`

En \`recolector-whatsapp/.env\`:

\`\`\`dotenv
MENSAJERO_TIEMPO_REAL_PUERTO=8765
\`\`\`

El \`WHATSAPP_PASARELA_SECRETO\` de Laravel y el \`MENSAJERO_PASARELA_SECRETO\` del recolector deben coincidir (ya es un requisito de la pasarela). No publicar estos valores ni archivos \`.env\`.

Iniciar Laravel y el recolector como siempre:

\`\`\`powershell
php artisan serve --host=127.0.0.1 --port=8000
\`\`\`

En otra terminal:

\`\`\`powershell
cd recolector-whatsapp
npm run verificar
npm run pruebas
npm run desarrollo
\`\`\`

Abrir \`http://127.0.0.1:8000/tablero\`. El estado indica "En vivo" cuando funciona el SSE. No se necesita botón de actualización ni un nuevo proceso adicional. Se debe reiniciar el recolector una vez al aplicar el código nuevo para habilitar el puerto SSE.

## Seguridad / servidor corporativo

- Por defecto, el SSE escucha **solo en 127.0.0.1**, no en toda la red.
- Para clientes remotos usar un proxy inverso **HTTPS** (con soporte de conexiones SSE persistentes y sin búfer) hacia el puerto local. Establecer \`MENSAJERO_TIEMPO_REAL_URL=https://.../...\` según el proxy y alinear el origen permitido con \`MENSAJERO_URL\` del recolector.
- No exponer directamente el puerto 8765 a Internet ni usar URLs HTTP cuando el tablero se sirva por HTTPS.
- El navegador obtiene una credencial firmada de 90 segundos únicamente si Laravel autoriza consultar evidencia. El SSE reconecta para renovar la credencial y fuerza renovación de conexión después de diez minutos.
- Si el SSE se interrumpe, no se detiene la captura. Al reconectar, el navegador vuelve a consultar el estado real a Laravel.
- Si Laravel almacena el mensaje pero el aviso inmediato falla, SQL Server conserva el contenido. El tablero realiza reconciliación de respaldo para evitar que la vista quede indefinidamente desactualizada.
- Los mensajes históricos ya existentes no se reenvían al SSE. El fragmento de Laravel sigue siendo la fuente de verdad.

## Validación

\`\`\`powershell
cd recolector-whatsapp
npm run verificar
npm run pruebas
\`\`\`

Comprobar en el navegador que el indicador muestre "En vivo", enviar un mensaje **nuevo** desde otro participante del grupo autorizado y observar cómo se desplaza al entrar. El cambio debe aparecer sin pulsar botones ni recargar la página.

Si aparece "Canal en vivo no disponible", revisar el estado del recolector, la variable \`MENSAJERO_TIEMPO_REAL_HABILITADO\`, la coincidencia de los secretos y el puerto 8765. Nunca borrar colas como método de recuperación.
