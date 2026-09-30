<?php

namespace App\Http\Requests\Operacion;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Solicitud de Validación para la Entrega Física de Equipos al Cliente.
 *
 * Aplica los controles de autorización para el cierre operativo definitivo:
 * - Valida mediante la Policy `deliver` que el usuario posea permisos para registrar la entrega
 *   (Supervisor o Administrador) y que la orden esté en estado `LISTO_PARA_ENTREGA`
 *   con cotización aprobada, costo final fijado y sin entrega previa registrada.
 * - Sanitiza y valida comentarios de entrega u observaciones del cliente al recibir el equipo.
 */
class EntregarOrdenServicioRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene facultades para entregar la orden de servicio.
     *
     * @return bool Verdadero si la Policy autoriza la entrega.
     */
    public function authorize(): bool
    {
        $orden = $this->route('orden');

        if (! $orden instanceof OrdenServicio) {
            return false;
        }

        return Gate::allows(
            'deliver',
            $orden
        );
    }

    /**
     * Normaliza los textos de entrada antes de aplicar las validaciones.
     */
    protected function prepareForValidation(): void
    {
        $comentario = $this->input('comentario');

        $this->merge([
            'comentario' => is_string($comentario)
                ? trim($comentario)
                : $comentario,
        ]);
    }

    /**
     * Define las reglas de validación para el comentario de entrega.
     *
     * @return array<string, mixed> Reglas de validación aplicables.
     */
    public function rules(): array
    {
        return [
            // Observaciones o conformidad de entrega del cliente (opcional)
            'comentario' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados para la validación de entrega.
     *
     * @return array<string, string> Mensajes legibles.
     */
    public function messages(): array
    {
        return [
            'comentario.string' => 'El comentario de entrega debe ser un texto válido.',
            'comentario.max' => 'El comentario de entrega no puede superar los 2000 caracteres.',
        ];
    }

    /**
     * Nombres amigables para los campos en caso de fallos.
     *
     * @return array<string, string> Nombres legibles.
     */
    public function attributes(): array
    {
        return [
            'comentario' => 'comentario de entrega',
        ];
    }
}

