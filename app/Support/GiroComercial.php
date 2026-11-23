<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Utilidad centralizada para resolver la empresa activa y validar
 * su compatibilidad con el giro comercial (`giro_comercial`).
 */
class GiroComercial
{
    public static function resolverEmpresaActiva(Request $request): ?Empresa
    {
        /** @var User|null $user */
        $user = $request->user();

        $sessionEmpresaId = $request->session()->get('empresa_activa_id')
            ?? $request->session()->get('empresa_id');

        if (! empty($sessionEmpresaId)) {
            $empresa = Empresa::query()->find((int) $sessionEmpresaId);
            if ($empresa instanceof Empresa) {
                return $empresa;
            }
        }

        if ($user && method_exists($user, 'sucursales')) {
            $sucursalActiva = $user->sucursales()
                ->wherePivot('activo', true)
                ->orderByDesc('sucursal_usuario.es_principal')
                ->with('empresa')
                ->first();

            if ($sucursalActiva?->empresa instanceof Empresa) {
                $request->session()->put('empresa_activa_id', $sucursalActiva->empresa->id);

                return $sucursalActiva->empresa;
            }
        }

        return Empresa::query()->where('activo', true)->first();
    }

    public static function esRestaurante(Request $request): bool
    {
        $giro = strtolower((string) (self::resolverEmpresaActiva($request)?->giro_comercial ?? ''));

        return (bool) preg_match('/restauran|alimento/', $giro);
    }

    public static function esTecnologiaOTaller(Request $request): bool
    {
        $giro = strtolower((string) (self::resolverEmpresaActiva($request)?->giro_comercial ?? ''));

        return (bool) preg_match('/computador|tecnolog|taller|reparac|celular|electron/', $giro);
    }

    public static function exigirRestaurante(Request $request): void
    {
        abort_unless(
            self::esRestaurante($request),
            403,
            'Este módulo aplica únicamente para empresas con giro de restaurante.'
        );
    }

    public static function exigirTecnologiaOTaller(Request $request): void
    {
        abort_unless(
            self::esTecnologiaOTaller($request),
            403,
            'Este módulo aplica únicamente para empresas con giro de tecnología o taller.'
        );
    }
}