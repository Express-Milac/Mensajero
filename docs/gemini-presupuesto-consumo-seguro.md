# Diseño previo: presupuesto de consumo de Gemini en Mensajero

**Fecha:** 2026-10-28  
**Estado:** diseño; no es una implementación ni autorización operativa.  
**Rama:** `integracion/normalizacion-gemini-validada-20261028`.

## Avance experimental de Fase 1 (NO VALIDADO AÚN, 2026-10-28)

Se añadió `2026_10_28_000400_crear_reservas_presupuesto_gemini.php` con dos tablas: `mensajero_candado_presupuesto_gemini` (fila global de bloqueo) y `mensajero_reservas_consumo_gemini` (fuente única por `tipo_fuente + fuente_id`, grupo, importe entero en microUSD, tarifa, fecha y estado).

`ReservarPresupuestoGemini` implementa reservas idempotentes y límites compartidos globales por día/mes UTC; nunca devuelve cupo por una reserva fallida/incierta, ni siquiera al finalizar. **El importe de cada reserva es un máximo configurado artificialmente, NO un costo máximo validado con tarifas reales.** Este componente está **desconectado de `PublicarLoteGemini`, del circuito individual y de `ClienteGeminiLote`**, y por ello aún NO constituye protección efectiva frente a llamadas reales. El envío real sigue NO autorizado.

La configuración de tarifas, presupuesto y activación es cero/falsa por defecto. Hay seis pruebas nuevas contra SQL Server test y un script para verificar primero `SELECT DB_NAME()`, migrar exclusivamente la base `mensajero_integracion_test`, ejecutar pruebas nuevas y repetir regresión completa. Los resultados de estas pruebas **están pendientes de ejecución del usuario**. Hasta entonces, la cifra confirmada sigue 79/1375 de la versión anterior.

Próxima fase: integrar la reserva monetaria con ambos circuitos **únicamente cuando** exista una cotización máxima sustentada por precios oficiales verificados y límites adecuados de salida/pensamiento. No se deben aprobar tarifas ficticias ni conectar un máximo arbitrario al envío real.

## Referencia de cotización implementada y pendiente de pruebas (28/10/2026)

Se añadió `CotizarCostoReferenciaGemini`, un **cotizador puro** sin consultas SQL, HTTP, Redis ni autorización de pagos. Solo admite el modelo exacto `gemini-3.5-flash-lite` y los circuitos actuales `mensaje` y `lote`; para cualquier modelo/circuito desconocido devuelve `null` (no cotiza).

**Tarifas estándar publicadas por Google al revisar el 28/10/2026**: USD 0.30 / millón de tokens de entrada y USD 2.50 / millón de tokens de salida, incluyendo pensamiento. El modelo permite 1,048,576 tokens de entrada, que se presupuestan COMPLETOS aun cuando el mensaje real sea mucho menor. Los clientes actuales limitan su `maxOutputTokens` a 900 (individual) y 1,800 (lote), parámetro que según Google también incluye tokens de pensamiento.

La referencia se calcula usando solo enteros en microUSD y redondeo hacia arriba por componente:

| Circuito | Entrada máxima reservada | Salida máxima reservada | Total de referencia |
| --- | ---: | ---: | ---: |
| Mensaje | 314,573 microUSD | 2,250 microUSD | **316,823 microUSD (USD 0.316823)** |
| Lote | 314,573 microUSD | 4,500 microUSD | **319,073 microUSD (USD 0.319073)** |

**Limitaciones:** es una referencia conservadora aplicable solamente a las solicitudes `generateContent` estándar actuales, basadas en texto y sin herramientas ni caché explícita. No cubre cargos de otros productos, búsqueda, audio/vídeo enviados como archivos, almacenamiento explícito en caché, ni cambios futuros de tarifa. Aunque utiliza el máximo del modelo, no se presenta como techo universal de facturación. No sustituye una aprobación explícita de modelo, tarifa, moneda, presupuesto y configuración final.

Se añadieron cuatro pruebas puras `tests/Unidad/CotizarCostoReferenciaGeminiTest.php` y el script `scripts/validar-cotizacion-gemini.ps1` para verificar aritmética y ejecutar regresión SQL. **Estas pruebas aún no se han ejecutado en el equipo del usuario**; la validación SQL ya confirmada sigue siendo **86 pruebas y 1,467 aserciones** sobre el commit previo `442bc4d`. No hay modificaciones en la base ni nueva migración en este bloque.

Referencias verificadas:
- https://ai.google.dev/gemini-api/docs/pricing
- https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite
- https://ai.google.dev/gemini-api/docs/thinking

## Evidencia de la revisión

El circuito individual usa `ClienteGemini` (máximo `maxOutputTokens=900`) y el circuito de lotes usa `ClienteGeminiLote` (`maxOutputTokens=1800`). Ambos consultan el servicio `generateContent` y registran, cuando llegan, `usageMetadata.promptTokenCount` y `candidatesTokenCount`. No se persisten por separado los tokens de pensamiento, caché ni cargos externos. Una falla de transporte puede impedir recibir `usageMetadata` aunque la solicitud haya llegado al proveedor.

`PresupuestoPublicacionesGemini` ya limita el **número de reservas** por grupo y día UTC y suma las reservas de los circuitos individual y por lotes, incluidos estados inciertos. Esto no es un presupuesto en dinero ni un control global de facturación.

Las verificaciones confirmadas son 79 pruebas / 1,375 aserciones de la suite SQL Server con `mensajero_integracion_test`, más cuatro ensayos Redis aislados. No confundir estas pruebas con la puesta en producción.

## Objetivo y límites reales

- **Objetivo local:** impedir **nuevas solicitudes** cuando una reserva conservadora de capacidad calculada con tarifas verificadas supere límites diarios/mensuales autorizados. No es una garantía absoluta de lo que facturará Google.
- **Protección externa adicional:** configurar un límite de gasto por proyecto en Google AI Studio / Cloud Billing cuando la cuenta lo permita. Google advierte de retrasos de aplicación y posibles excedentes; no presentarlo como bloqueo monetario instantáneo.
- **Sin autorización:** presupuesto no configurado, tarifas desconocidas, modelo no aprobado, ausencia de datos, moneda no definida o fallo SQL `=>` no se envía ninguna solicitud.
- **Sin inventar tarifas:** registrar versión, fecha de vigencia, modelo exacto, SKU y origen de precios oficiales; no asumir que el costo es fijo entre modelos o períodos. La revisión/actualización de tarifas es una decisión operativa explícita.
- **Sin presupuesto dado por MILAC:** el límite monetario debe permanecer en cero/deshabilitado. No seleccionar por cuenta propia un importe en MXN o USD.

## Propuesta de implementación (todavía no aplicada)

1. **Tabla propia `mensajero_reservas_consumo_gemini`:** una fila única por solicitud (circuito, ID de lote/mensaje, grupo, modelo, día UTC, referencia de tarifa, reserva máxima monetaria expresada en unidades enteras de moneda, estado `reservada` / `confirmada` / `incierta`, tokens reportados solo como datos observados). Prohibir duplicidad mediante índice único. No guardar texto de mensajes, claves ni credenciales.
2. **Prevalidación por tipo y modelo:** obtener y validar el tamaño máximo autorizado de la entrada, límite de salida y, según modelo, tokens de pensamiento/caché facturables. El contador previo no puede confundirse con el gasto final. Si no se puede calcular un techo conservador defendible de toda la solicitud, el intento se rechaza.
3. **Reserva transaccional:** bajo bloqueos SQL explícitos de grupo y contadores globales/día/mes, computar reservas individuales y por lotes, incluidas `incierta`. Comparar el siguiente costo máximo con presupuesto local; reservar **antes** de publicar/enviar. Prohibir la carrera de dos grupos que consumen el mismo límite global simultáneamente.
4. **Punto de control final:** inmediatamente antes de la red, revalidar el permiso SQL y que la reserva monetaria siga vigente. Nunca calcular un nuevo presupuesto y emitir una segunda solicitud sin el mismo identificador idempotente.
5. **Conciliación sin segundo envío:** tras una respuesta, capturar todas las categorías de tokens que facture el modelo. Ante error de conexión, timeout, caída o `usageMetadata` incompleto, mantener retenida la reserva máxima como `incierta`; NO devolver cupo por suposición y NO reintentar en automático. Cualquier liberación requiere evidencia de proveedor y auditoría.
6. **Revisión manual y auditoría:** mostrar a administradores reservas, gasto estimado/observado, diferencias con facturación del proveedor y motivos de bloqueo, sin información personal de WhatsApp.

## Casos de aceptación necesarios antes de activar

- Sin tarifas verificadas, presupuesto 0, límite de salida desconocido o modelo distinto `=>` ninguna solicitud.
- Entradas máximas y dos reservas simultáneas de grupos distintos no exceden el límite global.
- Reserva individual ocupa cupo monetario compartido con lotes; estado incierto continúa ocupándolo.
- La excepción de Redis tras aceptar un trabajo, timeout después de contactar Google y pérdida de respuesta no liberan cupo ni provocan un nuevo envío.
- Al revocar permiso de grupo o global no se transmiten contenidos de nuevos mensajes; cambios de corte no permiten rescatar historia.
- Tokenización, caché y pensamiento se cuentan según el modelo y la tarifa vigente; cambios de precios obligan a aprobar nueva configuración.
- Pruebas SQL contra `mensajero_integracion_test` con `Http::fake`, `Bus::fake`, `preventStrayRequests`, y transacciones reversibles; después repetir la suite integral. Jamás migrar bases operativas en esta fase.

## Fuentes oficiales para validar la implementación

- Facturación, tipos de tokens y límites de proyecto de Gemini: https://ai.google.dev/gemini-api/docs/billing
- Límite de gasto de Google Cloud y advertencia de latencia/excedentes: https://docs.cloud.google.com/billing/docs/how-to/budgets-spend-caps
- Contadores de tokens y diferencias entre candidatos/pensamiento: https://firebase.google.com/docs/ai-logic/count-tokens
- Tarifas exactas y vigencia por modelo/SKU: https://ai.google.dev/gemini-api/docs/pricing

**Regla de proyecto:** mantener `MENSAJERO_IA_LOTES_HABILITADOS=false` y `MENSAJERO_IA_ENVIO_AUTORIZADO=false` hasta que presupuesto y auditoría estén implementados, ensayados y se obtenga aprobación explícita.
