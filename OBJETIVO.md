# Objetivo de Mensajero

Convertir conversaciones de WhatsApp Business de la operación de transporte en evidencia organizada y, posteriormente, en eventos operativos interpretados por Gemini y verificables por personas.

El número también participa en conversaciones ajenas. Solo los 5–6 grupos seleccionados deben generar almacenamiento y procesamiento. Los grupos existentes pueden tener 30 o más participantes.

## Principios
- WhatsApp es la fuente; el evento crudo conserva evidencia; Gemini interpreta; Mensajero presenta; una persona verifica.
- Lista permitida por defecto denegada antes de evento crudo, multimedia o IA.
- Pegatinas se descartan incluso en grupos autorizados.
- Tipos desconocidos provenientes de chats autorizados se conservan para evolución futura.
- SQL Server guarda datos estructurados, metadatos y rutas; los archivos viven en almacenamiento privado configurable.
- Evento crudo y buzón de salida se confirman en una misma transacción.
- Redis y Gemini nunca son condición para aceptar el evento crudo.
- Cada interpretación deberá enlazar sus mensajes fuente.
- La vista de mensajes originales será permanente.
- La pasarela de WhatsApp será autohospedada por MILAC y el dominio no dependerá de la biblioteca concreta utilizada por esa pasarela.

## Infraestructura confirmada
Mensajero se desplegará en servidor propio de MILAC y usará una base SQL Server corporativa ya existente. No se crea una base operativa separada.

Temporalmente no existe inicio de sesión corporativo. `MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite avanzar únicamente dentro de una red controlada. `corporativo` falla cerrado hasta existir el adaptador real.

## Primer objetivo funcional
Captura controlada: conexión, descubrimiento de grupos, selección administrable, recepción, remitente, fecha UTC, citas, contenido original, multimedia fuera de SQL Server, consulta paginada, búsqueda, filtros y actualización en tiempo real.

Aceptación mínima:
- Grupo autorizado: texto y multimedia se conservan.
- Grupo no autorizado: no crea evento crudo, no descarga archivos y no llega a IA.
- Pegatina de un grupo autorizado: no se almacena.
- Repetir un evento: no duplica evento crudo ni buzón de salida.
- Desactivar un grupo: detiene nuevas capturas sin borrar evidencia previa.
- Caída de Redis/Gemini: no impide persistir el evento crudo.
