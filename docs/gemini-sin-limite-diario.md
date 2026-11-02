# Gemini: cuota diaria opcional sin límite (piloto individual)

## Activación explícita

`MENSAJERO_IA_CONCILIACION_MAXIMO_DIA_GRUPO=0` permite reservar solicitudes sin tope diario por grupo para el **piloto individual**, una vez desplegado el código de esta rama. No cambia los valores por defecto: los valores 1 a 30 conservan su límite; los negativos y mayores a 30 bloquean nuevas reservas. El valor 0 no desactiva los controles de consentimiento.

Se mantienen todos los controles de grupo, fecha de corte, estado y autorización de pagos. También se mantienen los registros de reserva única por mensaje, protección contra duplicación y prohibición de reintentos inciertos. El límite propio de lotes continúa aplicándose si esa función experimental se activa.

## Riesgo de costos

El modo sin límite puede generar un número indefinido de llamadas pagadas a Gemini, según el volumen de WhatsApp y las restricciones del proveedor. Revisar la facturación/cuotas del proveedor y mantener disponible el interruptor de Gemini en el panel.

## Recuperación de pendientes

La normalización publica análisis para mensajes nuevos, pero **no vuelve a publicar mensajes ya normalizados** que se omitieron por cuota. La conciliación automática sigue desactivada por defecto y exige nueva fecha de corte, ventana reciente y autorización independientes. No activar `--aplicar` para recuperar mensajes históricos sin evaluar primero el alcance.

## Validación PHP 7.4 y despliegue

Comprobar `php7.4 -l` en los archivos cambiados y ejecutar la prueba `tests/Unidad/PresupuestoGeminiSinLimiteTest.php` con la base de datos de pruebas; no ejecutar pruebas que utilicen SQL de producción.

Tras definir la variable a 0 en `.env`, limpiar caché de configuración si existe, verificar que el diagnóstico muestre `SIN LIMITE` y reiniciar **solo** los trabajadores de Laravel que consumen esa configuración, asegurando previamente que no hay trabajos Gemini reservados/en curso. El despacho multigrupo y las tareas WhatsApp no se modifican.

Para revertir sin cambiar código: establecer el valor a 3 (o a otro número entre 1 y 30), limpiar caché de configuración si corresponde y reiniciar trabajadores. Las reservas ya efectuadas permanecen registradas.
