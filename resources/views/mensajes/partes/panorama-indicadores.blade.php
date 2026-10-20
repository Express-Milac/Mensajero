    <section class="indicadores-ejecutivos" data-panorama-indicadores aria-label="Resumen de conversaciones">
        <article class="card indicador-ejecutivo">
            <span>Grupos en seguimiento</span>
            <strong>{{ number_format($totalGrupos) }}</strong>
            <small>Conversaciones seleccionadas</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Mensajes registrados hoy</span>
            <strong>{{ number_format($mensajesHoy) }}</strong>
            <small>Contados por fecha de recepción</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Grupos con mensajes hoy</span>
            <strong>{{ number_format($cantidadConActividad) }}</strong>
            <small>De {{ number_format($totalGrupos) }} grupos seleccionados</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Último mensaje recibido</span>
            <strong class="indicador-fecha">{{ $ultimaParticipacion?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin mensajes' }}</strong>
            <small>Fecha y hora local</small>
        </article>
    </section>
