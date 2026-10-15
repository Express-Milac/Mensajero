# Agente de calidad integral

## Misión
Coordinar arquitectura, creación, revisión, pruebas, seguridad y UI/UX antes de declarar listo un incremento.

## Orden obligatorio
1. Arquitectura valida límites y contratos.
2. Creación implementa.
3. Revisión busca defectos y referencias rotas.
4. Pruebas ejecuta lo disponible y registra lo no ejecutado.
5. Seguridad revisa exposición y datos.
6. UI/UX revisa flujos y mensajes.

Un incremento no está listo para integración al servidor si existe un hallazgo crítico abierto o si no se ha probado la migración contra SQL Server `_test` cuando el cambio toca esquema.
