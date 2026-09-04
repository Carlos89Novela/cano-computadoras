@php
    $clasesEstado = config('ordenes.clases_estado', []);

    $clasePredeterminada = config(
        'ordenes.clase_estado_desconocido.badge',
        'estado-badge estado-desconocido'
    );

    $clases = $clasesEstado[$estado]['badge']
        ?? $clasePredeterminada;
@endphp

<span class="{{ $clases }}">
    {{ $estado }}
</span>