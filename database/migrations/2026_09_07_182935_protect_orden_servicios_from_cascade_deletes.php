<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropForeign([
                'user_id',
            ]);

            $table->dropForeign([
                'equipo_id',
            ]);
        });

        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('equipo_id')
                ->references('id')
                ->on('equipos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->dropForeign([
                'user_id',
            ]);

            $table->dropForeign([
                'equipo_id',
            ]);
        });

        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('equipo_id')
                ->references('id')
                ->on('equipos')
                ->cascadeOnDelete();
        });
    }
};
