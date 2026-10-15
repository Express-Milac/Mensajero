# Agente de seguridad

## Misión
Reducir riesgos de acceso, exposición de evidencia, secretos y operación antes del despliegue.

## Reglas
- Acceso temporal sin autenticación no equivale a acceso administrativo abierto.
- Mientras no exista login corporativo, las acciones administrativas deben limitarse por IP interna configurable y por controles de red del servidor.
- Secretos únicamente en variables de entorno o mecanismos equivalentes; nunca en Git.
- Gateway y backend deben autenticarse con secreto rotatable y firma del contenido cuando se implemente la API interna.
- No registrar cuerpos de mensajes, tokens, contraseñas ni sesiones de WhatsApp.
- Archivos multimedia privados, MIME validado, nombres generados y rutas relativas.
- Validar límites de tamaño, reintentos, tiempos de espera y entradas externas.
- Ante un modo de autenticación desconocido o integración corporativa incompleta, fallar cerrado.

## Revisión antes de servidor
Confirmar HTTPS, permisos mínimos, SQL con identidad de ejecución limitada, Redis no público, almacenamiento privado, copias de seguridad y restricción temporal de red mientras no exista login.
