import { createDecipheriv, createHmac } from 'node:crypto';
import { jidNormalizedUser, proto } from '@whiskeysockets/baileys';
import type {
  ContextoEdicionDisponible,
  ContextoEdicionParaGuardar,
} from './almacen-contexto-ediciones-durable.js';

interface ClaveMensajeWhatsapp {
  remoteJid?: string | null;
  remoteJidAlt?: string | null;
  id?: string | null;
  fromMe?: boolean | null;
  participant?: string | null;
  participantAlt?: string | null;
}

interface MensajeWhatsappParcial {
  key?: ClaveMensajeWhatsapp;
  message?: unknown;
  messageTimestamp?: unknown;
}

interface EnvolturaEdicionCifrada {
  targetMessageKey?: ClaveMensajeWhatsapp;
  encPayload?: Uint8Array | null;
  encIv?: Uint8Array | null;
  secretEncType?: number | null;
}

export interface ResultadoEdicionCifrada {
  actualizacion: {
    key: ClaveMensajeWhatsapp;
    update: {
      message: {
        editedMessage: {
          message: unknown;
        };
      };
      messageTimestamp: unknown;
    };
  } | null;
  causaFallo: string | null;
  mensajeObjetivoProveedorId: string | null;
}

function comoRegistro(valor: unknown): Record<string, unknown> | null {
  return typeof valor === 'object' && valor !== null
    ? valor as Record<string, unknown>
    : null;
}

function comoCadena(valor: unknown): string | null {
  return typeof valor === 'string' && valor !== '' ? valor : null;
}

function comoBinario(valor: unknown): Uint8Array | null {
  if (valor instanceof Uint8Array) {
    return valor;
  }

  if (Buffer.isBuffer(valor)) {
    return new Uint8Array(valor);
  }

  return null;
}

function buscarSecretoMensaje(valor: unknown): Uint8Array | null {
  let actual = comoRegistro(valor);

  for (let nivel = 0; nivel < 8 && actual !== null; nivel += 1) {
    const contexto = comoRegistro(actual.messageContextInfo);
    const secreto = comoBinario(contexto?.messageSecret);

    if (secreto?.byteLength === 32) {
      return secreto;
    }

    const envolturas = [
      'ephemeralMessage',
      'viewOnceMessage',
      'viewOnceMessageV2',
      'viewOnceMessageV2Extension',
      'documentWithCaptionMessage',
      'deviceSentMessage',
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

    actual = siguiente;
  }

  return null;
}

function obtenerEnvoltura(valor: unknown): EnvolturaEdicionCifrada | null {
  const contenido = comoRegistro(valor);
  const envoltura = comoRegistro(contenido?.secretEncryptedMessage);

  if (envoltura === null) {
    return null;
  }

  return envoltura as EnvolturaEdicionCifrada;
}

function normalizarCandidatoJid(valor: unknown): string | null {
  const jid = comoCadena(valor);

  if (
    jid === null
    || jid.endsWith('@g.us')
    || jid.endsWith('@broadcast')
  ) {
    return null;
  }

  try {
    return jidNormalizedUser(jid);
  } catch {
    return jid;
  }
}

function candidatosUnicos(valores: unknown[]): string[] {
  const candidatos = valores
    .map(normalizarCandidatoJid)
    .filter((valor): valor is string => valor !== null);

  return [...new Set(candidatos)];
}

function hmacSha256(datos: Uint8Array, clave: Uint8Array): Buffer {
  return createHmac('sha256', Buffer.from(clave))
    .update(Buffer.from(datos))
    .digest();
}

function descifrarCarga(
  carga: Uint8Array,
  iv: Uint8Array,
  clave: Uint8Array,
): Buffer {
  if (carga.byteLength <= 16) {
    throw new Error('La carga cifrada de la edición no contiene etiqueta GCM.');
  }

  const datos = Buffer.from(carga);
  const textoCifrado = datos.subarray(0, datos.length - 16);
  const etiqueta = datos.subarray(datos.length - 16);
  const descifrador = createDecipheriv('aes-256-gcm', Buffer.from(clave), Buffer.from(iv));
  descifrador.setAuthTag(etiqueta);

  return Buffer.concat([
    descifrador.update(textoCifrado),
    descifrador.final(),
  ]);
}

function derivarClaveEdicion(
  secretoMensaje: Uint8Array,
  mensajeObjetivoProveedorId: string,
  remitenteOriginal: string,
  editor: string,
): Buffer {
  const informacion = Buffer.concat([
    Buffer.from(mensajeObjetivoProveedorId, 'utf8'),
    Buffer.from(remitenteOriginal, 'utf8'),
    Buffer.from(editor, 'utf8'),
    Buffer.from('Message Edit', 'utf8'),
    Buffer.from([1]),
  ]);
  const extraida = hmacSha256(secretoMensaje, new Uint8Array(32));

  return hmacSha256(informacion, extraida);
}

export function esEdicionCifradaWhatsapp(valorContenido: unknown): boolean {
  const envoltura = obtenerEnvoltura(valorContenido);

  return envoltura?.secretEncType
    === proto.Message.SecretEncryptedMessage.SecretEncType.MESSAGE_EDIT;
}

export function extraerMensajeObjetivoEdicionCifrada(valorContenido: unknown): string | null {
  const envoltura = obtenerEnvoltura(valorContenido);
  return comoCadena(envoltura?.targetMessageKey?.id);
}

export function extraerContextoEdicionOriginal(
  valor: unknown,
): ContextoEdicionParaGuardar | null {
  const mensaje = comoRegistro(valor) as MensajeWhatsappParcial | null;
  const chatId = comoCadena(mensaje?.key?.remoteJid);
  const mensajeProveedorId = comoCadena(mensaje?.key?.id);

  if (
    chatId === null
    || !chatId.endsWith('@g.us')
    || mensajeProveedorId === null
    || mensaje?.message === null
    || mensaje?.message === undefined
    || esEdicionCifradaWhatsapp(mensaje.message)
  ) {
    return null;
  }

  const secretoMensaje = buscarSecretoMensaje(mensaje.message);

  if (secretoMensaje === null) {
    return null;
  }

  const remitentesProveedor = candidatosUnicos([
    mensaje.key?.participant,
    mensaje.key?.participantAlt,
  ]);

  return {
    chatId,
    mensajeProveedorId,
    desdeMiCuenta: mensaje.key?.fromMe === true,
    remitentesProveedor,
    secretoMensaje,
  };
}

export function descifrarEdicionCifradaWhatsapp(
  valor: unknown,
  contexto: ContextoEdicionDisponible,
  identidadesCuentaLocal: string[] = [],
): ResultadoEdicionCifrada {
  const mensaje = comoRegistro(valor) as MensajeWhatsappParcial | null;
  const envoltura = obtenerEnvoltura(mensaje?.message);
  const objetivo = comoCadena(envoltura?.targetMessageKey?.id);

  if (envoltura === null || objetivo === null) {
    return {
      actualizacion: null,
      causaFallo: 'envoltura_invalida',
      mensajeObjetivoProveedorId: objetivo,
    };
  }

  if (
    envoltura.secretEncType
      !== proto.Message.SecretEncryptedMessage.SecretEncType.MESSAGE_EDIT
    || contexto.mensajeProveedorId !== objetivo
  ) {
    return {
      actualizacion: null,
      causaFallo: 'tipo_u_objetivo_invalido',
      mensajeObjetivoProveedorId: objetivo,
    };
  }

  const carga = comoBinario(envoltura.encPayload);
  const iv = comoBinario(envoltura.encIv);

  if (carga === null || iv?.byteLength !== 12 || contexto.secretoMensaje.byteLength !== 32) {
    return {
      actualizacion: null,
      causaFallo: 'material_criptografico_invalido',
      mensajeObjetivoProveedorId: objetivo,
    };
  }

  const claveMensaje = mensaje?.key ?? {};
  const claveObjetivo = envoltura.targetMessageKey ?? {};
  const candidatosEditor = candidatosUnicos([
    claveMensaje.participant,
    claveMensaje.participantAlt,
    claveObjetivo.participant,
    claveObjetivo.participantAlt,
    ...(claveMensaje.fromMe ? identidadesCuentaLocal : []),
  ]);
  const candidatosOriginal = candidatosUnicos([
    ...contexto.remitentesProveedor,
    claveObjetivo.participant,
    claveObjetivo.participantAlt,
    ...(contexto.desdeMiCuenta ? identidadesCuentaLocal : []),
  ]);

  if (candidatosEditor.length === 0 || candidatosOriginal.length === 0) {
    return {
      actualizacion: null,
      causaFallo: 'identidades_insuficientes',
      mensajeObjetivoProveedorId: objetivo,
    };
  }

  for (const remitenteOriginal of candidatosOriginal) {
    for (const editor of candidatosEditor) {
      try {
        const clave = derivarClaveEdicion(
          contexto.secretoMensaje,
          objetivo,
          remitenteOriginal,
          editor,
        );
        const textoPlano = descifrarCarga(carga, iv, clave);
        const descifrado = proto.Message.decode(textoPlano);
        const protocolo = descifrado.protocolMessage;

        if (
          protocolo?.type !== proto.Message.ProtocolMessage.Type.MESSAGE_EDIT
          || !protocolo.editedMessage
          || (protocolo.key?.id && protocolo.key.id !== objetivo)
        ) {
          continue;
        }

        return {
          actualizacion: {
            key: {
              ...claveMensaje,
              id: objetivo,
              participant: claveObjetivo.participant
                ?? claveMensaje.participant
                ?? undefined,
              participantAlt: claveObjetivo.participantAlt
                ?? claveMensaje.participantAlt
                ?? undefined,
            },
            update: {
              message: {
                editedMessage: {
                  message: protocolo.editedMessage,
                },
              },
              messageTimestamp: mensaje?.messageTimestamp,
            },
          },
          causaFallo: null,
          mensajeObjetivoProveedorId: objetivo,
        };
      } catch {
        // AES-GCM autentica cada combinación. Una identidad incorrecta debe fallar
        // sin producir texto plano; se prueban únicamente candidatos observados.
      }
    }
  }

  return {
    actualizacion: null,
    causaFallo: 'autenticacion_gcm_fallida',
    mensajeObjetivoProveedorId: objetivo,
  };
}
