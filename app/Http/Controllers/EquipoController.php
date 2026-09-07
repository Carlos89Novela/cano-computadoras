<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipoRequest;
use App\Http\Requests\UpdateEquipoRequest;
use App\Models\Equipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipoController extends Controller
{
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
        return view('equipos.create');
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

    public function edit(Request $request, Equipo $equipo): View
    {
        $this->verificarPropietario($request, $equipo);

        return view('equipos.edit', compact('equipo'));
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
        Request $request,
        Equipo $equipo
    ): RedirectResponse {
        $this->verificarPropietario($request, $equipo);

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

    private function verificarPropietario(
        Request $request,
        Equipo $equipo
    ): void {
        abort_unless(
            $equipo->user_id === $request->user()->id,
            403,
            'No tienes permiso para administrar este equipo.'
        );
    }
}
