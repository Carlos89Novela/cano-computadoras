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
 * Acción del dominio: CrearProveedor.
 *
 * Encapsula la lógica de negocio completa para registrar un nuevo proveedor:
 * 1. Normalización y sanitización de datos (limpieza de espacios, mayúsculas en códigos y RFC, minúsculas en correos).
 * 2. Validación de autorizaciones (permiso 'productos.crear') y reglas de negocio.
 * 3. Ejecución atómica en DB::transaction con bloqueo pesimista (lockForUpdate) para evitar colisiones concurrentes.
 * 4. Verificación de unicidad de código insensible a mayúsculas dentro de la empresa.
 * 5. Registro automático de auditoría inmutable con metadatos de la petición HTTP.
 */
class CrearProveedor
{
    /**
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio centralizado de auditoría del sistema.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Ejecuta el proceso de creación del proveedor.
     *
     * @param  Empresa  $empresa  Empresa a la que pertenecerá el proveedor.
     * @param  User  $actor  Usuario que ejecuta la acción.
     * @param  array<string, mixed>  $datos  Datos crudos del proveedor.
     * @param  Request|null  $request  Petición HTTP actual (para extraer IP y User-Agent en auditoría).
     * @return Proveedor Instancia creada y recargada con sus relaciones clave.
     *
     * @throws AuthorizationException Si el usuario no tiene permisos suficientes.
     * @throws ValidationException Si los datos no cumplen las reglas o el código ya existe.
     */
    public function ejecutar(
        Empresa $empresa,
        User $actor,
        array $datos,
        ?Request $request = null
    ): Proveedor {
        // Paso 1: Normaliza y sanea las entradas de texto antes de validar
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // Paso 2: Validación temprana (Fail Fast) para no abrir transacciones innecesarias si fallan reglas básicas
        $this->validar(
            empresa: $empresa,
            actor: $actor,
            datos: $datosNormalizados
        );

        // Si no se proporcionó una Request explícita, se obtiene la actual del contenedor
        $request ??= request();

        // Paso 3: Transacción de base de datos para garantizar atomicidad y consistencia
        return DB::transaction(
            function () use (
                $empresa,
                $actor,
                $datosNormalizados,
                $request
            ): Proveedor {
                // Bloqueo pesimista sobre la empresa para prevenir escrituras concurrentes conflictivas
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                // Re-valida el estado de la empresa ya con el registro bloqueado
                $this->validar(
                    empresa: $empresaBloqueada,
                    actor: $actor,
                    datos: $datosNormalizados
                );

                $codigo = (string) $datosNormalizados[
                    'codigo'
                ];

                // Comprueba si ya existe un proveedor con ese código en la misma empresa (LOWER para case-insensitivity)
                $codigoDuplicado = Proveedor::query()
                    ->where(
                        'empresa_id',
                        $empresaBloqueada->id
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

                // Paso 4: Creación del proveedor en base de datos
                $proveedor = Proveedor::query()->create([
                    'empresa_id' => $empresaBloqueada->id,
                    'codigo' => $codigo,
                    'nombre' => $datosNormalizados['nombre'],
                    'razon_social' => $datosNormalizados['razon_social'],
                    'rfc' => $datosNormalizados['rfc'],
                    'contacto' => $datosNormalizados['contacto'],
                    'telefono' => $datosNormalizados['telefono'],
                    'correo' => $datosNormalizados['correo'],
                    'direccion' => $datosNormalizados['direccion'],
                    'notas' => $datosNormalizados['notas'],
                    'activo' => true,
                    'creado_por_id' => $actor->id,
                ]);

                // Paso 5: Registro de auditoría con instantánea completa de los nuevos valores
                $this->registrarAuditoria->registrar(
                    accion: 'proveedor.creado',
                    modulo: 'proveedores',
                    descripcion: 'Se creó un proveedor.',
                    actor: $actor,
                    modelo: $proveedor,
                    valoresNuevos: [
                        'empresa_id' => $empresaBloqueada->id,
                        'proveedor_id' => $proveedor->id,
                        'codigo' => $proveedor->codigo,
                        'nombre' => $proveedor->nombre,
                        'razon_social' => $proveedor->razon_social,
                        'rfc' => $proveedor->rfc,
                        'contacto' => $proveedor->contacto,
                        'telefono' => $proveedor->telefono,
                        'correo' => $proveedor->correo,
                        'direccion' => $proveedor->direccion,
                        'notas' => $proveedor->notas,
                        'activo' => $proveedor->activo,
                    ],
                    metadatos: [
                        'empresa_nombre' => $empresaBloqueada->nombre,
                    ],
                    request: $request
                );

                // Paso 6: Retorna el modelo fresco con sus relaciones esenciales precargadas
                return $proveedor
                    ->fresh()
                    ->load([
                        'empresa',
                        'creadoPor',
                    ]);
            }
        );
    }

    /**
     * Limpia y normaliza los valores del arreglo de datos.
     * Transforma campos vacíos a null y estandariza mayúsculas/minúsculas.
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
     * Convierte una cadena opcional en null si está vacía o contiene solo espacios en blanco.
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
     * Procesa un campo de texto opcional y lo convierte a mayúsculas si existe.
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
     * Procesa un correo opcional y lo convierte a minúsculas si existe.
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
     * Valida permisos del usuario, estado de la empresa y formato de los datos del proveedor.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws AuthorizationException Si el actor carece del permiso 'productos.crear'.
     * @throws ValidationException Si alguna regla de validación falla.
     */
    private function validar(
        Empresa $empresa,
        User $actor,
        array $datos
    ): void {
        // Verifica que el usuario tenga el permiso Spatie correspondiente
        if (! $actor->can('productos.crear')) {
            throw new AuthorizationException(
                'No tienes permiso para crear proveedores.'
            );
        }

        // Una empresa inactiva no puede registrar nuevos proveedores
        if (! $empresa->activo) {
            throw ValidationException::withMessages([
                'empresa' => 'No se pueden crear proveedores para una empresa inactiva.',
            ]);
        }

        // Valida el formato del código (alfanumérico con guiones, obligatorio, máx 40)
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

        // Valida el nombre del proveedor (obligatorio, máx 200)
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

        // Valida longitudes máximas en campos opcionales
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

        // Valida sintaxis del correo si está presente
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
     * Valida que un campo opcional no supere la longitud de caracteres permitida.
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
