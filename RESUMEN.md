# Estado y traspaso a otra IA

Actualizado: 16/10/2026.

## Decisiones confirmadas
5–6 grupos seleccionados; grupos normales existentes de 30+ participantes; servidor propio; SQL Server corporativo existente; autenticación corporativa pospuesta. Meta Cloud API continúa implementada para canales oficiales compatibles.

Para resolver los grupos normales existentes se autorizó un POC separado con un **número recolector corporativo secundario**. Ese número es reemplazable y nunca será el número principal de MILAC.

## Implementado
- Dominio, migraciones, rutas principales, estados y pruebas con nomenclatura de negocio en español.
- Lista permitida por defecto denegada y descarte de pegatinas.
- `CapturarEventoWhatsappEntrante` persiste evento crudo + buzón de salida de forma transaccional e idempotente.
- Almacenamiento privado configurable fuera de SQL Server.
- Webhook oficial Meta Cloud API y cliente Graph API propio.
- Contrato HMAC interno con protección antirrepetición para operaciones mutables.
- Administración y auditoría de grupos descubiertos.
- `recolector-whatsapp/` preparado como componente Node.js/TypeScript aislado.
- Cola local durable: mensajes autorizados se guardan en disco antes de transmisión y solo se eliminan tras ACK.
- Caché local de grupos autorizados para sobrevivir indisponibilidad temporal de Laravel.
- Registro de periodos de integridad para desconexiones y reinicios no limpios.
- Comando `mensajero:registrar-conexion-recolector`.

## Estado de certificación
La base Laravel previa al recolector está validada localmente con Pint 78 archivos, PHPUnit 32 pruebas/78 aserciones y SQL Server 6 pruebas/100 aserciones repetidas dos veces.

El incremento del recolector todavía debe ser traído al entorno local y ejecutar `npm install`, `npm run verificar`, `npm run pruebas`, Pint, PHPUnit e integración SQL Server antes de escanear el QR del número recolector.

## Limitación obligatoria antes de operación real
El incremento actual preserva el evento y metadatos de mensajes multimedia, pero todavía no descarga de forma durable el binario. Antes de agregar el número recolector a grupos operativos reales se debe implementar y certificar foto, video, audio y documentos, además de carga/reinicio/desconexión/reconciliación.

## Criterio directivo
Mensajero no debe presentar un intervalo como completo si existe un periodo registrado de integridad no confirmada. El objetivo es cero pérdida silenciosa, no una promesa técnicamente imposible de pérdida absoluta.
