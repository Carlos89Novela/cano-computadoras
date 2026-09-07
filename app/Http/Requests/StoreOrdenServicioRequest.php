<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $problemaReportado = $this->input('problema_reportado');

        $this->merge([
            'problema_reportado' => is_string($problemaReportado)
                ? trim($problemaReportado)
                : $problemaReportado,
        ]);
    }

    public function rules(): array
    {
        return [
            'equipo_id' => [
                'required',
                'integer',
                Rule::exists('equipos', 'id')->where(
                    'user_id',
                    $this->user()?->id
                ),
            ],
            'problema_reportado' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'servicio_id' => [
                'nullable',
                'integer',
                Rule::exists('servicios', 'id')->where(
                    'activo',
                    true
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'equipo_id.required' => 'Debes seleccionar un equipo.',
            'equipo_id.integer' => 'El equipo seleccionado no es válido.',
            'equipo_id.exists' => 'El equipo seleccionado no existe o no te pertenece.',
            'problema_reportado.required' => 'Debes describir el problema del equipo.',
            'problema_reportado.min' => 'La descripción del problema debe tener al menos 10 caracteres.',
            'problema_reportado.max' => 'La descripción del problema no puede superar los 2000 caracteres.',
            'servicio_id.integer' => 'El servicio seleccionado no es válido.',
            'servicio_id.exists' => 'El servicio seleccionado no está disponible.',
        ];
    }

    public function attributes(): array
    {
        return [
            'equipo_id' => 'equipo',
            'problema_reportado' => 'problema reportado',
            'servicio_id' => 'servicio',
        ];
    }
}
