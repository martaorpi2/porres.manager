@php
    $reconciliationCount = 0;
    foreach ($accreditationAmountAlerts as $group) {
        $reconciliationCount += (int) $group['count'];
    }
@endphp
<div class="row mb-4">
    <div class="col-md-12">
        <h3 class="section-title mb-0">Inconsistencias en la conciliación de cobranzas y acreditaciones</h3>
        <p class="mb-0 mt-2" style="color:#000;">Se detectaron diferencias entre los asientos de cobranza y las acreditaciones registradas por medio de pago. Revise los siguientes casos.</p>
    </div>
</div>
<div id="accreditation-reconciliation-section" class="process-step" style="border-left: 4px solid #dc3545;">
    <div class="process-step-header" style="background-color: #f8d7da;">
        <div class="process-step-title">
            <i class="la la-balance-scale process-step-icon" style="color: #721c24;"></i>
            <span style="color: #721c24; font-weight: bold;">Cobranzas y acreditaciones</span>
        </div>
        <span class="process-step-count" style="background-color: #dc3545; color: white;">{{ $reconciliationCount }}</span>
    </div>
    <div class="process-step-content">
        @foreach($accreditationAmountAlerts as $group)
            @foreach($group['amounts'] as $row)
                <div class="process-item-card" style="border-left: 3px solid #dc3545; cursor: default;">
                    <div class="process-item-title">{{ $group['menu'] }}</div>
                    <div class="process-item-meta">
                        <span><i class="la la-calendar"></i> {{ $row['when'] }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span>Cobranza: {{ $row['debe'] }}</span>
                        <span>Acreditación: {{ $row['haber'] }}</span>
                    </div>
                    <div class="process-item-meta">
                        <span style="color: #dc3545; font-weight: bold;">
                            <i class="la la-exchange-alt"></i> Diferencia: {{ $row['difference'] }}
                        </span>
                    </div>
                </div>
            @endforeach
            @foreach($group['missing'] as $missing)
                <div class="process-item-card" style="border-left: 3px solid #ffc107; cursor: default;">
                    <div class="process-item-title">{{ $group['menu'] }}</div>
                    <div class="process-item-meta">
                        <span style="color: #856404; font-weight: bold;">
                            <i class="la la-exclamation-triangle"></i> Asiento de cobranza faltante
                        </span>
                    </div>
                    <div class="process-item-meta">
                        <span>{{ $missing }}</span>
                    </div>
                </div>
            @endforeach
            @foreach($group['excluded'] ?? [] as $excluded)
                <div class="process-item-card" style="border-left: 3px solid #ffc107; cursor: default;">
                    <div class="process-item-title">{{ $group['menu'] }}</div>
                    <div class="process-item-meta">
                        <span style="color: #856404; font-weight: bold;">
                            <i class="la la-exclamation-triangle"></i> No entra al asiento
                        </span>
                    </div>
                    <div class="process-item-meta">
                        <span>{{ $excluded }}</span>
                    </div>
                </div>
            @endforeach
            @foreach($group['mixed'] ?? [] as $mixed)
                <div class="process-item-card" style="border-left: 3px solid #871f1f; cursor: default;">
                    <div class="process-item-title">{{ $group['menu'] }}</div>
                    <div class="process-item-meta">
                        <span style="color: #721c24; font-weight: bold;">
                            <i class="la la-calendar"></i> Varias fechas de cobro
                        </span>
                    </div>
                    <div class="process-item-meta">
                        <span>{{ $mixed }}</span>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
