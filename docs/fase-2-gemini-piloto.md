# Fase 2 — Gemini: piloto validado y transición a análisis continuo

**Estado al 22/10/2026:** piloto funcional comprobado, **no certificado para producción**.

## Evidencia del primer recorrido real

Grupo de prueba: **10020, Mensajero milac Prueba**.

| Hito | Evidencia |
|---|---|
| Captura de WhatsApp | Mensaje normalizado #74, 22/10/2026 17:20:57 UTC |
| Contenido de ensayo | Retraso **ficticio** de unidad TEST-01, 45 minutos por falta de andén |
| Publicación en Redis | Trabajo AnalizarMensajeGeminiTrabajo en cola mensajero_ia |
| Solicitud real a Google | Trabajo completado DONE a las 11:40:37, después de corregir error temporal HTTP 401 |
| Respuesta Gemini | categoria=incidencia, título «Retraso en descarga por falta de anden» |
| SQL Server | mensajero_analisis_operativos_ia, mensaje_whatsapp_id=74, estado=pendiente_revision |
| Interfaz | Pantalla /mensajes/ia con título, resumen y enlace a mensaje #74 |
| Consumo mostrado | 223 tokens entrada, 84 salida |
| Pruebas PHP | GeminiContratoTest: 9 pruebas, 19 aserciones aprobadas en Windows |

### Errores encontrados y corregidos durante el piloto

1. En Windows, cURL 60 requirió configurar una CA válida para PHP; **no** se desactivó verificación HTTPS.
2. El trabajo IA carecía de trait Dispatchable; se reparó y agregó prueba de despacho.
3. Un intento Google devolvió HTTP 401; se añadió diagnóstico limitado a códigos técnicos y el siguiente reintento terminó DONE.
4. Antes de la prueba se impidió enviar contexto histórico por defecto, y la publicación manual exige ID explícito.

No repetir el mismo mensaje para gastar cuota, no ejecutar workers de IA duplicados ni reutilizar claves fuera de canales corporativos aprobados.

## Qué está implementado

- Generación Gemini con JSON estructurado y validación estricta en PHP.
- Clasificación: logistica, incidencia, acuerdo, solicitud, otro.
- Enlace a mensajes originales, estado pendiente_revision / sin_evento y conservación de tokens.
- Política de grupo autorizado, tipo texto, fecha de corte, servicio pagado confirmado, autorización de envío y clave.
- Un trabajo Redis de Gemini separado de normalización, con reintentos y protección de unicidad en SQL Server.
- **Publicación automática desde el normalizador** si MENSAJERO_IA_EJECUCION_AUTOMATICA=true. Se mantiene independiente de captura de WhatsApp.

## Qué NO está implementado

- Aprobación/rechazo/edición humana con auditoría y roles reales (la interfaz solo muestra pendiente).
- Límite monetario o de tokens por día/proyecto y alertas automáticas de presupuesto.
- Conciliación persistente de publicaciones IA perdidas cuando Redis falla **después** del commit de normalización.
- Garantía de una única llamada a Google por mensaje cuando hay reintentos, disponibilidad continua 24/7 ni pruebas de estrés.
- Anonimización completa del texto y aprobación de su uso para todos los grupos reales.

## Activación persistente autorizada

Una vez autorizadas facturación, privacidad y grupos específicos, descargar los scripts y ejecutar:

~~~powershell
php artisan test --filter=GeminiContratoTest
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\activar-gemini.ps1 -GrupoId 10020
~~~

Esto escribe en **.env local** las variables IA_HABILITADA=true, MENSAJERO_IA_ENVIO_AUTORIZADO=true, MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO=true, MENSAJERO_IA_EJECUCION_AUTOMATICA=true, MENSAJERO_NORMALIZACION_HABILITADA=true, MENSAJERO_IA_GRUPOS_AUTORIZADOS=10020, MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true y fecha de corte **actual**, sin retroanalizar históricos. Solicita clave oculta y confirmación de autorización.

Levantar programador, normalizador y un solo trabajador IA:

~~~powershell
php artisan schedule:work
php artisan queue:work redis --queue=mensajero_normalizacion --sleep=2 --tries=5 --timeout=60
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\ejecutar-gemini-windows.ps1
~~~

Son **terminales separadas** y también hacen falta servidor HTTP, recolector WhatsApp y Redis. Para arranque desatendido tras reinicios se requiere supervisor de procesos/servicio. Procedimiento ampliado: [operación permanente](operacion-gemini-permanente.md).

## Política de procesamiento

Las instrucciones completas, parámetros y campos de Gemini están en [canalización de IA](canalizacion-ia.md). Por defecto solo viaja el mensaje objetivo, sin historial del chat y sin adjuntos. Los resultados deben ser revisados por una persona antes de usarse operativamente.

## Siguiente aprobación pendiente

**No incorporar los otros grupos de MILAC al envío a Google** hasta acreditar autorización de datos, facturación controlada y seguridad del servidor. Un flujo piloto que funciona no constituye una certificación de producción.
