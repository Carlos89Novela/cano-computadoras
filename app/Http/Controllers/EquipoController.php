<?php

namespace App\Http\Controllers;

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

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'string', 'max:100'],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->equipos()->create($datos);

        return redirect()
            ->route('equipos.index')
            ->with('success', 'Equipo registrado correctamente.');
    }

    public function edit(Request $request, Equipo $equipo): View
    {
        $this->verificarPropietario($request, $equipo);

        return view('equipos.edit', compact('equipo'));
    }

    public function update(
        Request $request,
        Equipo $equipo
    ): RedirectResponse {
        $this->verificarPropietario($request, $equipo);

        $datos = $request->validate([
            'tipo' => ['required', 'string', 'max:100'],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $equipo->update($datos);

        return redirect()
            ->route('equipos.index')
            ->with('success', 'Equipo actualizado correctamente.');
    }

    public function destroy(
        Request $request,
        Equipo $equipo
    ): RedirectResponse {
        $this->verificarPropietario($request, $equipo);

        $equipo->delete();

        return redirect()
            ->route('equipos.index')
            ->with('success', 'Equipo eliminado correctamente.');
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