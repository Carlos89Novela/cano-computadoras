<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(): View
    {
        $servicios = Servicio::query()
            ->orderBy('nombre')
            ->get();

        return view(
            'admin.servicios.index',
            compact('servicios')
        );
    }

    public function create(): View
    {
        return view('admin.servicios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:150',
                'unique:servicios,nombre',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'activo' => [
                'nullable',
                'boolean',
            ],
        ]);

        Servicio::create([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio registrado correctamente.'
            );
    }

    public function edit(Servicio $servicio): View
    {
        return view(
            'admin.servicios.edit',
            compact('servicio')
        );
    }

    public function update(
        Request $request,
        Servicio $servicio
    ): RedirectResponse {
        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:150',
                'unique:servicios,nombre,'.$servicio->id,
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'activo' => [
                'nullable',
                'boolean',
            ],
        ]);

        $servicio->update([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'precio' => $datos['precio'],
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio actualizado correctamente.'
            );
    }

    public function destroy(
        Servicio $servicio
    ): RedirectResponse {
        if ($servicio->ordenesServicio()->exists()) {
            $servicio->update([
                'activo' => false,
            ]);

            return redirect()
                ->route('admin.servicios.index')
                ->with(
                    'success',
                    'El servicio tiene reparaciones relacionadas y fue desactivado.'
                );
        }

        $servicio->delete();

        return redirect()
            ->route('admin.servicios.index')
            ->with(
                'success',
                'Servicio eliminado correctamente.'
            );
    }
}