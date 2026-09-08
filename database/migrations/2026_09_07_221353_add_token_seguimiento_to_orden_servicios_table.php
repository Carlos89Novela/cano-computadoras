<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->string('token_seguimiento', 64)
                ->nullable()
                ->after('folio');
        });

        DB::table('orden_servicios')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($ordenes): void {
                foreach ($ordenes as $orden) {
                    DB::table('orden_servicios')
                        ->where('id', $orden->id)
                        ->update([
                            'token_seguimiento' => (string) Str::ulid(),
                        ]);
                }
            });

        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->unique(
                'token_seguimiento',
                'orden_servicios_token_seguimiento_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropUnique(
                'orden_servicios_token_seguimiento_unique'
            );

            $table->dropColumn('token_seguimiento');
        });
    }
};
