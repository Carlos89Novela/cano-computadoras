<?php

namespace App\Http\Requests;

use App\Enums\TipoEquipo;
use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización de Equipos del Cliente.
 *
 * Aplica los controles de autorización y validación sobre modificaciones a un equipo:
 * - Valida mediante Policy que el usuario autenticado sea el dueño legítimo del equipo.
 * - Sanitiza y recorta cadenas de texto (tipo, marca, modelo).
 * - Convierte cadenas vacías en valores nulos limpios para número de serie y descripción.
 * - Restringe el tipo de equipo a los valores autorizados en el enum `TipoEquipo`.
 */
class UpdateEquipoRequest extends FormRequest
{
    /**
     * Determina si el cliente autenticado tiene permiso para editar este equipo.
     *
     * @return bool Verdadero si la Policy de equipos autoriza la operación.
     */
    public function authorize(): bool
    {
        $equipo = $this->route('equipo');

        return $equipo instanceof Equipo
            && $this->user()?->can('update', $equipo) === true;
    }

    /**
     * Normaliza los valores antes de aplicar las reglas de validación.
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
     * Define las reglas de validación aplicables a la actualización del equipo.
     *
     * @return array<string, mixed> Reglas de validación.
     */
    public function rules(): array
    {
        return [
            // Tipo de dispositivo validado contra el enum TipoEquipo
            'tipo' => [
                'required',
                'string',
                Rule::in(TipoEquipo::valores()),
                'max:100',
            ],
            // Marca o fabricante
            'marca' => [
                'required',
                'string',
                'max:100',
            ],
            // Modelo del equipo
            'modelo' => [
                'required',
                'string',
                'max:100',
            ],
            // Número de serie del fabricante (opcional)
            'numero_serie' => [
                'nullable',
                'string',
                'max:150',
            ],
            // Descripción o características adicionales (opcional)
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

