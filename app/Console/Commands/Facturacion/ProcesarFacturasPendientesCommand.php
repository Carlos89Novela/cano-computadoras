<?php

namespace App\Console\Commands\Facturacion;

use App\Models\Venta;
use App\Services\Facturacion\TimbrarVentaService;
use Illuminate\Console\Command;
use Throwable;

class ProcesarFacturasPendientesCommand extends Command
{
    protected $signature = 'facturacion:procesar-pendientes {--empresa_id= : Filtrar por empresa específica} {--limite=50 : Cantidad máxima a procesar por lote}';

    protected $description = 'Procesa e intenta timbrar en lote todas las ventas acumuladas en estado pendiente';

    public function handle(TimbrarVentaService $timbrarService): int
    {
        $empresaId = $this->option('empresa_id');
        $limite = (int) $this->option('limite');

        $query = Venta::query()
            ->where('estado_facturacion', 'pendiente')
            ->where('estado', 'completada')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', (int) $empresaId))
            ->orderBy('id', 'asc')
            ->take($limite);

        $pendientes = $query->get();
        $total = $pendientes->count();

        if ($total === 0) {
            $this->info('No hay facturas pendientes de timbrado.');

            return self::SUCCESS;
        }

        $this->info("Iniciando procesamiento de {$total} facturas pendientes...");
        $exitosas = 0;
        $fallidas = 0;

        foreach ($pendientes as $venta) {
            $this->line("Procesando venta folio: {$venta->folio}...");
            try {
                $timbrarService->ejecutar($venta);
                $this->info("✔ Venta {$venta->folio} timbrada con éxito (UUID: {$venta->uuid_fiscal}).");
                $exitosas++;
            } catch (Throwable $e) {
                $this->error("✖ Error en venta {$venta->folio}: {$e->getMessage()}");
                $fallidas++;
            }
        }

        $this->newLine();
        $this->info("Resumen de procesamiento: {$exitosas} timbradas, {$fallidas} pendientes.");

        return self::SUCCESS;
    }
}
