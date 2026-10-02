<?php

namespace App\Actions\Almacenes;

// ---------------------------------------------------------
// MODELOS
// Entidades relacionadas con la actualización del almacén.
// ---------------------------------------------------------

use App\Models\Almacen;
use App\Models\Empresa;
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

class ActualizarAlmacen
{
    /**
     * Catálogo de tipos de almacén admitidos.
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
    // Valida, normaliza y actualiza los datos del almacén.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(
        Empresa $empresa,
        Almacen $almacen,
        User $actor,
        array $datos,
        ?Request $request = null
    ): Almacen {
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        $this->validar(
            empresa: $empresa,
            almacen: $almacen,
            actor: $actor,
            datos: $datosNormalizados
        );

        $request ??= request();

        return DB::transaction(
            function () use (
                $empresa,
                $almacen,
                $actor,
                $datosNormalizados,
                $request
            ): Almacen {
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $almacenBloqueado = Almacen::query()
                    ->lockForUpdate()
                    ->findOrFail($almacen->id);

                $this->validar(
                    empresa: $empresaBloqueada,
                    almacen: $almacenBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                $valoresAnteriores = [
                    'codigo' => $almacenBloqueado->codigo,
                    'nombre' => $almacenBloqueado->nombre,
                    'tipo' => $almacenBloqueado->tipo,
                    'permite_existencias' => (bool) $almacenBloqueado->permite_existencias,
                ];

                $almacenBloqueado->update([
                    'codigo' => $datosNormalizados['codigo'],
                    'nombre' => $datosNormalizados['nombre'],
                    'tipo' => $datosNormalizados['tipo'],
                    'permite_existencias' => $datosNormalizados['permite_existencias'],
                    'actualizado_por_id' => $actor->id,
                ]);

                $almacenBloqueado->refresh();

                $valoresNuevos = [
                    'codigo' => $almacenBloqueado->codigo,
                    'nombre' => $almacenBloqueado->nombre,
                    'tipo' => $almacenBloqueado->tipo,
                    'permite_existencias' => (bool) $almacenBloqueado->permite_existencias,
                ];

                $this->registrarAuditoria->registrar(
                    accion: 'almacen.actualizado',
                    modulo: 'almacenes',
                    descripcion: 'Se actualizó la información del almacén.',
                    actor: $actor,
                    modelo: $almacenBloqueado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                        'sucursal_id' => $almacenBloqueado->sucursal_id,
                        'almacen_codigo' => $almacenBloqueado->codigo,
                        'almacen_nombre' => $almacenBloqueado->nombre,
                    ],
                    motivo: $datosNormalizados['motivo'],
                    request: $request
                );

                return $almacenBloqueado->load([
                    'empresa',
                    'sucursal',
                    'creadoPor',
                    'actualizadoPor',
                ]);
            }
        );
    }

    // ---------------------------------------------------------
    // NORMALIZACIÓN
    // Limpia cadenas, asegura tipos y asigna valores normalizados.
    // ---------------------------------------------------------

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *     codigo: string,
     *     nombre: string,
     *     tipo: string,
     *     permite_existencias: bool,
     *     motivo: string
     * }
     */
    private function normalizarDatos(array $datos): array
    {
        $tipo = Str::lower(
            trim((string) ($datos['tipo'] ?? ''))
        );

        return [
            'codigo' => Str::upper(
                trim((string) ($datos['codigo'] ?? ''))
            ),
            'nombre' => trim(
                (string) ($datos['nombre'] ?? '')
            ),
            'tipo' => $tipo,
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
    // Comprueba permisos, pertenencia, reglas de negocio y unicidad.
    // ---------------------------------------------------------

    /**
     * @param  array{
     *     codigo: string,
     *     nombre: string,
     *     tipo: string,
     *     permite_existencias: bool,
     *     motivo: string
     * }  $datos
     */
    private function validar(
        Empresa $empresa,
        Almacen $almacen,
        User $actor,
        array $datos
    ): void {
        if (! $actor->can('almacenes.actualizar')) {
            throw new AuthorizationException(
                'No tienes permiso para actualizar almacenes.'
            );
        }

        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden actualizar almacenes de una empresa inactiva.',
            ]);
        }

        if ((int) $almacen->empresa_id !== (int) $empresa->id) {
            throw new AuthorizationException(
                'El almacén no pertenece a la empresa indicada.'
            );
        }

        if (! $almacen->activo) {
            throw ValidationException::withMessages([
                'almacen' => 'No se puede actualizar un almacén inactivo.',
            ]);
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
                'tipo' => 'Los almacenes de tránsito no pueden gestionarse de forma manual.',
            ]);
        }

        if ($almacen->esTransito()) {
            throw ValidationException::withMessages([
                'tipo' => 'El almacén de tránsito no puede ser modificado.',
            ]);
        }

        if (
            $datos['tipo'] === Almacen::TIPO_PRINCIPAL
            && $almacen->tipo !== Almacen::TIPO_PRINCIPAL
        ) {
            $existePrincipal = Almacen::query()
                ->where('sucursal_id', $almacen->sucursal_id)
                ->where('tipo', Almacen::TIPO_PRINCIPAL)
                ->where('id', '!=', $almacen->id)
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
            ->where('id', '!=', $almacen->id)
            ->exists();

        if ($existeCodigo) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya existe un almacén con ese código en la empresa.',
            ]);
        }

        if ($datos['motivo'] === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar el motivo de la actualización del almacén.',
            ]);
        }

        if (mb_strlen($datos['motivo']) > 1000) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo no puede superar los 1000 caracteres.',
            ]);
        }

        $sinCambios = $almacen->codigo === $datos['codigo']
            && $almacen->nombre === $datos['nombre']
            && $almacen->tipo === $datos['tipo']
            && (bool) $almacen->permite_existencias === (bool) $datos['permite_existencias'];

        if ($sinCambios) {
            throw ValidationException::withMessages([
                'almacen' => 'La actualización requiere al menos un cambio.',
            ]);
        }
    }
}
