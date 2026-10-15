# Arquitectura de Mensajero

## Enfoque

Mensajero es un monolito modular Laravel 13 / PHP 8.4 con Microsoft SQL Server como base operativa, Redis para colas/caché/sesiones temporales y Flysystem para archivos privados. La puerta de enlace WhatsApp será un servicio Node.js/TypeScript autohospedado por MILAC y aislado detrás de un contrato propio.

## Flujo principal

```text
WhatsApp Business
        ↓
Puerta de enlace autohospedada
        ↓
Identificación mínima de conversación
        ↓
Lista blanca
   ┌────┴────┐
 ignorar   aceptar
             ↓
Evidencia cruda + bandeja de salida
      misma transacción SQL
             ↓
Normalización / mensajes originales
             ↓
Multimedia / tiempo real / IA
```

## Garantías

- La lista blanca se evalúa antes de persistir contenido operativo.
- Los adhesivos se descartan.
- Evidencia cruda no se sobrescribe por normalización ni IA.
- Redis no es la única copia de trabajo pendiente.
- Una caída de Gemini no detiene captura ni consulta de originales.
- Los archivos no se guardan dentro de SQL Server.
- La nomenclatura del dominio es española; solo se conservan términos externos impuestos por protocolos o herramientas.

## Módulos

- **Conexiones:** estado de WhatsApp y reconexión.
- **Captura:** lista blanca, evidencia cruda, deduplicación y bandeja de salida.
- **Mensajes:** proyección consultable de mensajes originales.
- **Multimedia:** archivos privados, metadatos y reintentos.
- **Inteligencia:** contexto incremental, Gemini y eventos.
- **Auditoría:** cambios, actores y trazabilidad.
- **Identidad:** integración corporativa futura y permisos por conversación.
