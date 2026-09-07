<?php

namespace App\Http\Requests;

use App\Enums\EstadoAutorizacion;
use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutorizarOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        return $orden instanceof OrdenServicio
            && $this->user()?->can('view', $orden) === true;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                Rule::in(
                    EstadoAutorizacion::decisiones()
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Debes seleccionar una decisión.',
            'decision.in' => 'La decisión seleccionada no es válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'decision' => 'decisión',
        ];
    }
}
