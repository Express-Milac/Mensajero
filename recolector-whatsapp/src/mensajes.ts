function comoRegistro(valor: unknown): Record<string, unknown> | null {
  return typeof valor === 'object' && valor !== null
    ? valor as Record<string, unknown>
    : null;
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

  if ('stickerMessage' in contenido) {
    return 'sticker';
  }

  if ('imageMessage' in contenido) {
    return 'foto';
  }

  if ('videoMessage' in contenido) {
    return 'video';
  }

  if ('audioMessage' in contenido) {
    return 'audio';
  }

  if ('documentMessage' in contenido) {
    return 'documento';
  }

  if ('conversation' in contenido || 'extendedTextMessage' in contenido) {
    return 'texto';
  }

  if ('locationMessage' in contenido || 'liveLocationMessage' in contenido) {
    return 'ubicacion';
  }

  if ('contactMessage' in contenido || 'contactsArrayMessage' in contenido) {
    return 'contacto';
  }

  if ('reactionMessage' in contenido) {
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
