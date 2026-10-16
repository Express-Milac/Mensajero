function comoRegistro(valor: unknown): Record<string, unknown> | null {
  return typeof valor === 'object' && valor !== null
    ? valor as Record<string, unknown>
    : null;
}

function tieneValor(registro: Record<string, unknown>, clave: string): boolean {
  const valor = registro[clave];
  return valor !== null && valor !== undefined;
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
