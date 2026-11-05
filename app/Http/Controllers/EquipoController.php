<?php

namespace App\Http\Controllers;

use App\Enums\TipoEquipo;
use App\Http\Requests\StoreEquipoRequest;
use App\Http\Requests\UpdateEquipoRequest;
use App\Models\Equipo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $equipos = $request->user()
            ->equipos()
            ->latest()
            ->get();

        return view('equipos.index', compact('equipos'));
    }

    public function create(): View
    {
        $tiposEquipo = TipoEquipo::valores();

        return view(
            'equipos.create',
            compact('tiposEquipo')
        );
    }

    public function store(
        StoreEquipoRequest $request
    ): RedirectResponse {
        $request->user()
            ->equipos()
            ->create($request->validated());

        return redirect()
            ->route('equipos.index')
            ->with(
                'success',
                'Equipo registrado correctamente.'
            );
    }

    public function edit(
        Equipo $equipo
    ): View {
        $this->authorize('view', $equipo);

        $tiposEquipo = TipoEquipo::valores();

        return view(
            'equipos.edit',
            compact(
                'equipo',
                'tiposEquipo'
            )
        );
    }

    public function update(
        UpdateEquipoRequest $request,
        Equipo $equipo
    ): RedirectResponse {
        $equipo->update(
            $request->validated()
        );

        return redirect()
            ->route('equipos.index')
            ->with(
                'success',
                'Equipo actualizado correctamente.'
            );
    }

    public function destroy(
        Equipo $equipo
    ): RedirectResponse {
        $this->authorize('delete', $equipo);

        if ($equipo->ordenesServicio()->exists()) {
            return redirect()
                ->route('equipos.index')
                ->with(
                    'error',
                    'No puedes eliminar un equipo que tiene órdenes de reparación registradas.'
                );
        }

        $equipo->delete();

        return redirect()
            ->route('equipos.index')
            ->with(
                'success',
                'Equipo eliminado correctamente.'
            );
    }
}
