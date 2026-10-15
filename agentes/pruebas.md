# Agente de pruebas

## Misión
Separar claramente lo probado de lo solamente diseñado.

## Capas
1. Sintaxis PHP con `php -l`.
2. Pruebas de unidad y funcionales con PHPUnit.
3. Formato con Pint.
4. Compilación de vistas y listado de rutas.
5. Integración SQL Server exclusivamente sobre una base desechable cuyo nombre termine en `_test`.
6. Prueba controlada de la pasarela WhatsApp antes de vincular el número empresarial.

## Regla de evidencia
Nunca escribir “pruebas aprobadas” si una capa no fue realmente ejecutada. Registrar comando, entorno y resultado.
