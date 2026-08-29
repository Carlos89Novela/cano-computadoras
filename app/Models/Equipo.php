<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'marca',
        'modelo',
        'numero_serie',
        'descripcion',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

}