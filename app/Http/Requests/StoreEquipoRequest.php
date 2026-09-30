<?php

namespace App\Http\Requests;

use App\Enums\TipoEquipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para el Alta de Equipos del Cliente.
 *
 * Aplica la normalización y validación requerida para dar de alta dispositivos
 * de hardware que podrán posteriormente ser ingresados al taller de reparación:
 * - Sanitiza y recorta cadenas de texto (tipo, marca, modelo).
 * - Convierte campos de texto vacíos opcionales en nulos homogéneos.
 * - Restringe el tipo de equipo a los valores autorizados en el enum `TipoEquipo`.
 */
class StoreEquipoRequest extends FormRequest
{
    /**
     * Determina si el usuario tiene permiso para registrar equipos.
     *
     * @return bool Verdadero si el usuario se encuentra debidamente autenticado.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normaliza los valores antes de someterlos a las reglas de validación.
     */
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

    /**
     * Define las reglas de validación para las propiedades del equipo.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Tipo de dispositivo validado contra el enum TipoEquipo (laptop, pc, etc.)
            'tipo' => [
                'required',
                'string',
                Rule::in(TipoEquipo::valores()),
                'max:100',
            ],
            // Marca o fabricante del equipo
            'marca' => [
                'required',
                'string',
                'max:100',
            ],
            // Modelo específico o serie comercial
            'modelo' => [
                'required',
                'string',
                'max:100',
            ],
            // Número de serie o identificador del fabricante (opcional)
            'numero_serie' => [
                'nullable',
                'string',
                'max:150',
            ],
            // Descripción estética o especificaciones adicionales del equipo (opcional)
            'descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación.
     *
     * @return array<string, string> Mensajes de error específicos.
     */
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

    /**
     * Limpia un valor opcional retornando null si está vacío o no es una cadena.
     *
     * @param  mixed  $valor  Dato crudo de entrada.
     * @return string|null Cadena recortada o null.
     */
    private function normalizarOpcional(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== '' ? $valor : null;
    }
}

