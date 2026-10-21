/**
 * Resuelve metadatos de identidad aportados por WhatsApp, nunca inferidos
 * desde los números arbitrarios de un identificador privado LID.
 * Los contactos permanecen únicamente en memoria del recolector.
 */

export type FuenteNombreRemitente = 'contacto' | 'perfil';

export interface DatosIdentidadRemitente {
  nombreRemitente: string | null;
  fuenteNombreRemitente: FuenteNombreRemitente | null;
  telefonoRemitente: string | null;
}

interface ContactoDisponible {
  id?: string | null;
  name?: string | null;
  notify?: string | null;
  verifiedName?: string | null;
  phoneNumber?: string | null;
}

interface ContactoGuardado {
  nombre: string | null;
  fuente: FuenteNombreRemitente | null;
  telefono: string | null;
}

function registro(valor: unknown): Record<string, unknown> | null {
  return typeof valor === 'object' && valor !== null
    ? valor as Record<string, unknown>
    : null;
}

function nombreSeguro(valor: unknown): string | null {
  if (typeof valor !== 'string') {
    return null;
  }

  const nombre = valor.trim().replace(/[\u0000-\u001f\u007f]/g, '');
  return nombre.length > 0 ? nombre.slice(0, 120) : null;
}

export function telefonoDeJid(jid: unknown): string | null {
  if (typeof jid !== 'string') {
    return null;
  }

  const coincidencia = /^([0-9]{8,15})@s\.whatsapp\.net$/.exec(jid);
  return coincidencia?.[1] ?? null;
}

export class IdentidadesRecolector {
  private readonly contactos = new Map<string, ContactoGuardado>();

  public registrarContactos(contactos: readonly ContactoDisponible[]): void {
    for (const contacto of contactos) {
      const id = contacto.id;
      if (typeof id !== 'string' || !/@(?:lid|s\.whatsapp\.net)$/.test(id)) {
        continue;
      }

      const anterior = this.contactos.get(id);
      // name representa el nombre guardado en el dispositivo; notify
      // es el nombre de perfil y no prueba la identidad de la persona.
      const nombreGuardado = nombreSeguro(contacto.name);
      const nombrePerfil = nombreSeguro(contacto.verifiedName)
        ?? nombreSeguro(contacto.notify);
      const nombre = nombreGuardado ?? nombrePerfil ?? anterior?.nombre ?? null;
      const fuente = nombreGuardado !== null ? 'contacto'
        : nombrePerfil !== null ? 'perfil'
          : anterior?.fuente ?? null;
      const telefono = telefonoDeJid(id)
        ?? telefonoDeJid(contacto.phoneNumber)
        ?? anterior?.telefono ?? null;

      if (this.contactos.size >= 10000 && !this.contactos.has(id)) {
        // Cache de consulta acotada, sin persistir la agenda completa.
        const primero = this.contactos.keys().next().value;
        if (primero !== undefined) {
          this.contactos.delete(primero);
        }
      }

      this.contactos.set(id, { nombre, fuente, telefono });
    }
  }

  public resolver(mensaje: unknown, identificador: string | null): DatosIdentidadRemitente {
    const vacio: DatosIdentidadRemitente = {
      nombreRemitente: null,
      fuenteNombreRemitente: null,
      telefonoRemitente: null,
    };

    if (!identificador) {
      return vacio;
    }

    const objeto = registro(mensaje);
    const clave = registro(objeto?.key);
    const participante = clave?.participant;
    const participanteAlt = clave?.participantAlt;
    const participantePn = clave?.participantPn;

    // El par alternativo debe venir en LA MISMA clave del mensaje.
    // Nunca convertir los dígitos de @lid en un teléfono.
    const pertenece = identificador === participante
      || identificador === participanteAlt
      || identificador === participantePn;

    const alternativo = pertenece
      ? telefonoDeJid(participanteAlt) ?? telefonoDeJid(participantePn)
      : null;

    const contacto = this.contactos.get(identificador);
    const otros = [participante, participanteAlt, participantePn]
      .filter((jid): jid is string => typeof jid === 'string' && jid !== identificador);
    const alternoContacto = pertenece
      ? otros.map((jid) => this.contactos.get(jid)).find((dato) => dato?.nombre) ?? null
      : null;

    const nombrePerfil = nombreSeguro(objeto?.pushName);
    const preferido = contacto?.nombre ? contacto : alternoContacto;
    const nombre = preferido?.nombre ?? nombrePerfil;

    return {
      nombreRemitente: nombre ?? null,
      fuenteNombreRemitente: preferido?.nombre ? preferido.fuente : nombrePerfil ? 'perfil' : null,
      telefonoRemitente: telefonoDeJid(identificador)
        ?? alternativo
        ?? contacto?.telefono
        ?? alternoContacto?.telefono
        ?? null,
    };
  }
}
