# Objetivo de Mensajero

Convertir conversaciones de WhatsApp de la operación de transporte en evidencia organizada y, posteriormente, eventos operativos interpretados por Gemini y verificables por personas.

Solo los 5–6 grupos seleccionados deben generar almacenamiento y procesamiento. Esos grupos ya existen y pueden tener 30 o más participantes.

## Principios

WhatsApp es la fuente. El evento crudo conserva evidencia. Gemini interpreta. Mensajero presenta. El humano verifica. Primero capturar, conservar y trazar; después interpretar y presentar.

- Lista permitida por defecto denegada antes de evento crudo, multimedia o IA.
- Pegatinas se descartan incluso en grupos autorizados.
- Tipos desconocidos de chats autorizados conservan evento crudo para evolución futura.
- SQL Server guarda datos, metadatos y rutas; archivos viven en almacenamiento privado portable.
- Evento crudo + buzón de salida se confirman en una misma transacción antes de considerar durable el evento.
- Redis y Gemini nunca son condición para aceptar evento crudo.
- Cada evento IA deberá enlazar exactamente a sus mensajes fuente.
- La vista Mensajes Originales será permanente.
- El objetivo de confiabilidad es **cero pérdida silenciosa**: si un intervalo no puede demostrarse completo, se marca como integridad no confirmada y no se usa como base directiva hasta reconciliarlo.

## Canales de captura

1. **Meta Cloud API oficial** para los flujos que Meta expone oficialmente.
2. **Recolector dedicado de grupos normales** para el POC de grupos existentes: un número corporativo secundario y reemplazable participa como usuario y se vincula a `recolector-whatsapp/`.

El número principal de MILAC no se utiliza en el recolector no oficial.

## Infraestructura confirmada

Mensajero se desplegará en servidor propio de MILAC. Sus tablas vivirán en una base SQL Server corporativa ya existente, aisladas mediante nombres `mensajero_*`; no se crea una base nueva por defecto.

Temporalmente no existe inicio de sesión corporativo. `MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite avanzar en el tablero interno ordinario, sin privilegios administrativos. El modo `corporativo` falla cerrado hasta implementarse.

## Primer objetivo funcional

Captura controlada: conexión, descubrimiento de grupos, lista permitida administrable, recepción, remitente, fecha UTC, citas, contenido original, multimedia fuera de SQL, consulta paginada, búsqueda, filtros y actualización en tiempo real.

Aceptación mínima:

- Grupo A/B monitoreados: texto y multimedia se conservan.
- Grupo C ignorado: no crea evento crudo, no descarga archivos y no llega a IA.
- Pegatina de un grupo monitoreado: no se almacena.
- Repetir un evento: no duplica evento crudo ni buzón de salida.
- Desactivar un grupo: detiene nuevas capturas sin borrar evidencia previa.
- Caída de Laravel/SQL/red: la cola local conserva mensajes autorizados pendientes.
- Caída de Redis/Gemini: no impide persistir evento crudo en SQL.
- Caída o reinicio del recolector: se registra un periodo de integridad no confirmada.

## Puerta previa a grupos operativos

Antes de incorporar grupos reales deben aprobarse pruebas del recolector sobre una cuenta y grupo de prueba: compilación TypeScript, pruebas, carga, desconexiones, reinicios, cola pendiente, deduplicación, texto, foto, video, audio, documento, citas y recuperación. Multimedia binaria debe quedar almacenada durablemente antes de considerar el canal apto para decisiones directivas.
