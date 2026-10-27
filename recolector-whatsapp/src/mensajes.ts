function comoRegistro(valor: unknown): Record<string, unknown> | null {
  return typeof valor === 'object' && valor !== null
    ? valor as Record<string, unknown>
    : null;
}

function tieneValor(registro: Record<string, unknown>, clave: string): boolean {
  const valor = registro[clave];
  return valor !== null && valor !== undefined;
}

function cadenaNoVacia(valor: unknown): string | null {
  return typeof valor === 'string' && valor.trim() !== '' ? valor : null;
}

function idDesdeClave(valor: unknown): string | null {
  return cadenaNoVacia(comoRegistro(valor)?.id);
}

function tipoProtocoloCoincide(valor: unknown, numero: number, nombre: string): boolean {
  return valor === numero || valor === nombre;
}

function contenidoEditado(valor: unknown): Record<string, unknown> | null {
  const registro = comoRegistro(valor);

  if (registro === null) {
    return null;
  }

  return comoRegistro(registro.message) ?? registro;
}

function extraerMensajeCitado(valor: unknown): string | null {
  const contenido = desarrollarContenido(valor);

  if (contenido === null) {
    return null;
  }

  const contextoDirecto = comoRegistro(contenido.contextInfo);
  const directo = cadenaNoVacia(contextoDirecto?.stanzaId);

  if (directo !== null) {
    return directo;
  }

  for (const parte of Object.values(contenido)) {
    const registro = comoRegistro(parte);
    const contexto = comoRegistro(registro?.contextInfo);
    const stanzaId = cadenaNoVacia(contexto?.stanzaId);

    if (stanzaId !== null) {
      return stanzaId;
    }
  }

  return null;
}

export type TipoEventoWhatsapp = 'mensaje' | 'edicion' | 'eliminacion' | 'reaccion' | 'otro';

export interface SemanticaMensajeWhatsapp {
  tipoEvento: TipoEventoWhatsapp;
  tipoMensaje: string;
  mensajeCitadoProveedorId: string | null;
  mensajeObjetivoProveedorId: string | null;
}

/**
 * `secretEncryptedMessage` es una envoltura interna de sincronización/protocolo.
 * Baileys puede emitirla por `messages.upsert`, mientras que la edición ya
 * descifrada se publica por `messages.update`. No representa por sí misma un
 * mensaje de usuario que debamos persistir como conversación.
 */
export function esContenidoInternoWhatsapp(valor: unknown): boolean {
  const raiz = comoRegistro(valor);

  return raiz !== null && tieneValor(raiz, 'secretEncryptedMessage');
}

export function desarrollarContenido(valor: unknown): Record<string, unknown> | null {
  let actual = comoRegistro(valor);

  for (let i = 0; i < 8 && actual !== null; i += 1) {
    const envolturas = [
      'ephemeralMessage',
      'viewOnceMessage',
      'viewOnceMessageV2',
      'viewOnceMessageV2Extension',
      'documentWithCaptionMessage',
      'editedMessage',
      'associatedChildMessage',
    ];

    let siguiente: Record<string, unknown> | null = null;

    for (const clave of envolturas) {
      const envoltura = comoRegistro(actual[clave]);
      const mensaje = comoRegistro(envoltura?.message);

      if (mensaje !== null) {
        siguiente = mensaje;
        break;
      }
    }

    if (siguiente === null) {
      const protocolo = comoRegistro(actual.protocolMessage);
      const editado = contenidoEditado(protocolo?.editedMessage);

      if (editado !== null) {
        siguiente = editado;
      }
    }

    if (siguiente === null) {
      return actual;
    }

    actual = siguiente;
  }

  return actual;
}

export function clasificarTipoMensaje(valor: unknown): string {
  const contenido = desarrollarContenido(valor);

  if (contenido === null) {
    return 'desconocido';
  }

  // Los objetos protobuf de Baileys pueden exponer propiedades presentes con valor null.
  // No basta con comprobar `clave in objeto`, porque eso puede clasificar texto como sticker.
  if (tieneValor(contenido, 'stickerMessage')) {
    return 'sticker';
  }

  if (tieneValor(contenido, 'imageMessage')) {
    return 'foto';
  }

  if (tieneValor(contenido, 'videoMessage')) {
    return 'video';
  }

  if (tieneValor(contenido, 'audioMessage')) {
    return 'audio';
  }

  if (tieneValor(contenido, 'documentMessage')) {
    return 'documento';
  }

  if (tieneValor(contenido, 'conversation') || tieneValor(contenido, 'extendedTextMessage')) {
    return 'texto';
  }

  if (tieneValor(contenido, 'locationMessage') || tieneValor(contenido, 'liveLocationMessage')) {
    return 'ubicacion';
  }

  if (tieneValor(contenido, 'contactMessage') || tieneValor(contenido, 'contactsArrayMessage')) {
    return 'contacto';
  }

  if (tieneValor(contenido, 'reactionMessage')) {
    return 'reaccion';
  }

  return 'otro';
}

export function analizarSemanticaMensaje(valor: unknown): SemanticaMensajeWhatsapp {
  const raiz = comoRegistro(valor);

  if (raiz === null) {
    return {
      tipoEvento: 'otro',
      tipoMensaje: 'desconocido',
      mensajeCitadoProveedorId: null,
      mensajeObjetivoProveedorId: null,
    };
  }

  const protocolo = comoRegistro(raiz.protocolMessage);

  if (protocolo !== null) {
    const objetivo = idDesdeClave(protocolo.key);
    const editado = contenidoEditado(protocolo.editedMessage);
    const esEdicion = editado !== null
      || tipoProtocoloCoincide(protocolo.type, 14, 'MESSAGE_EDIT');

    if (esEdicion) {
      return {
        tipoEvento: 'edicion',
        tipoMensaje: editado === null ? 'otro' : clasificarTipoMensaje(editado),
        mensajeCitadoProveedorId: editado === null ? null : extraerMensajeCitado(editado),
        mensajeObjetivoProveedorId: objetivo,
      };
    }

    if (tipoProtocoloCoincide(protocolo.type, 0, 'REVOKE')) {
      return {
        tipoEvento: 'eliminacion',
        tipoMensaje: 'eliminacion',
        mensajeCitadoProveedorId: null,
        mensajeObjetivoProveedorId: objetivo,
      };
    }

    return {
      tipoEvento: 'otro',
      tipoMensaje: 'otro',
      mensajeCitadoProveedorId: null,
      mensajeObjetivoProveedorId: objetivo,
    };
  }

  const envolturaEditada = comoRegistro(raiz.editedMessage);

  if (envolturaEditada !== null) {
    const editado = contenidoEditado(envolturaEditada);

    return {
      tipoEvento: 'edicion',
      tipoMensaje: editado === null ? 'otro' : clasificarTipoMensaje(editado),
      mensajeCitadoProveedorId: editado === null ? null : extraerMensajeCitado(editado),
      mensajeObjetivoProveedorId: idDesdeClave(envolturaEditada.key),
    };
  }

  const contenido = desarrollarContenido(valor);
  const reaccion = comoRegistro(contenido?.reactionMessage);

  if (reaccion !== null) {
    return {
      tipoEvento: 'reaccion',
      tipoMensaje: 'reaccion',
      mensajeCitadoProveedorId: null,
      mensajeObjetivoProveedorId: idDesdeClave(reaccion.key),
    };
  }

  return {
    tipoEvento: 'mensaje',
    tipoMensaje: clasificarTipoMensaje(valor),
    mensajeCitadoProveedorId: extraerMensajeCitado(valor),
    mensajeObjetivoProveedorId: null,
  };
}

export function fechaIsoDesdeMarca(valor: unknown): string {
  let segundos: number | null = null;

  if (typeof valor === 'number') {
    segundos = valor;
  } else if (typeof valor === 'string' && /^\d+$/.test(valor)) {
    segundos = Number(valor);
  } else if (typeof valor === 'bigint') {
    segundos = Number(valor);
  } else {
    const registro = comoRegistro(valor);
    const toNumber = registro?.toNumber;

    if (typeof toNumber === 'function') {
      segundos = Number(toNumber.call(valor));
    }
  }

  if (segundos === null || !Number.isFinite(segundos) || segundos <= 0) {
    return new Date().toISOString();
  }

  return new Date(segundos * 1000).toISOString();
}
