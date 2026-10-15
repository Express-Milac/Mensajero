# Registro de verificación

## 15/10/2026 — refactor previo al servidor

Se aplicaron los agentes de creación, revisión, pruebas, seguridad y UI/UX.

### Hallazgos corregidos

- Tablero compatible con ausencia de usuario autenticado.
- Administración restringida por IP mientras el login está deshabilitado.
- Modo corporativo falla cerrado hasta existir adaptador.
- Dominio, tablas, columnas, rutas visibles, estados y documentación propia en español.
- Eliminación de tablas SQL de framework innecesarias para caché/sesiones/colas; Redis asume esas responsabilidades.
- Lista blanca antes de evidencia cruda y adhesivos ignorados.
- Evidencia cruda + bandeja de salida en una sola transacción.
- Pruebas específicas de nomenclatura e idempotencia añadidas.

### Revisión final del repositorio

Se revisaron de nuevo el núcleo de captura, migraciones SQL Server, modelos, autenticación temporal, permisos, configuración de Redis y almacenamiento, rutas, vistas, pruebas unitarias, funcionales y de integración, además de la documentación de preparación del servidor.

La rama `codex/espanol-agentes-pruebas` se compactó para conservar el mismo árbol de archivos en un único commit base revisable sobre `main`. No se detectaron nuevos defectos estáticos que requirieran modificar el núcleo durante esta revisión final.

### Validaciones preparadas

- PHPUnit: suites `Unitarias` y `Funcionales`.
- PHPUnit SQL Server: suite `Integracion`, solo en base terminada en `_test`.
- Pint.
- Compilación de vistas.
- Listado de rutas.
- Validación de Composer.
- Validadores equivalentes para Linux y Windows en `herramientas/`.

### Bloqueo externo actual

Se intentó ejecutar GitHub Actions varias veces. Los runs terminaron como `startup_failure` antes de crear jobs, incluso con un flujo mínimo que solo hacía checkout y `echo`. Después de compactar la rama y volver a disparar el flujo, el resultado volvió a ser `startup_failure` antes de ejecutar pruebas.

Por tanto no existe evidencia de ejecución de PHPUnit/SQL Server en GitHub y no se declara la suite en verde. Este bloqueo apunta a configuración, política o disponibilidad de Actions en el repositorio u organización y debe resolverse o sustituirse por ejecución directa en un entorno con Composer y SQL Server antes de producción.

No se ejecutaron migraciones productivas, no se conectó el número empresarial y no se desplegó esta rama al servidor MILAC.
