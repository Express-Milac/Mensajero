# Hitos funcionales de Mensajero MILAC

Actualización: 22/10/2026.

## Estado más reciente — Gemini

| Hito | Estado comprobado |
|---|---|
| Captura WhatsApp en grupo piloto 10020 | Aprobada con mensaje ficticio #74 |
| Normalización y publicación Redis | Trabajo IA finalizó DONE |
| Google Gemini y contrato JSON | Evento incidencia válido; 223 tokens entrada y 84 salida |
| SQL Server y UI de eventos | pendiente_revision y evidencia #74 visibles |
| Pruebas de contrato Gemini | 9 pruebas, 19 aserciones aprobadas en Windows |
| Activación automática | Código disponible; requiere .env local, servicios supervisados y autorización |
| Aprobación humana / costos / seguridad 24x7 | **Pendiente de implementación y certificación** |

**Importante:** el resto de este documento conserva hitos históricos de la fase 1 del 20/10/2026. Cuando contradigan el estado Gemini del 22/10/2026, prevalece este apartado. La activación por defecto en .env.example sigue cerrada; véase [operación continua](operacion-gemini-permanente.md).



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

**Piloto funcional validado 22/10/2026** con mensaje #74, JSON Gemini, persistencia SQL Server y evidencia vinculada en /mensajes/ia. El despacho automático por mensaje nuevo está implementado pero necesita configuración local y trabajadores permanentes. Siguen pendientes revisión humana operativa, límites de consumo, conciliación de errores y certificación para los grupos reales; ver [canalización de IA](canalizacion-ia.md).

## Enfoque directivo del dashboard — 20/10/2026

El tablero **no evalúa infraestructura, normalización o salud de Redis como
prioridad gerencial**. La pantalla de entrada (`/tablero`) y el panorama
(`/mensajes`) presentan las **conversaciones y sus mensajes**, no un
tablero de tecnología:

- Total de grupos seleccionados, mensajes registrados hoy, grupos con
  mensajes hoy y fecha del último mensaje almacenado.
- Carrusel de desplazamiento **vertical**, con tarjetas diferenciadas
  mediante iconos y colores estables por ID de grupo. Los colores
  **no** afirman riesgo, importancia, progreso o desempeño.
- Hasta tres mensajes recientes por tarjeta, con remitente, extracto
  literal, hora y enlace para abrir el historial. El contenido está
  escapado en Blade y procede de evidencia persistida; sin IA ni
  interpretación inventada.
- Filtros de grupos con/sin mensajes registrados hoy y orden por
  mensaje reciente, volumen del día o nombre.
- Distintivo `Ensayo` solo para conexiones técnicas que cumplen
  exactamente `ensayo-redis-<16 caracteres hexadecimales>`.
  No se decide si otro grupo es de prueba por su nombre.

El diagnóstico técnico anterior (`/mensajes/tecnico`) mantiene
contadores de captura, mensajes normalizados y periodos de integridad,
pero es **acceso de administrador**, cerrado por defecto durante el
POC sin inicio de sesión. La nueva entrada ejecutiva también exige el
middleware de consulta protegida, incluso para `/tablero`.

La fase de análisis con Gemini podrá agregar temas, acuerdos, tareas,
responsables y seguimiento **solamente cuando cada afirmación incluya
fuentes verificables**. Hasta entonces no debe presentarse como una
conclusión fáctica a partir de la actividad o de los colores.

### Comprobación agrupada pendiente en Windows

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php routes\web.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Funcional\ConsultaEvidenciaAccesoTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
php artisan view:clear
```

En localhost, revisar `http://127.0.0.1:8000/tablero`, filtrar
`?actividad=hoy`, cambiar el orden y abrir las conversaciones. No
activar trabajadores, no alterar registros históricos ni publicar los
40 avisos pendientes. Restaurar
`MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=false` al terminar.
