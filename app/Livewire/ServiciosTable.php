<?php

namespace App\Livewire;

use App\Models\Servicio;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Illuminate\Database\Eloquent\Builder;

class ServiciosTable extends DataTableComponent
{
    protected $model = Servicio::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function builder(): Builder
    {
        return Servicio::query();
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Servicio', 'nombre')
                ->searchable()
                ->sortable(),

            Column::make('Precio', 'precio')
                ->sortable(),
            Column::make('Acciones', 'id')
                ->format(function ($value, $row) {
                    return view('admin.servicios.partials.acciones', ['servicio' => $row])->render();
                })
                ->html(),
        ];
    }
}