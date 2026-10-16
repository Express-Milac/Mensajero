export type EstadoConexion =
  | 'conectando'
  | 'requiere_vinculacion'
  | 'conectado'
  | 'desconectado'
  | 'error';

export interface ChatDescubierto {
  idChatProveedor: string;
  tipoChat: 'grupo' | 'individual' | 'otro';
  nombreGrupo: string | null;
  esActivo: boolean;
}

export interface FuenteMultimediaProveedor {
  tipoMime: string | null;
  nombreOriginal: string | null;
  abrirFlujo: () => Promise<AsyncIterable<Uint8Array>>;
}

export interface MensajeProveedor {
  chatId: string;
  tipoEvento: 'mensaje' | 'edicion' | 'eliminacion' | 'reaccion' | 'otro';
  tipoMensaje: string;
  eventoExternoId: string | null;
  mensajeProveedorId: string | null;
  remitenteProveedorId: string | null;
  mensajeCitadoProveedorId: string | null;
  mensajeObjetivoProveedorId: string | null;
  contenidoCrudo: string;
  recibidoEn: string;
  multimedia: FuenteMultimediaProveedor | null;
}

export interface ManejadoresWhatsapp {
  alQr: (qr: string) => void;
  alEstado: (estado: EstadoConexion, detalles?: Record<string, unknown>) => Promise<void>;
  alGrupos: (grupos: ChatDescubierto[]) => Promise<void>;
  alMensaje: (mensaje: MensajeProveedor) => Promise<void>;
}

export interface AdaptadorWhatsapp {
  iniciar(manejadores: ManejadoresWhatsapp): Promise<void>;
  sincronizarGrupos(): Promise<void>;
  detener(): Promise<void>;
}
