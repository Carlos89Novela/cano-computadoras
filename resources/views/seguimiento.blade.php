@extends('layouts.app')

@section('content')

<div class="container">

    <h1>Seguimiento de Reparación</h1>

    <p>
        Folio: {{ $orden->folio }}
    </p>

    <p>
        Estado: {{ $orden->estado }}
    </p>

    <p>
        Diagnóstico: {{ $orden->diagnostico }}
    </p>

</div>

@endsection