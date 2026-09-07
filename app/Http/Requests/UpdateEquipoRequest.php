<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipo = $this->route('equipo');

        return $equipo instanceof Equipo
            && $this->user() !== null
            && (int) $equipo->user_id === (int) $this->user()->id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo' => trim((string) $this->input('tipo')),
            'marca' => trim((string) $this->input('marca')),
            'modelo' => trim((string) $this->input('modelo')),
            'numero_serie' => $this->normalizarOpcional(
                $this->input('numero_serie')
            ),
            'descripcion' => $this->normalizarOpcional(
                $this->input('descripcion')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo' => [
                'required',
                'string',
                Rule::in([
                    'Laptop',
                    'Computadora de escritorio',
                    'Todo en uno',
                    'Otro',
                ]),
                'max:100',
            ],
            'marca' => [
                'required',
                'string',
                'max:100',
            ],
            'modelo' => [
                'required',
                'string',
                'max:100',
            ],
            'numero_serie' => [
                'nullable',
                'string',
                'max:150',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Debes seleccionar un tipo de equipo.',
            'tipo.in' => 'El tipo de equipo seleccionado no es válido.',
            'tipo.max' => 'El tipo de equipo no puede superar los 100 caracteres.',
            'marca.required' => 'La marca es obligatoria.',
            'marca.max' => 'La marca no puede superar los 100 caracteres.',
            'modelo.required' => 'El modelo es obligatorio.',
            'modelo.max' => 'El modelo no puede superar los 100 caracteres.',
            'numero_serie.max' => 'El número de serie no puede superar los 150 caracteres.',
            'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
        ];
    }

    private function normalizarOpcional(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== '' ? $valor : null;
    }
}
