# Objetivo de Mensajero

Convertir conversaciones de WhatsApp de la operación de transporte en evidencia organizada y, posteriormente, eventos operativos interpretados por Gemini y verificables por personas.

El número es WhatsApp Business y es compartido con conversaciones ajenas. Solo los 5–6 grupos seleccionados deben generar almacenamiento y procesamiento. Esos grupos ya existen y tienen al menos alrededor de 30 participantes.

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
- La pasarela WhatsApp será autohospedada por MILAC; no se utilizará un SaaS de terceros para capturar los grupos operativos.
- El dominio no conocerá la librería concreta de la pasarela: todo pasará por un contrato neutral.

## Infraestructura confirmada

Mensajero se desplegará en servidor propio de MILAC. Sus tablas vivirán en una base SQL Server corporativa ya existente, aisladas mediante nombres `mensajero_*`; no se crea una base nueva por defecto.

Temporalmente no existe inicio de sesión corporativo. `MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite avanzar en el tablero interno ordinario, sin privilegios administrativos. La aplicación conserva una frontera de autorización para integrar después el inicio de sesión corporativo sin reescribir rutas o lógica de negocio. El modo `corporativo` falla cerrado hasta implementarse.

## Primer objetivo funcional

Captura controlada: conexión, descubrimiento de grupos, lista permitida administrable, recepción, remitente, fecha UTC, citas, contenido original, multimedia fuera de SQL, consulta paginada, búsqueda, filtros y actualización en tiempo real.

Aceptación mínima:

- Grupo A/B monitoreados: texto y multimedia se conservan.
- Grupo C ignorado: no crea evento crudo, no descarga archivos y no llega a IA.
- Pegatina de un grupo monitoreado: no se almacena.
- Repetir un evento: no duplica evento crudo ni buzón de salida.
- Desactivar un grupo: detiene nuevas capturas sin borrar evidencia previa.
- Caída de Redis/Gemini: no impide persistir evento crudo en SQL.

## Puerta previa a integración

Antes de conectar la captura real o integrar al servidor productivo deben aprobarse Composer, PHPUnit, Pint, rutas/vistas y las migraciones/pruebas sobre una base SQL Server desechable `_test`. Un fallo de arranque del sistema de CI antes de crear jobs no se considera aprobación ni fallo de esas pruebas.
