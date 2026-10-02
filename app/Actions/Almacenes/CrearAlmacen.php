<?php

namespace App\Actions\Almacenes;

// ---------------------------------------------------------
// MODELOS
// Entidades relacionadas con la creación del almacén.
// ---------------------------------------------------------

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
// ---------------------------------------------------------
// SERVICIOS Y UTILIDADES
// Auditoría, autorización, transacciones y normalización.
// ---------------------------------------------------------

use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearAlmacen
{
    /**
     * Catálogo de tipos de almacén operativos admitidos.
     *
     * @var list<string>
     */
    public const TIPOS_PERMITIDOS = [
        Almacen::TIPO_PRINCIPAL,
        Almacen::TIPO_SECUNDARIO,
        Almacen::TIPO_TRANSITO,
        Almacen::TIPO_MERMA,
        Almacen::TIPO_DEVOLUCIONES,
    ];

    // ---------------------------------------------------------
    // CONSTRUCTOR
    // Inyecta el servicio central de auditoría.
    // ---------------------------------------------------------

    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    // ---------------------------------------------------------
    // MÉTODO PRINCIPAL
    // Valida, normaliza y registra un nuevo almacén en la sucursal.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Sucursal $sucursal,
        User $actor,
        array $datos,
        ?Request $request = null
    ): Almacen {
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        $this->validar(
            empresa: $empresa,
            sucursal: $sucursal,
            actor: $actor,
            datos: $datosNormalizados
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $sucursal,
                $actor,
                $datosNormalizados,
                $request
            ): Almacen {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $sucursalBloqueada = Sucursal::query()
                    ->lockForUpdate()
                    ->findOrFail($sucursal->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    sucursal: $sucursalBloqueada,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                $almacen = Almacen::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'sucursal_id' => $sucursalBloqueada->id,
                    'codigo' => $datosNormalizados['codigo'],
                    'nombre' => $datosNormalizados['nombre'],
                    'tipo' => $datosNormalizados['tipo'],
                    'es_virtual' => $datosNormalizados['es_virtual'],
                    'permite_existencias' => $datosNormalizados['permite_existencias'],
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]);

                $this->registrarAuditoria->registrar(
                    accion: 'almacen.creado',
                    modulo: 'almacenes',
                    descripcion: 'Se creó un nuevo almacén.',
                    actor: $actor,
                    modelo: $almacen,
                    valoresAnteriores: null,
                    valoresNuevos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'sucursal_id' => $sucursalBloqueada->id,
                        'codigo' => $almacen->codigo,
                        'nombre' => $almacen->nombre,
                        'tipo' => $almacen->tipo,
                        'es_virtual' => $almacen->es_virtual,
                        'permite_existencias' => $almacen->permite_existencias,
                    ],
                    motivo: $datosNormalizados['motivo'],
                    request: $request
                );

                return $almacen->load([
                    'empresa',
                    'sucursal',
                    'creadoPor',
                ]);
            }
        );
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN
    // Limpia cadenas, asegura tipos y asigna valores por defecto.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     codigo: string,
     *     nombre: string,
     *     tipo: string,
     *     es_virtual: bool,
     *     permite_existencias: bool,
     *     motivo: string
     * }
     */
    private function normalizarDatos(array $datos): array
    {
        $tipo = Str::lower(
            trim((string) ($datos['tipo'] ?? Almacen::TIPO_SECUNDARIO))
        );

        $esVirtual = isset($datos['es_virtual'])
            ? (bool) $datos['es_virtual']
            : ($tipo === Almacen::TIPO_TRANSITO);

        return [
            'codigo' => Str::upper(
                trim((string) ($datos['codigo'] ?? ''))
            ),
            'nombre' => trim(
                (string) ($datos['nombre'] ?? '')
            ),
            'tipo' => $tipo,
            'es_virtual' => $esVirtual,
            'permite_existencias' => isset($datos['permite_existencias'])
                ? filter_var(
                    $datos['permite_existencias'],
                    FILTER_VALIDATE_BOOLEAN
                )
                : true,
            'motivo' => trim(
                (string) ($datos['motivo'] ?? '')
            ),
        ];
    }

    // ---------------------------------------------------------
    // VALIDACIÓN
    // Comprueba permisos, estado de empresa/sucursal y unicidad.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     codigo: string,
     *     nombre: string,
     *     tipo: string,
     *     es_virtual: bool,
     *     permite_existencias: bool,
     *     motivo: string
     * }  $datos
     */
    private function validar(
        Empresa $empresa,
        Sucursal $sucursal,
        User $actor,
        array $datos
    ): void {
        if (! $actor->can('almacenes.crear')) {
            throw new AuthorizationException(
                'No tienes permiso para crear almacenes.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden crear almacenes para una empresa inactiva.',
            ]);
        }

        if (! $sucursal->activo) {
            throw ValidationException::withMessages([
                'sucursal' => 'No se pueden crear almacenes en una sucursal inactiva.',
            ]);
        }

        if ((int) $sucursal->empresa_id !== (int) $empresa->id) {
            throw new AuthorizationException(
                'La sucursal no pertenece a la empresa indicada.'
            );
        }

        $codigo = $datos['codigo'];

        if (
            $codigo === ''
            || mb_strlen($codigo) > 40
            || preg_match('/^[A-Z0-9-]+$/', $codigo) !== 1
        ) {
            throw ValidationException::withMessages([
                'codigo' => 'El código debe contener letras, números o guiones y no superar 40 caracteres.',
            ]);
        }

        if ($datos['nombre'] === '') {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del almacén es obligatorio.',
            ]);
        }

        if (mb_strlen($datos['nombre']) > 150) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre no puede superar los 150 caracteres.',
            ]);
        }

        if (! in_array($datos['tipo'], self::TIPOS_PERMITIDOS, true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de almacén seleccionado no es válido.',
            ]);
        }

        if ($datos['tipo'] === Almacen::TIPO_TRANSITO) {
            throw ValidationException::withMessages([
                'tipo' => 'Los almacenes de tránsito no pueden crearse de forma manual.',
            ]);
        }

        if ($datos['tipo'] === Almacen::TIPO_PRINCIPAL) {
            $existePrincipal = Almacen::query()
                ->where('sucursal_id', $sucursal->id)
                ->where('tipo', Almacen::TIPO_PRINCIPAL)
                ->exists();

            if ($existePrincipal) {
                throw ValidationException::withMessages([
                    'tipo' => 'La sucursal ya cuenta con un almacén principal.',
                ]);
            }
        }

        $existeCodigo = Almacen::query()
            ->where('empresa_id', $empresa->id)
            ->where('codigo', $datos['codigo'])
            ->exists();

        if ($existeCodigo) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya existe un almacén con ese código en la empresa.',
            ]);
        }

        if ($datos['motivo'] === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar el motivo de la creación del almacén.',
            ]);
        }

        if (mb_strlen($datos['motivo']) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }
    }
}
