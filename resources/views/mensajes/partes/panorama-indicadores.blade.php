<div data-panorama-indicadores>
    <section class="indicadores-ejecutivos" aria-label="Resumen de conversaciones">
        <article class="card indicador-ejecutivo">
            <div class="indicador-cabecera">
                <span>Grupos en seguimiento</span>
                <span class="indicador-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h7"/></svg></span>
            </div>
            <strong>{{ number_format($totalGrupos) }}</strong>
            <small>Conversaciones autorizadas</small>
            <span class="indicador-base"></span>
        </article>
        <article class="card indicador-ejecutivo">
            <div class="indicador-cabecera">
                <span>Mensajes registrados hoy</span>
                <span class="indicador-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-5.5A8.5 8.5 0 1 1 21 11.5z"/><path d="M8 12h8"/></svg></span>
            </div>
            <strong>{{ number_format($mensajesHoy) }}</strong>
            <small>Desde el inicio del día, hora local</small>
            <span class="indicador-base"></span>
        </article>
        <article class="card indicador-ejecutivo">
            <div class="indicador-cabecera">
                <span>Grupos con mensajes hoy</span>
                <span class="indicador-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h17"/><path d="m8 14 4-4 3 2 5-6"/></svg></span>
            </div>
            <strong>{{ number_format($cantidadConActividad) }} <span class="indicador-fraccion">/ {{ number_format($totalGrupos) }}</span></strong>
            <small>Con al menos un mensaje hoy</small>
            <span class="indicador-base"></span>
        </article>
        <article class="card indicador-ejecutivo">
            <div class="indicador-cabecera">
                <span>Último mensaje recibido</span>
                <span class="indicador-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
            </div>
            <strong class="indicador-fecha">{{ $ultimaParticipacion?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin mensajes' }}</strong>
            <small>Fecha y hora local</small>
            <span class="indicador-base"></span>
        </article>
    </section>

    <section class="lecturas-ejecutivas" aria-label="Lecturas rápidas de actividad">
        <article class="lectura-directiva lectura-destacada">
            <div class="lectura-kicker"><span class="lectura-punto"></span> MAYOR ACTIVIDAD HOY</div>
            @if($grupoMasActivo)
                <strong>{{ $grupoMasActivo['grupo']->nombre_interno ?: ($grupoMasActivo['grupo']->nombre_grupo ?: 'Grupo sin nombre') }}</strong>
                <p>{{ number_format($grupoMasActivo['hoy']) }} {{ $grupoMasActivo['hoy'] === 1 ? 'mensaje registrado' : 'mensajes registrados' }} hoy</p>
            @else
                <strong>Sin actividad registrada hoy</strong>
                <p>No se han recibido mensajes nuevos en los grupos seleccionados.</p>
            @endif
        </article>
        <article class="lectura-directiva">
            <div class="lectura-kicker">ÚLTIMA CONVERSACIÓN CON MOVIMIENTO</div>
            @if($grupoMasReciente)
                <strong>{{ $grupoMasReciente['grupo']->nombre_interno ?: ($grupoMasReciente['grupo']->nombre_grupo ?: 'Grupo sin nombre') }}</strong>
                <p>Último mensaje: {{ $grupoMasReciente['ultima']->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</p>
            @else
                <strong>Sin conversaciones registradas</strong>
                <p>Los mensajes nuevos aparecerán al ser recibidos.</p>
            @endif
        </article>
        <article class="lectura-directiva">
            <div class="lectura-kicker">SIN MENSAJES HOY</div>
            <div class="lectura-numero">{{ number_format($gruposSinActividadHoy) }} <span>de {{ number_format($totalGrupos) }} grupos</span></div>
            <p>Sin mensajes registrados durante el día. Esto no implica una incidencia.</p>
        </article>
    </section>
</div>
