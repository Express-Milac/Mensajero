# Hitos funcionales de Mensajero MILAC

Actualización: 20/10/2026.

Esta hoja sustituye la validación repetitiva de infraestructura por
**entregables funcionales**, con una revisión agrupada por bloque.
La evidencia original en SQL Server nunca se reprocesa masivamente por
defecto. La normalización automática continúa desactivada.

## Fase 1 — Captura, conservación y consulta

| Entregable | Estado real | Criterio siguiente |
|---|---|---|
| Base SQL Server y buzón transaccional | Implementados y ensayados en `_test` | Mantener integridad y deduplicación |
| Redis y normalización | Flujo ficticio extremo a extremo aprobado; recuperación manual aprobada | Pruebas de concurrencia, supervisión y alta disponibilidad antes de producción |
| Descubrimiento y selección de grupos | Administración y lista permitida implementadas | Ensayo con grupos de prueba reales y exclusión de chats ajenos |
| Recolector WhatsApp autohospedado | Servicio preparado en el proyecto; existe riesgo del cliente no oficial | Certificar conexión y recepción continua con número secundario, no principal |
| Almacenamiento durable de fotos, videos, audios y documentos | Recolector y validación de captura implementados | Confirmar montaje de volumen común y descarga real representativa |
| Consulta por grupo y evidencia individual | Vistas protegidas y prueba SQL Server aprobadas; nuevos filtros de tipo de mensaje y fecha integrados, aún no validados en Windows | Revisar filtros y navegación en navegador local |
| Visualizar / descargar multimedia privada | **Validación funcional aprobada en Windows: 2 pruebas / 43 aserciones**, incluyendo revocación; falta revisión visual y captura real | Verificar en navegador local con archivo de prueba y recolector |
| Inicio de sesión MILAC | Frontera preparada, integración corporativa pendiente | Autorización efectiva antes de acceso por red/producción |
| Supervisión/conciliación | Diagnóstico SQL probado; pendientes históricos detectados | Clasificar 40 avisos atrasados antes de despacho masivo, agregar inspección Redis |

### Próximo paso de validación funcional

La descarga multimedia fue certificada en el POC mediante **2 pruebas y
43 aserciones**. La siguiente batería se centra únicamente en el
incremento de filtros del historial, sin repetir Redis ni multimedia:

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php tests\Integracion\VistaMensajesSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
```

Luego abrir `http://127.0.0.1:8000/mensajes` solo desde equipo local
con bandera de consulta POC explícita, `APP_ENV=local` y base
`mensajero_test`. No usar proxy ni túnel externo sin login.
Si no hay binarios de multimedia almacenados, las vistas de los archivos
no pueden verificarse visualmente todavía.

**No se necesitan más ensayos Redis por evento para este avance.**
No activar normalización global ni despachar los 40 avisos históricos
sin clasificar su procedencia.


## Criterios para considerar lista la fase 1

1. Recolector asociado a número secundario de prueba y conexión continua.
2. Solo 5–6 grupos expresamente autorizados; chats ajenos descartados.
3. Texto, fotos, videos, audio y documentos capturados en rutas privadas
   portables; nunca guardar binarios dentro de SQL Server.
4. Vista por grupo e historial completo con acceso controlado; multimedia
   representable/descargable para las personas autorizadas.
5. Evidencia preservada con identificadores, integridad y trazabilidad,
   incluyendo reinicios y ventanas de integridad no confirmada.
6. Supervisión de atrasos, cola, errores y recuperación documentada.
7. Prueba final de regresión, seguridad y respaldo/restauración en un
   entorno equivalente al servidor MILAC.

La fase 1 no está completa ni aprobada para producción mientras esos
criterios no estén comprobados.

## Fase 2 — Interpretación con IA

Gemini solo después de capturar/consultar con integridad y permisos.
Toda interpretación deberá conservar enlace a eventos fuente y permitir
revisión humana antes de alimentar decisiones operativas.
