# Pasarela WhatsApp de Mensajero

Servicio Node.js/TypeScript autohospedado para vincular una cuenta de WhatsApp como dispositivo complementario y entregar eventos al núcleo Laravel de Mensajero mediante HMAC.

## Alcance actual

- Vinculación por QR.
- Persistencia local de sesión para desarrollo/prueba controlada.
- Reconexión automática si la sesión no fue cerrada desde WhatsApp.
- Descubrimiento de grupos y sincronización con Laravel.
- Consulta periódica de la lista permitida.
- Descarte local de chats no autorizados y de stickers.
- Entrega HMAC de eventos autorizados.
- Sin descarga de multimedia todavía: en este hito se conservan metadatos crudos del evento; la descarga y almacenamiento privado serán el siguiente incremento.

## Advertencia operativa

La integración usa Baileys, una biblioteca no oficial de WhatsApp Web. No está afiliada ni autorizada por WhatsApp. Por esa razón Mensajero mantiene un adaptador neutral y la primera prueba debe hacerse con una cuenta controlada, nunca con el número empresarial de MILAC.

## Preparación

1. En Laravel, registrar una conexión:

```bash
php artisan mensajero:registrar-conexion-whatsapp prueba-local --nombre="Cuenta de prueba"
```

2. Configurar el mismo secreto HMAC en Laravel (`WHATSAPP_PASARELA_SECRETO`) y en `pasarela-whatsapp/.env` (`MENSAJERO_PASARELA_SECRETO`). Debe tener al menos 32 caracteres aleatorios.

3. Copiar el archivo de ejemplo:

```powershell
Copy-Item .env.example .env
```

4. Instalar dependencias:

```bash
npm install
```

5. Validar:

```bash
npm run verificar
npm run pruebas
```

6. Con Laravel ejecutándose en `http://127.0.0.1:8000`, iniciar la pasarela:

```bash
npm run desarrollo
```

La consola mostrará un QR. Escanéalo desde **Dispositivos vinculados** de la cuenta de WhatsApp de prueba.

## Flujo esperado

1. WhatsApp abre la sesión.
2. La pasarela obtiene los grupos participantes.
3. Laravel los crea o actualiza con `es_monitoreado=false` por defecto.
4. En `/grupos`, se activa únicamente el grupo de prueba.
5. La pasarela refresca la lista permitida cada 30 segundos.
6. Un mensaje nuevo del grupo autorizado se firma y entrega a `/api/pasarela/v1/eventos`.
7. Mensajes de otros chats y stickers se descartan antes de llamar a Laravel.

## Sesión

La carpeta indicada por `WHATSAPP_RUTA_SESION` contiene credenciales sensibles del dispositivo vinculado. Está ignorada por Git y no debe copiarse, publicarse ni compartirse. Para producción se revisará el almacenamiento de claves de sesión antes de vincular el número empresarial.
