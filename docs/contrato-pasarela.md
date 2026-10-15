# Contrato interno de pasarela — v1

La pasarela de WhatsApp es autohospedada por MILAC y se comunica con Laravel mediante rutas internas versionadas. Este contrato no autoriza por sí mismo a capturar un chat: la lista permitida de Mensajero sigue aplicándose después de autenticar la petición.

## Rutas

- `POST /api/pasarela/v1/estado-conexion`: actualiza el estado de una conexión registrada (`conectando`, `requiere_vinculacion`, `conectado`, `desconectado` o `error`).
- `POST /api/pasarela/v1/chats-descubiertos`: sincroniza metadatos de chats/grupos descubiertos. Los nuevos registros nacen con `es_monitoreado=false`.
- `POST /api/pasarela/v1/chats-autorizados`: devuelve exclusivamente chats activos con `es_monitoreado=true`. Es una consulta de solo lectura usada para filtrar en la pasarela antes de descargar/procesar contenido costoso.
- `POST /api/pasarela/v1/eventos`: entrega un evento de WhatsApp al núcleo de captura. Un chat desconocido, inactivo, no monitoreado o una pegatina se ignora antes de persistir el evento crudo.

## Encabezados obligatorios

- `X-Mensajero-Id`: identificador de 16 a 128 caracteres (`A-Z`, `a-z`, `0-9`, `.`, `_`, `:`, `-`). Para operaciones de escritura no debe reutilizarse nunca.
- `X-Mensajero-Marca-Tiempo`: Unix timestamp en segundos.
- `X-Mensajero-Firma`: `sha256=<hexadecimal de 64 caracteres>`.

La ventana predeterminada es de 300 segundos y se configura con `MENSAJERO_VENTANA_FIRMA_SEGUNDOS`.

## Construcción de la firma

1. Serializar el cuerpo JSON final exactamente como se enviará.
2. Calcular `SHA256(cuerpo)` en hexadecimal minúsculo.
3. Construir, sin espacios adicionales, la cadena canónica:

```text
v1\n<X-Mensajero-Id>\n<X-Mensajero-Marca-Tiempo>\n<SHA256(cuerpo)>
```

4. Calcular `HMAC-SHA256(cadena_canónica, WHATSAPP_PASARELA_SECRETO)`.
5. Enviar el resultado como `X-Mensajero-Firma: sha256=<resultado>`.

El secreto debe tener al menos 32 caracteres aleatorios, no se almacena en el repositorio y solo debe existir en los entornos de Laravel y la pasarela.

## Protección antirrepetición

Las rutas de escritura (`estado-conexion`, `chats-descubiertos` y `eventos`) registran el identificador de solicitud, marca de tiempo, hash del cuerpo, ruta y fecha de recepción en `mensajero_solicitudes_pasarela`. El identificador tiene unicidad en SQL Server, por lo que la protección sobrevive reinicios de Laravel, Redis y la pasarela.

`chats-autorizados` es deliberadamente de solo lectura: valida la misma firma HMAC y ventana temporal, pero no persiste cada identificador de sondeo para evitar crecimiento continuo de la tabla. Repetir esa consulta dentro de la ventana no modifica estado ni concede autorizaciones.

## Rechazos de seguridad

- Firma, identificador o marca de tiempo inválidos: `401`.
- Identificador de escritura ya utilizado: `409`.
- Secreto de pasarela no configurado: `503`.
- Carga JSON válida en firma pero inválida para el contrato: `422`.
- `clave_conexion` desconocida: `404`.

## Regla de confianza

HMAC demuestra que la petición procede de una pasarela que conoce el secreto; no concede permiso para capturar todos sus chats. La autorización de negocio permanece en `mensajero_chats_monitoreados.es_monitoreado` y es denegada por defecto.

La pasarela debe refrescar `chats-autorizados` y aplicar esa lista localmente antes de descargar multimedia o realizar procesamiento costoso. Laravel vuelve a comprobar la autorización al recibir el evento, por lo que existen dos barreras independientes.
