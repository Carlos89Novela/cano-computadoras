@php
    $classes = match ($estado) {
        'Recibido' => 'bg-sky-900 text-sky-200 border border-sky-700',
        'En diagnóstico' => 'bg-cyan-900 text-cyan-200 border border-cyan-700',
        'Esperando autorización' => 'bg-amber-900 text-amber-200 border border-amber-700',
        'Esperando refacción' => 'bg-orange-900 text-orange-200 border border-orange-700',
        'En reparación' => 'bg-violet-900 text-violet-200 border border-violet-700',
        'En pruebas' => 'bg-indigo-900 text-indigo-200 border border-indigo-700',
        'Listo para entrega' => 'bg-emerald-900 text-emerald-200 border border-emerald-700',
        'Entregado' => 'bg-teal-900 text-teal-200 border border-teal-700',
        'Cancelado' => 'bg-rose-900 text-rose-200 border border-rose-700',
        default => 'bg-slate-700 text-slate-200 border border-slate-600',
    };
@endphp

<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $classes }}">
    {{ $estado }}
</span>
