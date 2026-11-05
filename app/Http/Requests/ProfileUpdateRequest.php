<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de Validación para la Actualización de Datos de Perfil.
 *
 * Valida los cambios en la información personal del usuario autenticado:
 * - Valida nombre requerido y con longitud máxima permitida.
 * - Valida correo electrónico válido, normalizado a minúsculas, y con unicidad
 *   en la tabla de usuarios exceptuando el identificador del propio usuario activo.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Define las reglas de validación aplicables a la actualización del perfil.
     *
     * @return array<string, ValidationRule|array<mixed>|string> Matriz de reglas de validación.
     */
    public function rules(): array
    {
        return [
            // Nombre completo del usuario
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            // Correo electrónico con comprobación de unicidad ignorando al propio usuario
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()?->id),
            ],
        ];
    }
}
