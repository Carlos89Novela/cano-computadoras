<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class EmpresaConfiguracionFiscal extends Model
{
    use HasFactory;

    protected $table = 'empresa_configuraciones_fiscales';

    protected $fillable = [
        'empresa_id',
        'facturacion_activa',
        'modalidad_facturacion',
        'rfc',
        'razon_social',
        'regimen_fiscal',
        'codigo_postal',
        'cer_path',
        'key_path',
        'password_csd_encrypted',
        'timbres_contratados',
        'timbres_utilizados',
        'timbres_restantes',
    ];

    protected $casts = [
        'facturacion_activa' => 'boolean',
        'timbres_contratados' => 'integer',
        'timbres_utilizados' => 'integer',
        'timbres_restantes' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function setPasswordCsdAttribute(?string $value): void
    {
        $this->attributes['password_csd_encrypted'] = $value !== null && $value !== ''
            ? Crypt::encryptString($value)
            : null;
    }

    public function getPasswordCsdAttribute(): ?string
    {
        if (empty($this->attributes['password_csd_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['password_csd_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }
}
