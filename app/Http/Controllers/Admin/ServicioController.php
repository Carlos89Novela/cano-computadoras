<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServicioRequest;
use App\Http\Requests\Admin\UpdateServicioRequest;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(): View
    {
        $servicios = Servicio::query()
            ->orderBy('nombre')
            ->get();

        return view('admin.servicios.index', compact('servicios'));
    }

    public function create(): View
    {
        return view('admin.servicios.create');
    }

    public function store(
        StoreServicioRequest $request
    ): RedirectResponse {
        Servicio::query()->create(
            $request->validated()
        );

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
        UpdateServicioRequest $request,
        Servicio $servicio
    ): RedirectResponse {
        $servicio->update(
            $request->validated()
        );

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