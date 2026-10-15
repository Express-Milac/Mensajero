# Objetivo de Mensajero

Mensajero debe convertir conversaciones de WhatsApp Business relacionadas con la operación de transporte en evidencia organizada y, después, en eventos operativos interpretados por inteligencia artificial sin alterar ni sustituir la fuente original.

## Contexto

El número de WhatsApp es compartido y participa en conversaciones ajenas al sistema. Solo aproximadamente 5–6 grupos seleccionados deben producir almacenamiento y procesamiento. Los grupos existentes tienen al menos alrededor de 30 participantes, por lo que la captura se diseñará mediante una puerta de enlace autohospedada por MILAC y desacoplada del núcleo de Laravel.

## Principios obligatorios

- Lista blanca con denegación por defecto.
- Adhesivos ignorados antes de persistencia y descargas.
- Tipos desconocidos de conversaciones autorizadas conservan evidencia cruda.
- SQL Server almacena datos estructurados, metadatos y rutas, nunca archivos binarios operativos.
- Los archivos viven en almacenamiento privado configurable mediante Flysystem.
- Evidencia cruda + bandeja de salida se confirman en una misma transacción antes de reconocer recepción durable.
- Reintentos e idempotencia evitan duplicados.
- Gemini puede fallar sin impedir captura ni consulta de mensajes originales.
- Mensajes originales permanecerá disponible incluso cuando exista Mensajero IA.
- Cada evento de IA deberá señalar exactamente los mensajes que lo sustentan.
- La nomenclatura propia del sistema se mantiene en español.

## Primer objetivo funcional

Captura cruda controlada con:

- conexión WhatsApp;
- descubrimiento de conversaciones;
- administración de lista blanca;
- identificación de conversación y remitente;
- fechas consistentes;
- texto y referencias de mensajes citados;
- multimedia fuera de SQL Server;
- consulta paginada de mensajes originales;
- filtros y búsqueda;
- actualizaciones en tiempo real;
- errores y salud visibles.

## Aceptación mínima

- Conversación A monitoreada: texto y fotografía se conservan.
- Conversación B monitoreada: texto y audio se conservan.
- Conversación C no monitoreada: no crea evidencia, no descarga archivos y no llega a IA.
- Adhesivo en una conversación monitoreada: se descarta.
- Evento repetido: no duplica evidencia ni bandeja de salida.
- Conversación desactivada: deja de aceptar nuevas capturas y conserva el historial previo.
- Caída de Redis o Gemini: SQL Server todavía puede conservar evidencia cruda.
- Cambiar la raíz de almacenamiento no requiere reescribir la lógica empresarial.

## Tecnología

Laravel/PHP 8.4+, Microsoft SQL Server, Redis, Flysystem y Blade/Livewire/Alpine. El núcleo seguirá siendo un monolito modular; la puerta de enlace WhatsApp será el único componente separado que dependa de la librería concreta elegida para la sesión.
