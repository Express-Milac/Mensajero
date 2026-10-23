# Cara operativa de Mensajero MILAC — Primera versión

Actualización: 23/10/2026. Código incorporado a la rama de desarrollo; pendiente de ejecutar integración real en SQL Server _test y prueba con WhatsApp del piloto.

## Contrato de las dos caras

**Mensajes originales** (/mensajes) conserva cada texto, fotografía, audio, video y documento individualmente, con identidad, integridad, fecha y remitente. Las inferencias de Gemini NUNCA modifican esta fuente.

**Operación interpretada** (/operacion) muestra acontecimientos propuestos por Gemini a partir de textos o leyendas multimedia, con categoría, título, resumen, revisión y referencias a las evidencias originales. No se presentan como hechos comprobados.

Un acontecimiento puede tener solo texto, un texto con adjuntos, o una foto con leyenda. Varias fotografías sin leyenda no generan automáticamente varios acontecimientos. Los mensajes informales pueden no generar ningún evento.

## Implementación

Migraciones SQL Server:
- 2026_10_23_000100_crear_acontecimientos_operativos.php: crea mensajero_acontecimientos_operativos (proyección 1:1 de análisis IA significativo) y mensajero_evidencias_acontecimientos (relaciones múltiples a mensajes originales).
- 2026_10_23_000200_crear_auditoria_operativa.php: guarda revisiones humanas, actores y marcas UTC.

Una proyección local crea una propuesta por resultado IA, con categoría, título, resumen, estado pendiente_revision y referencias validadas (sustento_ia). La proyección es idempotente por analisis_ia_id. Si falla, el análisis sigue conservado y puede proyectarse luego SIN volver a llamar a Google.

Para fotografía, audio, video o documento cercano, el asociador exige: mismo grupo, mismo contacto remitente, mensaje normalizado, ventana ±90 segundos y **un solo acontecimiento elegible**. Si aparece otro acontecimiento de ese emisor en el intervalo y la vinculación se vuelve ambigua, retira la relación tentativa. Estas fuentes llevan etiqueta posible_adjunta y **NO verifican el contenido visual**. Si falta identificación de remitente o hay varias coincidencias, no se agrupan. La captura individual original permanece intacta.

Gemini puede procesar:
- Texto independiente.
- Leyenda textual de foto/video/documento (nunca bytes de imagen, ni visión artificial).
- Hasta 6 mensajes previos del mismo grupo, dentro de los 5 minutos anteriores y siempre posteriores a consentimiento, únicamente si el responsable cambia MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=false después de aprobar el nuevo alcance. El valor predeterminado continúa true (solo mensaje objetivo).

Habilitar la captura de un nuevo grupo NO le concede autorización Gemini. La lista MENSAJERO_IA_GRUPOS_AUTORIZADOS sigue limitada al piloto 10020 mientras no exista autorización adicional de MILAC.

La interpretación nace pendiente_revision. Un usuario administrador autenticado (o el modo POC localhost explícitamente autorizado) puede marcar validado o descartado. Esto queda auditado y no altera el mensaje original. Validado significa **interpretación revisada por una persona**, no comprobación física de la entrega o el estado de una unidad.

## Permiso local de revisión (cerrado por defecto)

Configurar solo para el piloto local si se desea validar o descartar desde la página, nunca en el servidor público:

    APP_ENV=local
    MENSAJERO_MODO_AUTENTICACION=deshabilitado
    MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
    MENSAJERO_GESTION_LOCAL_OPERACION_HABILITADA=true

Sin esta bandera, /operacion es de solo lectura. El permiso no se hereda de gestión de grupos. En producción se exige autenticación administrativa corporativa.

## Instalación y pruebas

Primero comprobar cuidadosamente el nombre de la BD SQL Server. **No migrar inadvertidamente la base de negocio**; los ensayos deben usar exclusivamente una BD que termine en _test. Instalar las dos nuevas migraciones en un entorno autorizado y reiniciar Laravel y los trabajadores de normalización e IA (sin crear otro recolector).

Verificar contrato y seguridad (no llama a Google):

    php artisan test --filter=GeminiContratoTest
    php artisan test --filter=OperacionAccesoTest
    php artisan route:list --path=operacion
    php artisan view:clear

Solo con la configuración phpunit.integracion.xml apuntando a BD _test:

    vendor\bin\phpunit -c phpunit.integracion.xml --filter AcontecimientosOperativosSqlServerTest

Recuperar interpretaciones previas del piloto, sin consultar Gemini:

    php artisan mensajero:proyectar-operacion --grupo=10020 --limite=50
    php artisan mensajero:proyectar-operacion --grupo=10020 --limite=50 --aplicar

El primero es simulación; el segundo crea acontecimientos a partir de análisis ya existentes, sin crear registros duplicados. No ejecutar el segundo sin comprobar antes la base conectada.

Probar con mensajes ficticios: texto aislado, texto con cinco fotos del mismo remitente, foto con leyenda, varias fotos sin contexto, fotos de otra persona y dos informes consecutivos. Verificar que /operacion proponga acontecimientos pertinentes y que /mensajes conserve cada evidencia original.

## Pendiente antes de habilitar grupos reales

Esta **primera versión no consolida semánticamente** un incidente actualizado por varias personas, ni une automáticamente acontecimientos duplicados de distintos mensajes. Tampoco analiza píxeles ni permite revisar manualmente cada vínculo tentativo de una fotografía. Esas funciones deberán iterarse con conversaciones del piloto, controles de privacidad/costo y más pruebas. Para operación 24/7 falta supervisor, latidos, recuperación de publicación IA y alerta de consumo.

No considerar este prototipo certificado para producción MILAC.
