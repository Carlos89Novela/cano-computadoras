<?php

namespace App\Http\Requests\Finanzas;

use Illuminate\Foundation\Http\FormRequest;

class StoreMovimientoBancarioRequest extends FormRequest
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
            'tipo' => ['required', 'string', 'in:ingreso,egreso,transferencia'],
            'cuenta_bancaria_id' => ['required', 'integer', 'exists:cuentas_bancarias,id'],
            'cuenta_destino_id' => ['nullable', 'required_if:tipo,transferencia', 'integer', 'exists:cuentas_bancarias,id', 'different:cuenta_bancaria_id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_operacion' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }
}
