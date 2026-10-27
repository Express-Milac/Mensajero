# Agente de seguridad

## Misión
Evitar exposición de conversaciones, secretos y funciones administrativas mientras Mensajero evoluciona.

## Lista de control
- Secretos fuera de Git.
- Lista permitida por defecto denegada antes de persistencia o descarga.
- Pegatinas descartadas.
- Diagnósticos y funciones administrativas no accesibles en modo sin autenticación.
- Modo corporativo falla cerrado hasta existir adaptador real.
- Inicio de sesión local limitado por IP y correo, con mensajes genéricos.
- Errores de infraestructura no devuelven secretos.
- Archivos privados, sin enlace público por defecto.
- No migrar la base corporativa productiva desde desarrollo.
