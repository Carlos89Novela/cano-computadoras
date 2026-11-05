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

/**
 * Acción del Dominio de Sucursales: Apertura Operativa de Sucursal.
 *
 * Orquesta el aprovisionamiento integral de una nueva sucursal dentro de una empresa:
 * 1. Autorización estricta: Acción reservada exclusivamente para el Propietario del sistema (`esPropietario()`).
 * 2. Asignación gerencial: Asocia un gerente responsable con rol de 'supervisor' o 'administrador'.
 * 3. Determinación de sede principal: La primera sucursal se convierte automáticamente en principal;
 *    las subsecuentes respetan la exclusividad de sede principal única por empresa.
 * 4. Aprovisionamiento logístico automático:
 *    - Crea el Almacén Principal físico de la sucursal (`ALM-{CODIGO}`).
 *    - Garantiza la existencia del Almacén Virtual de Tránsito de la empresa (`TRANSITO`).
 * 5. Vinculación en tabla pivote: Asocia al gerente en `sucursal_usuario` con flags `es_principal` y `es_gerente`.
 * 6. Bitácora de auditoría: Emite el evento inmutable 'sucursal.abierta' con el detalle de entidades creadas.
 */
class AbrirSucursal
{
    /**
     * Inyecta el servicio de auditoría inmutable.
     *
     * @param  RegistrarAuditoria  $registrarAuditoria  Servicio centralizado de bitácora.
     */
    public function __construct(
        private RegistrarAuditoria $registrarAuditoria
    ) {}

    /**
     * Ejecuta el aprovisionamiento atómico de la sucursal, sus almacenes y la asignación del gerente.
     *
     * @param  Empresa  $empresa  Empresa multi-tenant donde se apertura la sucursal.
     * @param  User  $actor  Usuario propietario que autoriza y abre la sucursal.
     * @param  User  $gerente  Usuario designado como titular de la sucursal.
     * @param  array<string, mixed>  $datos  Atributos de la sucursal (código, nombre, contacto, dirección, etc.).
     * @param  Request|null  $request  Petición HTTP entrante para metadatos de auditoría.
     * @return Sucursal Instancia de la sucursal creada con sus relaciones cargadas.
     *
     * @throws AuthorizationException Si el actor no es propietario o no es administrador.
     * @throws ValidationException Si la empresa está inactiva, el gerente no califica o hay colisión de códigos.
     */
    public function ejecutar(
        Empresa $empresa,
        User $actor,
        User $gerente,
        array $datos,
        ?Request $request = null
    ): Sucursal {
        // Normaliza textos, mayúsculas en códigos y valores nulos/vacíos
        $datosNormalizados = $this->normalizarDatos(
            $datos
        );

        // Valida reglas preliminares de autorización, rol del gerente y formatos
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
                // Bloqueo pesimista sobre la empresa y el gerente para garantizar atomicidad
                $empresaBloqueada = Empresa::query()
                    ->lockForUpdate()
                    ->findOrFail($empresa->id);

                $gerenteBloqueado = User::query()
                    ->lockForUpdate()
                    ->findOrFail($gerente->id);

                // Revalida estados sobre los modelos bloqueados
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

                // Comprueba que no exista colisión de código en la misma empresa
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

                // Evalúa si es la primera sucursal de la empresa para asignarla como principal por defecto
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

                // Si solicita ser principal y no es la primera, verifica que no exista ya otra sede principal
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

                // Creación de la sucursal
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

                // Aprovisionamiento logístico 1: Almacén físico principal para la nueva sucursal
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

                // Aprovisionamiento logístico 2: Garantiza el Almacén Virtual de Tránsito de la empresa
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

                // Asignación gerencial: Vincula al gerente en la tabla pivote sucursal_usuario
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

                // Asienta el evento en la bitácora inmutable de auditoría
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
     * Limpia y estandariza los tipos de datos recibidos del formulario/payload.
     *
     * @param  array<string, mixed>  $datos  Valores en crudo.
     * @return array<string, mixed> Valores estructurados y formateados.
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

    /**
     * Convierte cadenas vacías a nulos limpios.
     *
     * @param  mixed  $valor  Dato a evaluar.
     * @return string|null Cadena sin espacios o null si estaba vacía.
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
     * Valida la jerarquía de privilegios, habilitación de la empresa y cualificación gerencial.
     *
     * @param  Empresa  $empresa  Empresa a la que pertenecerá la sucursal.
     * @param  User  $actor  Usuario ejecutante.
     * @param  User  $gerente  Candidato a gerente de sucursal.
     * @param  array<string, mixed>  $datos  Datos normalizados de la sucursal.
     *
     * @throws AuthorizationException Si el usuario no es el propietario.
     * @throws ValidationException Si la empresa está inactiva o los campos no cumplen formato.
     */
    private function validar(
        Empresa $empresa,
        User $actor,
        User $gerente,
        array $datos
    ): void {
        // Regla estricta: Solo el Propietario con rol Administrador puede abrir sucursales
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

        // El gerente debe contar con rol jerárquico adecuado
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
