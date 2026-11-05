<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sucursales\AbrirSucursal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AbrirSucursalRequest;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SucursalController extends Controller
{
    public function index(
        Empresa $empresa
    ): View {
        $empresa->load([
            'sucursales' => function ($consulta): void {
                $consulta
                    ->with([
                        'almacenes',
                        'usuarios',
                    ])
                    ->orderByDesc('es_principal')
                    ->orderBy('nombre');
            },
            'almacenes' => function ($consulta): void {
                $consulta
                    ->whereNull('sucursal_id')
                    ->orderBy('nombre');
            },
        ]);

        /** @var view-string $vista */
        $vista = 'admin.sucursales.index';

        return view(
            $vista,
            compact('empresa')
        );
    }

    public function create(
        Empresa $empresa
    ): View {
        $gerentes = User::query()
            ->role([
                'supervisor',
                'administrador',
            ])
            ->where(
                'es_propietario',
                false
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        $tieneSucursales = Sucursal::query()
            ->where('empresa_id', $empresa->id)
            ->exists();

        /** @var view-string $vista */
        $vista = 'admin.sucursales.create';

        return view(
            $vista,
            compact(
                'empresa',
                'gerentes',
                'tieneSucursales',
            )
        );
    }

    public function store(
        AbrirSucursalRequest $request,
        Empresa $empresa,
        AbrirSucursal $abrirSucursal
    ): RedirectResponse {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            403
        );

        $datos = $request->validated();

        $gerente = User::query()->findOrFail(
            (int) $datos['gerente_id']
        );

        unset($datos['gerente_id']);

        $abrirSucursal->ejecutar(
            empresa: $empresa,
            actor: $actor,
            gerente: $gerente,
            datos: $datos,
            request: $request
        );

        return redirect()
            ->route(
                'admin.empresas.sucursales.index',
                [
                    'empresa' => $empresa->id,
                ]
            )
            ->with(
                'success',
                'La sucursal y su almacén principal fueron creados correctamente.'
            );
    }
}
