<?php

namespace App\Http\Requests\Inventario;

use App\Models\Empresa;
use App\Models\RecepcionInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecepcionInventarioRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado está autorizado para emitir la petición.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('inventario.registrar_entrada') ?? false;
    }

    /**
     * Reglas de validación para el payload de la recepción.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Empresa|null $empresa */
        $empresa = $this->route('empresa');
        $empresaId = $empresa?->id;

        return [
            'almacen_id' => [
                'required',
                'integer',
                Rule::exists('almacenes', 'id')->where(function ($query) use ($empresaId) {
                    if ($empresaId !== null) {
                        $query->where('empresa_id', $empresaId);
                    }
                }),
            ],
            'proveedor_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($this->input('tipo') === RecepcionInventario::TIPO_COMPRA),
                Rule::exists('proveedores', 'id')->where(function ($query) use ($empresaId) {
                    if ($empresaId !== null) {
                        $query->where('empresa_id', $empresaId);
                    }
                }),
            ],
            'tipo' => [
                'required',
                'string',
                Rule::in(RecepcionInventario::tiposPermitidos()),
            ],
            'referencia_externa' => [
                'nullable',
                'string',
                'max:150',
            ],
            'motivo' => [
                'required',
                'string',
                'max:1000',
            ],
            'lineas' => [
                'required',
                'array',
                'min:1',
            ],
            'lineas.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id')->where(function ($query) use ($empresaId) {
                    if ($empresaId !== null) {
                        $query->where('empresa_id', $empresaId);
                    }
                }),
            ],
            'lineas.*.cantidad' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,4})?$/',
            ],
            'lineas.*.costo_unitario' => [
                'required',
                'numeric',
                'gte:0',
                'regex:/^\d+(\.\d{1,4})?$/',
            ],
        ];
    }

    /**
     * Mensajes descriptivos en caso de error de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Debes seleccionar un almacén de destino.',
            'almacen_id.exists' => 'El almacén seleccionado no es válido o no pertenece a la empresa.',
            'proveedor_id.required' => 'El proveedor es obligatorio cuando el tipo de recepción es una compra.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido o no pertenece a la empresa.',
            'tipo.in' => 'El tipo de recepción seleccionado no es válido.',
            'motivo.required' => 'Debes proporcionar un motivo para la recepción.',
            'lineas.min' => 'Debes incluir al menos un producto en la recepción.',
            'lineas.*.producto_id.distinct' => 'No puedes repetir el mismo producto en diferentes líneas de la recepción.',
            'lineas.*.producto_id.exists' => 'Uno de los productos seleccionados no existe o no pertenece a la empresa.',
            'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'lineas.*.costo_unitario.gte' => 'El costo unitario no puede ser negativo.',
        ];
    }
}
