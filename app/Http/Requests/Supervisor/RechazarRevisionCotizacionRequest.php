<?php

namespace App\Http\Requests\Supervisor;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RechazarRevisionCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'rejectQuoteReview',
            $orden
        );
    }

    protected function prepareForValidation(): void
    {
        $observacion = $this->input('observacion');

        $this->merge([
            'observacion' => is_string($observacion)
                ? trim($observacion)
                : $observacion,
        ]);
    }

    public function rules(): array
    {
        return [
            'observacion' => [
                'required',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'observacion.required' => 'Debes indicar el motivo del rechazo.',
            'observacion.string' => 'La observación debe ser un texto válido.',
            'observacion.max' => 'La observación no puede superar los 2000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'observacion' => 'observación',
        ];
    }
}
