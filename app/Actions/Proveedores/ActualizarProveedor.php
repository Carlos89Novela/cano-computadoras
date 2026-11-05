<?php

namespace App\Actions\Proveedores;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Acción del dominio: ActualizarProveedor.
 *
 * Se encarga de procesar y persistir las modificaciones en los datos generales de un proveedor:
 * 1. Sanitiza y normaliza las entradas.
 * 2. Valida la autorización ('productos.actualizar'), estado activo de la empresa y pertenencia del proveedor a la misma.
 * 3. Ejecuta la operación bajo una transacción atómica con bloqueo pesimista (lockForUpdate) en Empresa y Proveedor.
 * 4. Verifica que el código no esté duplicado en otros proveedores de la empresa (ignorando el registro actual).
 * 5. Detecta si hubo cambios reales para evitar escrituras y logs de auditoría redundantes.
 * 6. Registra la auditoría con comparación detallada de 'valoresAnteriores' vs 'valoresNuevos'.
 */
class ActualizarProveedor
{
    /**
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio para el registro inmutable de auditorías.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Ejecuta la actualización del proveedor.
     *
     * @param  Empresa  $empresa  Empresa propietaria del proveedor.
     * @param  Proveedor  $proveedor  Instancia actual del proveedor a modificar.
     * @param  User  $actor  Usuario que ejecuta la acción.
     * @param  array<string, mixed>  $datos  Nuevos datos a aplicar.
     * @param  Request|null  $request  Petición HTTP para capturar IP y User-Agent en auditoría.
     * @return Proveedor Proveedor actualizado con relaciones recargadas.
     *
     * @throws AuthorizationException Si el usuario no tiene permisos o el proveedor pertenece a otra empresa.
     * @throws ValidationException Si los datos son inválidos, el código ya se usa en otro registro o no hubo cambios.
     */
    public function ejecutar(
        Empresa $empresa,
        Proveedor $proveedor,
        User $actor,
        array $datos,
        ?Request $request = null
    ): Proveedor {
        // Paso 1: Normaliza los datos antes de realizar validaciones
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // Paso 2: Validación rápida en memoria (Fail-Fast)
        $this->validar(
            empresa: $empresa,
            proveedor: $proveedor,
            actor: $actor,
            datos: $datosNormalizados
        );

        $request ??= request();

        // Paso 3: Transacción atómica en base de datos
        return DB::transaction(
            function () use (
                $empresa,
                $proveedor,
                $actor,
                $datosNormalizados,
                $request
            ): Proveedor {
                // Bloqueo pesimista para evitar que la empresa o el proveedor sean modificados en paralelo
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $proveedorBloqueado = Proveedor::query()
                    ->lockForUpdate()
                    ->findOrFail($proveedor->id);

                // Re-valida con los datos frescos y bloqueados
                $this->validar(
                    empresa: $empresaBloqueada,
                    proveedor: $proveedorBloqueado,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                $codigo = (string) $datosNormalizados['codigo'];

                // Comprueba que el código no lo tenga asignado OTRO proveedor dentro de la misma empresa
                $codigoDuplicado = Proveedor::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
                    )
                    ->whereKeyNot(
                        $proveedorBloqueado->id
                    )
                    ->whereRaw(
                        'LOWER(codigo) = ?',
                        [
                            mb_strtolower($codigo),
                        ]
                    )
                    ->exists();

                if ($codigoDuplicado) {
                    throw ValidationException::withMessages([
                        'codigo' => 'Ya existe un proveedor con ese código.',
                    ]);
                }

                // Captura el estado anterior para comparar y auditar
                $valoresAnteriores = [
                    'codigo' => $proveedorBloqueado->codigo,
                    'nombre' => $proveedorBloqueado->nombre,
                    'razon_social' => $proveedorBloqueado->razon_social,
                    'rfc' => $proveedorBloqueado->rfc,
                    'contacto' => $proveedorBloqueado->contacto,
                    'telefono' => $proveedorBloqueado->telefono,
                    'correo' => $proveedorBloqueado->correo,
                    'direccion' => $proveedorBloqueado->direccion,
                    'notas' => $proveedorBloqueado->notas,
                ];

                // Prepara el arreglo con los nuevos valores normalizados
                $valoresNuevos = [
                    'codigo' => $codigo,
                    'nombre' => $datosNormalizados['nombre'],
                    'razon_social' => $datosNormalizados['razon_social'],
                    'rfc' => $datosNormalizados['rfc'],
                    'contacto' => $datosNormalizados['contacto'],
                    'telefono' => $datosNormalizados['telefono'],
                    'correo' => $datosNormalizados['correo'],
                    'direccion' => $datosNormalizados['direccion'],
                    'notas' => $datosNormalizados['notas'],
                ];

                // Regla de negocio: Si ningún campo cambió, no se ejecuta actualización ni se ensucia la auditoría
                if ($valoresAnteriores === $valoresNuevos) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Debes modificar al menos un dato del proveedor.',
                    ]);
                }

                // Paso 4: Actualiza el proveedor y registra al usuario editor
                $proveedorBloqueado->update([
                    ...$valoresNuevos,
                    'actualizado_por_id' => $actor->id,
                ]);

                // Paso 5: Registro de auditoría con diferencias (antes vs después)
                $this->registrarAuditoria->registrar(
                    accion: 'proveedor.actualizado',
                    modulo: 'proveedores',
                    descripcion: 'Se actualizaron los datos de un proveedor.',
                    actor: $actor,
                    modelo: $proveedorBloqueado,
                    valoresAnteriores: $valoresAnteriores,
                    valoresNuevos: $valoresNuevos,
                    metadatos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'empresa_nombre' => $empresaBloqueada->nombre,
                    ],
                    request: $request
                );

                // Paso 6: Recarga y retorna el modelo fresco con relaciones clave
                return $proveedorBloqueado
                    ->refresh()
                    ->load([
                        'empresa',
                        'creadoPor',
                        'actualizadoPor',
                        'desactivadoPor',
                    ]);
            }
        );
    }

    /**
     * Limpia y estandariza los datos de entrada.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizarDatos(
        array $datos
    ): array {
        $codigo = $datos['codigo'] ?? '';
        $nombre = $datos['nombre'] ?? '';

        return [
            'codigo' => is_string($codigo)
                ? Str::upper(trim($codigo))
                : '',
            'nombre' => is_string($nombre)
                ? trim($nombre)
                : '',
            'razon_social' => $this->textoOpcional(
                $datos['razon_social'] ?? null
            ),
            'rfc' => $this->textoMayusculasOpcional(
                $datos['rfc'] ?? null
            ),
            'contacto' => $this->textoOpcional(
                $datos['contacto'] ?? null
            ),
            'telefono' => $this->textoOpcional(
                $datos['telefono'] ?? null
            ),
            'correo' => $this->correoOpcional(
                $datos['correo'] ?? null
            ),
            'direccion' => $this->textoOpcional(
                $datos['direccion'] ?? null
            ),
            'notas' => $this->textoOpcional(
                $datos['notas'] ?? null
            ),
        ];
    }

    /**
     * Convierte un valor string en null si está vacío o solo contiene espacios.
     */
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
     * Limpia y convierte a mayúsculas un texto opcional.
     */
    private function textoMayusculasOpcional(
        mixed $valor
    ): ?string {
        $texto = $this->textoOpcional($valor);

        return $texto === null
            ? null
            : Str::upper($texto);
    }

    /**
     * Limpia y convierte a minúsculas un correo opcional.
     */
    private function correoOpcional(
        mixed $valor
    ): ?string {
        $correo = $this->textoOpcional($valor);

        return $correo === null
            ? null
            : Str::lower($correo);
    }

    /**
     * Valida permisos, límites de empresa y restricciones de formato.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws AuthorizationException Si no tiene permisos o hay discrepancia de empresa.
     * @throws ValidationException Si los campos no cumplen las reglas de formato o longitud.
     */
    private function validar(
        Empresa $empresa,
        Proveedor $proveedor,
        User $actor,
        array $datos
    ): void {
        // Valida permiso de actualización de catálogo de productos/proveedores
        if (! $actor->can('productos.actualizar')) {
            throw new AuthorizationException(
                'No tienes permiso para actualizar proveedores.'
            );
        }

        // Valida que la empresa no esté suspendida o inactiva
        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden actualizar proveedores de una empresa inactiva.',
            ]);
        }

        // Seguridad multi-empresa: Previene que se modifique un proveedor bajo la ruta de otra empresa
        if (
            (int) $proveedor->empresa_id
            !== (int) $empresa->id
        ) {
            throw new AuthorizationException(
                'El proveedor no pertenece a la empresa indicada.'
            );
        }

        // Valida formato y longitud del código
        $codigo = $datos['codigo'] ?? '';

        if (
            ! is_string($codigo)
            || $codigo === ''
            || mb_strlen($codigo) > 40
            || preg_match(
                '/^[A-Z0-9-]+$/',
                $codigo
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'codigo' => 'El código debe contener letras, números o guiones y no superar 40 caracteres.',
            ]);
        }

        // Valida nombre
        $nombre = $datos['nombre'] ?? '';

        if (
            ! is_string($nombre)
            || $nombre === ''
            || mb_strlen($nombre) > 200
        ) {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre del proveedor es obligatorio y no puede superar 200 caracteres.',
            ]);
        }

        // Valida campos opcionales
        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'razon_social',
            maximo: 200,
            mensaje: 'La razón social no puede superar 200 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'rfc',
            maximo: 20,
            mensaje: 'El RFC no puede superar 20 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'contacto',
            maximo: 150,
            mensaje: 'El nombre del contacto no puede superar 150 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'telefono',
            maximo: 30,
            mensaje: 'El teléfono no puede superar 30 caracteres.'
        );

        // Valida correo electrónico
        $correo = $datos['correo'] ?? null;

        if (
            $correo !== null
            && (
                ! is_string($correo)
                || mb_strlen($correo) > 255
                || filter_var(
                    $correo,
                    FILTER_VALIDATE_EMAIL
                ) === false
            )
        ) {
            throw ValidationException::withMessages([
                'correo' => 'El correo del proveedor no es válido.',
            ]);
        }

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'direccion',
            maximo: 2000,
            mensaje: 'La dirección no puede superar 2000 caracteres.'
        );

        $this->validarLongitudOpcional(
            datos: $datos,
            campo: 'notas',
            maximo: 4000,
            mensaje: 'Las notas no pueden superar 4000 caracteres.'
        );
    }

    /**
     * Valida que el texto no sobrepase el límite máximo de caracteres.
     *
     * @param  array<string, mixed>  $datos
     */
    private function validarLongitudOpcional(
        array $datos,
        string $campo,
        int $maximo,
        string $mensaje
    ): void {
        $valor = $datos[$campo] ?? null;

        if ($valor === null) {
            return;
        }

        if (
            ! is_string($valor)
            || mb_strlen($valor) > $maximo
        ) {
            throw ValidationException::withMessages([
                $campo => $mensaje,
            ]);
        }
    }
}
