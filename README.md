# Mensajero

Sistema interno de MILAC para convertir mensajes de grupos seleccionados de WhatsApp en evidencia operativa consultable y, más adelante, en eventos interpretados por IA.

## Arquitectura vigente

Mensajero conserva dos canales separados:

- **Meta Cloud API oficial** para conversaciones y grupos compatibles con la plataforma oficial.
- **Recolector de grupos normales existentes** en `recolector-whatsapp/`, únicamente con un número corporativo secundario dedicado y reemplazable.

El número principal de MILAC no debe vincularse al recolector no oficial.

## Reglas de captura
- Solo se procesan grupos expresamente autorizados.
- Nuevos grupos descubiertos nacen no monitoreados.
- Pegatinas se descartan.
- Evento crudo + buzón se guardan transaccionalmente e idempotentemente.
- El recolector escribe cada mensaje autorizado en cola durable local antes de transmitirlo.
- Una tarea local solo se elimina después de confirmación satisfactoria de Laravel.
- Reinicios/desconexiones generan periodos de integridad no confirmada.
- SQL Server no almacena binarios; archivos usan almacenamiento privado configurable.

## Estado implementado
- Laravel 13 / PHP 8.4.
- SQL Server con tablas `mensajero_*`.
- Administración y auditoría de grupos.
- Webhook oficial Meta con validación `X-Hub-Signature-256`.
- Contrato HMAC interno.
- `recolector-whatsapp/` con descubrimiento de grupos, filtro local, QR, reconexión, cola durable y monitor de integridad.
- Comando `mensajero:registrar-conexion-recolector`.

## Estado de pruebas
La base anterior al incremento recolector está certificada localmente: Pint 78 archivos, PHPUnit 32/32 (78 aserciones) y SQL Server 6/6 (100 aserciones) repetido dos veces.

El recolector agregado en el incremento actual debe certificarse localmente antes de vincular el número dedicado.

## Preparación del POC recolector

Laravel debe usar exclusivamente `mensajero_test` durante estas pruebas.

```powershell
php artisan mensajero:registrar-conexion-recolector recolector-pruebas --nombre="Recolector MILAC pruebas"
cd recolector-whatsapp
Copy-Item .env.example .env
npm install
npm run verificar
npm run pruebas
```

Completar `.env` local con el mismo `WHATSAPP_PASARELA_SECRETO`/`MENSAJERO_PASARELA_SECRETO` sin compartirlo ni subirlo a Git.

Después ejecutar Laravel y `npm run desarrollo`. El QR se escanea **solo** con el número recolector dedicado.

Ver `recolector-whatsapp/README.md` y `docs/recolector-whatsapp-dedicado.md`.

## Limitación actual
La metadata de foto/video/audio/documento queda en el evento crudo, pero el binario todavía no se descarga durablemente. El número recolector no debe entrar a grupos operativos reales hasta completar y certificar multimedia, carga, reinicios y reconciliación.
