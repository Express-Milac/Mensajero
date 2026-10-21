# Recolector WhatsApp MILAC — POC controlado

Este componente existe para probar la captura automática de **grupos normales existentes** mediante un número corporativo secundario y dedicado. No debe vincularse al número principal de MILAC.

## Alcance del POC

- usa una cuenta/número recolector que participa como un usuario más en los grupos de prueba;
- vincula una sesión multidispositivo mediante Baileys únicamente en este canal aislado;
- descubre grupos y los sincroniza con Mensajero;
- solo entrega mensajes de grupos autorizados en la lista permitida;
- descarta stickers;
- conserva texto y cualquier tipo desconocido como evento crudo;
- persiste cada mensaje autorizado en una **cola local durable en disco antes de intentar enviarlo a Laravel**;
- reintenta después de caídas de red/Laravel sin borrar tareas pendientes;
- registra periodos donde la integridad de captura no puede confirmarse;
- mantiene sesión, cola y cachés bajo `datos/`, fuera de Git.

## Advertencia

Baileys no es una API oficial de Meta. Este POC se autoriza exclusivamente porque el número recolector es un activo secundario y reemplazable de la empresa. No existe garantía de que Meta no limite o suspenda esa cuenta. La integración oficial Cloud API permanece en Mensajero para los flujos compatibles.

La versión `6.7.24` queda fijada porque ya fue validada previamente en el entorno local de este proyecto. Antes de producción deberá reevaluarse la versión, el almacenamiento de credenciales y las incidencias vigentes de la biblioteca.

### Corrección local de recepción

Baileys 6.7.24 contiene un fallo conocido en la interpretación del atributo de protocolo `offline`: el valor de texto `"0"` puede tratarse como verdadero y desviar mensajes en tiempo real hacia la cola de mensajes fuera de línea. El síntoma puede ser una sesión conectada y capaz de consultar grupos, pero sin eventos `messages.upsert` entregados a la aplicación.

Mensajero aplica durante `npm install` una corrección mínima y verificable sobre `node_modules/@whiskeysockets/baileys/lib/Socket/messages-recv.js` mediante `scripts/aplicar-parche-baileys.mjs`. La instalación falla si la dependencia deja de coincidir con los patrones esperados; de esa forma una actualización no puede omitir silenciosamente la corrección. El parche es temporal y deberá retirarse cuando una versión upstream validada incorpore una solución equivalente.

## Preparación

Desde la raíz del repositorio:

```powershell
php artisan mensajero:registrar-conexion-recolector recolector-pruebas --nombre="Recolector MILAC pruebas"
```

Dentro de este directorio:

```powershell
Copy-Item .env.example .env
npm install
npm run verificar
npm run pruebas
```

Configurar `.env` sin compartir el secreto:

```text
MENSAJERO_URL=http://127.0.0.1:8000
MENSAJERO_CLAVE_CONEXION=recolector-pruebas
MENSAJERO_PASARELA_SECRETO=<mismo secreto de Laravel>
RECOLECTOR_RUTA_DATOS=./datos
```

Laravel debe estar ejecutándose y usar una base `_test` durante el POC.

## Primera vinculación

Ejecutar:

```powershell
npm run desarrollo
```

Aparecerá un QR. Escanearlo únicamente desde el **número recolector dedicado** en WhatsApp → Dispositivos vinculados.

Al conectarse, el recolector enviará la lista de grupos a Mensajero. Abrir `/grupos`, autorizar solamente el grupo de prueba y esperar la siguiente actualización de la lista permitida (máximo configurado: 15 segundos).

## Durabilidad

`datos/cola/pendientes/` contiene trabajo que Laravel todavía no ha confirmado. Un archivo se elimina solamente después de recibir respuesta HTTP satisfactoria. Reiniciar Node o perder Internet no elimina esas tareas.

`datos/estado-ejecucion.json` registra el último latido. Si el proceso anterior terminó sin cierre limpio, al reiniciar se genera un periodo de integridad no confirmada.

`datos/sesion/` contiene credenciales criptográficas de WhatsApp. Debe protegerse como un secreto y nunca copiarse a Git.

## Multimedia y validación operativa pendiente

El recolector ya incorpora una cola durable de descargas y un almacén
privado para foto, video, audio y documento. La captura registra
metadatos de ruta, tamaño y SHA-256 en SQL Server, y la interfaz Laravel
puede visualizar y entregar archivos de forma privada tras revalidar
el acceso del grupo y la integridad. La prueba de integración Laravel
aprobó **2 casos y 43 aserciones** el 20/10/2026.

**La integración real con WhatsApp y la transferencia física del archivo
todavía requieren una prueba controlada**: confirmar que Node y Laravel
utilicen la misma raíz `RECOLECTOR_RUTA_ALMACENAMIENTO` /
`MENSAJERO_RUTA_ALMACENAMIENTO`, con un grupo aislado y un número
secundario. El módulo no se considera certificado para producción solo
por haber aprobado pruebas de integración. No se vinculará el número
principal de MILAC y no se capturarán grupos operativos reales antes
de aprobar conectividad, almacenamiento y políticas de acceso.

Este POC no se considera fuente directiva hasta aprobar pruebas de carga,
reinicios, desconexión, recuperación y reconciliación.

## Identificación de remitentes: nombre de contacto y celular

El recolector ahora conserva **por mensaje autorizado** la identidad que
WhatsApp realmente hace disponible, en tres campos opcionales:

- `nombre_remitente`: nombre de la agenda de contactos de la sesión
  vinculada, o nombre de perfil (`pushName`) si no hay nombre guardado.
- `fuente_nombre_remitente`: `contacto` o `perfil` para diferenciar el
  origen de ese nombre.
- `telefono_remitente`: número solo cuando se observa un JID telefónico
  (`@s.whatsapp.net`) o una correspondencia en el mismo mensaje entre
  `participant` y `participantAlt`/`participantPn` de WhatsApp.

El adaptador escucha `contacts.upsert`, `contacts.update` y
`messaging-history.set` para los contactos que WhatsApp proporcione.
Mantiene una caché temporal acotada; **no envía la agenda completa
a Laravel**, ni consulta los chats ajenos, ni instala servicios adicionales.
Los datos se mandan solo con los mensajes de **grupos autorizados**.

Con identificadores privados `@lid`, el número puede ser desconocido.
Los dígitos de un LID **nunca son tratados como celular**.
El nombre del perfil no equivale a verificación de identidad empresarial.
Si WhatsApp no proporciona nombre o número fiable, Mensajero indicará
«Persona sin identificar» y permitirá asignar nombre interno.

La integración conserva `contenido_crudo` y su SHA-256 originales,
utiliza el flujo firmado/durable actual y es retrocompatible: las tareas
pendientes que no tienen estos campos siguen siendo aceptadas.

### Activación segura en el entorno local de ensayo

1. Actualizar Laravel y aplicar **primero** la migración
   `2026_10_21_000001_identificar_remitentes_en_eventos.php`
   únicamente en `mensajero_test`.
2. Ejecutar `npm run verificar` y
   `npm run pruebas` en `recolector-whatsapp`.
3. Detener **ordenadamente la instancia actual** del recolector de
   pruebas y volver a iniciarla con el comando habitual
   `npm run desarrollo`. No abrir una segunda sesión o instancia.
4. Enviar un mensaje sintético desde un participante conocido del
   grupo de pruebas autorizado, y revisar su remitente en el
   historial y directorio de Mensajero.

No reiniciar para rescatar mensajes de producción ni alterar el
buzón histórico. Los mensajes ya conservados siguen intactos; este
cambio **no es un proceso retroactivo de descubrimiento de números**.


## Diagnóstico no destructivo de la lista de grupos autorizados

Si SQL Server indica que un grupo está autorizado, pero
`datos/cola/pendientes` no recibe ningún mensaje y el grupo
no está en `datos/chats-autorizados.json`, **no edites la caché**
ni ejecutes `diagnosticar-cola` o `drenar-cola`. Esos dos últimos
comandos envían trabajos; el drenaje además elimina los confirmados.

Desde la **raíz de Mensajero**, define temporalmente el identificador
del grupo objetivo obtenido directamente de la BD de prueba:

```powershell
$env:MENSAJERO_JID_DIAGNOSTICO = (php artisan tinker --execute="echo \App\Models\ChatMonitoreado::find(10020)?->id_chat_proveedor;").Trim()
Push-Location .\recolector-whatsapp
try {
    npm run verificar
    npm run diagnosticar-autorizaciones
} finally {
    Pop-Location
    Remove-Item Env:\MENSAJERO_JID_DIAGNOSTICO -ErrorAction SilentlyContinue
}
```

Este comando firma una **consulta de solo lectura** al endpoint
`/api/pasarela/v1/chats-autorizados` y la compara con la caché
del recolector. Solo imprime presencia booleana, cantidades, coincidencia
y host, sin nombres, JID, claves o contactos. No inicia WhatsApp,
no modifica permisos, no entrega ni confirma tareas de la cola.

Interpretación:
- `grupo_en_servidor=true` y `grupo_en_cache=false`: la caché
  está retrasada respecto a la respuesta HTTP; investigar el ciclo
  de autorizaciones o su ruta real en el proceso.
- `grupo_en_servidor=false` pero BD autorizada: la instancia HTTP
  podría consultar otra base o configuración; verificar URL/servidor
  antes de modificar registros.
- `consulta_laravel=fallida`: revisar el estado HTTP y la conectividad
  hacia Laravel o la firma de la pasarela.
- Ambas presencias `true`: investigar recepción desde WhatsApp,
  sus reconexiones y filtros por fecha.
