<?php

namespace App\Jobs\Facturacion;

use App\Models\Venta;
use App\Services\Facturacion\TimbrarVentaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TimbrarVentaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de reintentos automáticos.
     */
    public int $tries = 3;

    /**
     * Segundos de espera entre cada intento (backoff exponencial).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(
        public Venta $venta
    ) {}

    public function handle(TimbrarVentaService $timbrarService): void
    {
        if ($this->venta->estado_facturacion === 'facturada') {
            return;
        }

        try {
            $timbrarService->ejecutar($this->venta);
            Log::info("Venta {$this->venta->folio} timbrada exitosamente vía cola.");
        } catch (Throwable $e) {
            Log::warning("Intento fallido de timbrado para venta {$this->venta->folio}: {$e->getMessage()}");

            // Marcar en estado pendiente si no ha culminado
            $this->venta->update(['estado_facturacion' => 'pendiente']);

            throw $e; // Provoca el reintento automático según $tries
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Se agotaron los reintentos para la venta {$this->venta->folio}. Queda en estado pendiente para lote posterior: {$exception->getMessage()}");
        $this->venta->update(['estado_facturacion' => 'pendiente']);
    }
}
