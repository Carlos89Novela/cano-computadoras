<?php

namespace App\Http\Requests\OrdenCompra;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'almacen_id' => ['required', 'integer', 'exists:almacenes,id'],
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha_emision' => ['required', 'date'],
            'fecha_entrega_esperada' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'tipo_cambio' => ['nullable', 'numeric', 'min:0.0001'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento_unitario' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.tasa_iva' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
