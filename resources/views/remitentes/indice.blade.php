<x-layout title="Remitentes">
    <style>
        /* Tarjetas de remitentes: estilos aislados de otras pantallas y del gestor de grupos. */
        .panorama-claro .catalogo-tarjetas.remitentes-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 252px), 1fr));
            align-items: stretch;
            gap: 12px;
        }

        .panorama-claro .remitentes-tarjetas .remitente-tarjeta {
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            border: 1px solid #dfe7ee;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(23, 47, 71, .035);
            overflow: hidden;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .panorama-claro .remitentes-tarjetas .remitente-tarjeta:hover,
        .panorama-claro .remitentes-tarjetas .remitente-tarjeta:focus-within {
            border-color: #e8b38e;
            box-shadow: 0 7px 20px rgba(23, 47, 71, .075);
        }

        .panorama-claro .remitentes-tarjetas .remitente-encabezado {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 14px 9px;
        }

        .panorama-claro .remitentes-tarjetas .remitente-avatar {
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #fff1e5;
            color: #e97730;
        }
        .panorama-claro .remitentes-tarjetas .remitente-avatar svg { width: 19px; height: 19px; }
        .panorama-claro .remitentes-tarjetas .remitente-identidad { min-width: 0; flex: 1; }
        .panorama-claro .remitentes-tarjetas .remitente-identidad h3 {
            margin: 0 0 4px;
            color: #173655;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .panorama-claro .remitentes-tarjetas .remitente-origen {
            display: block;
            max-width: 100%;
            color: #72859a;
            font-size: 10px;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .panorama-claro .remitentes-tarjetas .remitente-grupo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin: 0 14px;
            padding: 9px 10px;
            border: 1px solid #edf1f5;
            border-radius: 8px;
            background: #f8fafc;
            min-width: 0;
        }
        .panorama-claro .remitentes-tarjetas .remitente-grupo span,
        .panorama-claro .remitentes-tarjetas .remitente-dato span {
            color: #71859b;
            font-size: 10px;
            font-weight: 600;
            line-height: 1.25;
        }
        .panorama-claro .remitentes-tarjetas .remitente-grupo strong {
            color: #2d4b67;
            font-size: 11px;
            line-height: 1.3;
            font-weight: 750;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .panorama-claro .remitentes-tarjetas .remitente-datos {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(60px, 0.7fr);
            gap: 8px 10px;
            padding: 11px 14px 12px;
            margin-top: 0;
            flex: 1;
        }
        .panorama-claro .remitentes-tarjetas .remitente-dato {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 3px;
            min-width: 0;
        }
        .panorama-claro .remitentes-tarjetas .remitente-dato strong {
            display: block;
            max-width: 100%;
            color: #23405d;
            font-size: 11.5px;
            font-weight: 750;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .panorama-claro .remitentes-tarjetas .remitente-dato-fecha { grid-column: 1 / -1; }

        .panorama-claro .remitentes-tarjetas .remitente-acciones {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            align-items: stretch;
            margin-top: auto;
            padding: 10px 14px 12px;
            border-top: 1px solid #edf1f5;
            background: #fff;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 34px;
            min-width: 0;
            padding: 7px 6px;
            border-radius: 8px;
            font-size: 11px;
            line-height: 1.25;
            font-weight: 750;
            text-decoration: none;
            text-align: center;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion-principal {
            border: 1px solid #ffbb8f;
            background: #fff0e4;
            color: #ac4b15;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion-principal:hover {
            background: #ffe2cd;
            border-color: #ed9c69;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion-secundaria {
            border: 1px solid #dae4ed;
            background: #f8fafc;
            color: #2e516d;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion-secundaria:hover {
            background: #ecf2f7;
            border-color: #b9ccda;
        }
        .panorama-claro .remitentes-tarjetas .remitente-accion:focus-visible {
            outline: 2px solid #f47f38;
            outline-offset: 2px;
        }
        @media (max-width: 600px) {
            .panorama-claro .catalogo-tarjetas.remitentes-tarjetas { grid-template-columns: minmax(0, 1fr); }
        }
        @media (prefers-reduced-motion: reduce) {
            .panorama-claro .remitentes-tarjetas .remitente-tarjeta,
            .panorama-claro .remitentes-tarjetas .remitente-accion { transition: none; }
        }
    </style>

    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / PERSONAS</span>
            <h1>Identificar remitentes</h1>
            <p>Consulta quién participa en los grupos autorizados. Cuando WhatsApp no informa su nombre, puedes asignar uno interno sin modificar los mensajes conservados.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Volver a conversaciones →</a>
    </section>

    <section class="catalogo-seccion" aria-label="Personas detectadas">
        <div class="catalogo-titulo">
            <div>
                <h2>Remitentes detectados</h2>
                <p>{{ number_format($remitentes->total()) }} identificadores distintos{{ $grupoId !== null ? ' en el grupo seleccionado' : ' en los grupos autorizados' }}.</p>
            </div>
        </div>
        <form class="catalogo-filtros" method="get" action="{{ route('remitentes.indice') }}">
            <div>
                <label for="grupo">Filtrar por grupo</label>
                <select id="grupo" name="grupo">
                    <option value="">Todos los grupos autorizados</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo->id }}" @selected($grupoId === (int) $grupo->id)>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</option>
                    @endforeach
                </select>
            </div>
            <button class="catalogo-aplicar" type="submit">Aplicar</button>
            @if($grupoId !== null)<a class="catalogo-limpiar" href="{{ route('remitentes.indice') }}">Ver todos</a>@endif
        </form>

        <p class="catalogo-ayuda-gestor"><strong>Identificación:</strong> el nombre que aparece en WhatsApp es elegido por el participante y no equivale a identidad verificada. El nombre interno que asignes tendrá preferencia en el tablero, las conversaciones y el historial, incluso para mensajes anteriores. Los identificadores privados permanecen protegidos.</p>

        <div class="catalogo-tarjetas remitentes-tarjetas">
            @forelse($filas as $fila)
                <article class="catalogo-tarjeta remitente-tarjeta">
                    <div class="catalogo-tarjeta-encabezado remitente-encabezado">
                        <span class="catalogo-grupo-icono remitente-avatar" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                        </span>
                        <div class="catalogo-grupo-titulo remitente-identidad">
                            <h3 title="{{ $fila['nombre'] }}">{{ $fila['nombre'] }}</h3>
                            <span class="remitente-origen">{{ $fila['nombre_asignado'] ? 'Nombre interno asignado' : ($fila['fuente_nombre'] === 'contacto' ? 'Agenda del recolector' : ($fila['nombre_whatsapp'] ? 'Perfil de WhatsApp' : 'Pendiente de identificar')) }}</span>
                        </div>
                    </div>

                    <div class="remitente-grupo">
                        <span>Último grupo</span>
                        <strong title="{{ optional($fila['evento']->chat)->nombre_interno ?: (optional($fila['evento']->chat)->nombre_grupo ?: 'Grupo sin nombre') }}">{{ optional($fila['evento']->chat)->nombre_interno ?: (optional($fila['evento']->chat)->nombre_grupo ?: 'Grupo sin nombre') }}</strong>
                    </div>

                    <div class="remitente-datos">
                        <div class="remitente-dato remitente-dato-telefono">
                            <span>Celular</span>
                            <strong>{{ $fila['telefono'] ? '+'.$fila['telefono'] : 'No informado' }}</strong>
                        </div>
                        <div class="remitente-dato">
                            <span>Mensajes</span>
                            <strong>{{ number_format($fila['mensajes']) }}</strong>
                        </div>
                        <div class="remitente-dato remitente-dato-fecha">
                            <span>Última participación</span>
                            <strong>{{ ($fila['evento']->recibido_en !== null ? $fila['evento']->recibido_en->timezone(config('app.timezone'))->format('d/m/Y H:i') : 'Sin fecha') }}</strong>
                        </div>
                    </div>

                    <div class="catalogo-tarjeta-pie remitente-acciones">
                        <a class="remitente-accion remitente-accion-principal" href="{{ route('remitentes.editar', $fila['evento']->id) }}" aria-label="Identificar o corregir nombre de {{ $fila['nombre'] }}">Editar nombre <span aria-hidden="true">→</span></a>
                        <a class="remitente-accion remitente-accion-secundaria" href="{{ route('mensajes.evento', $fila['evento']->id) }}" aria-label="Ver mensaje original de {{ $fila['nombre'] }}">Ver mensaje <span aria-hidden="true">↗</span></a>
                    </div>
                </article>
            @empty
                <section class="catalogo-vacio"><h3>Sin remitentes identificables</h3><p>Todavía no se encontraron identificadores de remitentes en los grupos autorizados para este filtro.</p></section>
            @endforelse
        </div>
        @if($remitentes->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de remitentes">
                @if($remitentes->onFirstPage())<span>← Anterior</span>@else<a href="{{ $remitentes->previousPageUrl() }}">← Anterior</a>@endif
                <span>Página {{ $remitentes->currentPage() }} de {{ $remitentes->lastPage() }}</span>
                @if($remitentes->hasMorePages())<a href="{{ $remitentes->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
            </nav>
        @endif
    </section>
</x-layout>
