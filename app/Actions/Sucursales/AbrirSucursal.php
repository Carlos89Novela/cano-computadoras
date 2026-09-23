<?php

namespace App\Actions\Sucursales;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AbrirSucursal
{
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        User $actor,
        User $gerente,
        array $datos,
        ?Request $request = null
    ): Sucursal {
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        $this->validar(
            $empresa,
            $actor,
            $gerente,
            $datosNormalizados
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $actor,
                $gerente,
                $datosNormalizados,
                $request
            ): Sucursal {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $gerenteBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($gerente->id);

                $this->validar(
                    $empresaBloqueada,
                    $actor,
                    $gerenteBloqueado,
                    $datosNormalizados
                );

                $codigo = (string) $datosNormalizados[
                    'codigo'
                ];

                $nombre = (string) $datosNormalizados[
                    'nombre'
                ];

                $existeSucursal = Sucursal::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->where('codigo', $codigo)
                    ->exists();

                if ($existeSucursal) {
                    throw ValidationException::withMessages([
                        'codigo' => 'Ya existe una sucursal con ese código.',
                    ]);
                }

                $esPrimeraSucursal = ! Sucursal::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->exists();

                $solicitaPrincipal = (bool) (
                    $datosNormalizados['es_principal']
                    ?? false
                );

                if (
                    $solicitaPrincipal
                    && ! $esPrimeraSucursal
                ) {
                    $existePrincipal = Sucursal::query()
                        ->where(
                            'empresa_id',
                            $empresaBloqueada->id
                        )
                        ->where(
                            'es_principal',
                            true
                        )
                        ->exists();

                    if ($existePrincipal) {
                        throw ValidationException::withMessages([
                            'es_principal' => 'La empresa ya tiene una sucursal principal.',
                        ]);
                    }
                }

                $esPrincipal = $esPrimeraSucursal
                    || $solicitaPrincipal;

                $sucursal = Sucursal::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'telefono' => $datosNormalizados['telefono'],
                    'correo' => $datosNormalizados['correo'],
                    'direccion' => $datosNormalizados['direccion'],
                    'ciudad' => $datosNormalizados['ciudad'],
                    'estado' => $datosNormalizados['estado'],
                    'codigo_postal' => $datosNormalizados['codigo_postal'],
                    'es_principal' => $esPrincipal,
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]);

                $codigoAlmacen =
                    'ALM-'.$codigo;

                $almacenPrincipal = Almacen::query()
                    ->create([
                        'empresa_id' => $empresaBloqueada->id,
                        'sucursal_id' => $sucursal->id,
                        'codigo' => $codigoAlmacen,
                        'nombre' => 'Almacén '.$nombre,
                        'tipo' => Almacen::TIPO_PRINCIPAL,
                        'es_virtual' => false,
                        'permite_existencias' => true,
                        'activo' => true,
                        'creado_por_id' => $actor->id,
                    ]);

                $almacenTransito = Almacen::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->where(
                        'tipo',
                        Almacen::TIPO_TRANSITO
                    )
                    ->lockForUpdate()
                    ->first();

                if ($almacenTransito === null) {
                    $almacenTransito = Almacen::query()
                        ->create([
                            'empresa_id' => $empresaBloqueada->id,
                            'sucursal_id' => null,
                            'codigo' => 'TRANSITO',
                            'nombre' => 'Almacén virtual de tránsito',
                            'tipo' => Almacen::TIPO_TRANSITO,
                            'es_virtual' => true,
                            'permite_existencias' => true,
                            'activo' => true,
                            'creado_por_id' => $actor->id,
                        ]);
                }

                $sucursal->usuarios()->attach(
                    $gerenteBloqueado->id,
                    [
                        'es_principal' => true,
                        'es_gerente' => true,
                        'activo' => true,
                        'asignado_por_id' => $actor->id,
                        'asignado_at' => now(),
                    ]
                );

                $this->registrarAuditoria->registrar(
                    accion: 'sucursal.abierta',
                    modulo: 'sucursales',
                    descripcion: 'Se abrió una nueva sucursal con su almacén principal.',
                    actor: $actor,
                    modelo: $sucursal,
                    usuarioAfectado: $gerenteBloqueado,
                    valoresNuevos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'sucursal_id' => $sucursal->id,
                        'codigo' => $sucursal->codigo,
                        'nombre' => $sucursal->nombre,
                        'es_principal' => $sucursal->es_principal,
                        'gerente_id' => $gerenteBloqueado->id,
                        'almacen_principal_id' => $almacenPrincipal->id,
                        'almacen_transito_id' => $almacenTransito->id,
                    ],
                    metadatos: [
                        'almacen_principal_codigo' => $almacenPrincipal->codigo,
                        'almacen_transito_codigo' => $almacenTransito->codigo,
                    ],
                    motivo: (string) $datosNormalizados[
                        'motivo'
                    ],
                    request: $request
                );

                return $sucursal->fresh()->load([
                    'empresa',
                    'almacenes',
                    'usuarios',
                ]);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $codigo = $datos['codigo'] ?? '';
        $nombre = $datos['nombre'] ?? '';
        $motivo = $datos['motivo'] ?? '';

        return [
            'codigo' => is_string($codigo)
                ? Str::upper(trim($codigo))
                : '',
            'nombre' => is_string($nombre)
                ? trim($nombre)
                : '',
            'telefono' => $this->textoOpcional(
                $datos['telefono'] ?? null
            ),
            'correo' => $this->textoOpcional(
                $datos['correo'] ?? null
            ),
            'direccion' => $this->textoOpcional(
                $datos['direccion'] ?? null
            ),
            'ciudad' => $this->textoOpcional(
                $datos['ciudad'] ?? null
            ),
            'estado' => $this->textoOpcional(
                $datos['estado'] ?? null
            ),
            'codigo_postal' => $this->textoOpcional(
                $datos['codigo_postal'] ?? null
            ),
            'es_principal' => filter_var(
                $datos['es_principal'] ?? false,
                FILTER_VALIDATE_BOOL
            ),
            'motivo' => is_string($motivo)
                ? trim($motivo)
                : '',
        ];
    }

    private function textoOpcional(
        mixed $valor
    ): ?string {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor !== ''
            ? $valor
            : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validar(
        Empresa $empresa,
        User $actor,
        User $gerente,
        array $datos
    ): void {
        if (
            ! $actor->hasRole('administrador')
            || ! $actor->esPropietario()
        ) {
            throw new AuthorizationException(
                'Solo el propietario puede abrir sucursales.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden crear sucursales para una empresa inactiva.',
            ]);
        }

        if (! $gerente->hasAnyRole([
            'supervisor',
            'administrador',
        ])) {
            throw ValidationException::withMessages([
                'gerente_id' => 'El gerente debe tener rol de supervisor o administrador.',
            ]);
        }

        $codigo = (string) (
            $datos['codigo']
            ?? ''
        );

        if (
            $codigo === ''
            || mb_strlen($codigo) > 30
            || preg_match(
                '/^[A-Z0-9-]+$/',
                $codigo
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'codigo' => 'El código debe contener letras, números o guiones y no superar 30 caracteres.',
            ]);
        }

        $nombre = (string) (
            $datos['nombre']
            ?? ''
        );

        if (
            $nombre === ''
            || mb_strlen($nombre) > 150
        ) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre de la sucursal es obligatorio y no puede superar 150 caracteres.',
            ]);
        }

        $motivo = (string) (
            $datos['motivo']
            ?? ''
        );

        if (
            $motivo === ''
            || mb_strlen($motivo) > 1000
        ) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo válido de hasta 1000 caracteres.',
            ]);
        }
    }
}
