<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Servicio Centralizado de Auditoría del Sistema.
 *
 * Registra eventos clave en la bitácora inmutable de auditoría (`auditorias`), capturando
 * el contexto completo de la operación: actor responsable, sujeto o modelo afectado,
 * estado antes y después del cambio (diff/snapshots), metadatos adicionales y contexto
 * HTTP del cliente (IP, User-Agent, URI y método HTTP).
 *
 * Principios de seguridad aplicados:
 * - Sanitización preventiva: Purga automática de credenciales y tokens sensibles antes de persistir.
 * - Resiliencia contextual: Puede ejecutarse tanto en peticiones HTTP web/API como en comandos CLI o tareas en cola.
 */
class RegistrarAuditoria
{
    /**
     * Lista negra de claves cuyos valores nunca deben registrarse en la bitácora
     * para prevenir filtraciones de credenciales, secretos criptográficos o tokens de sesión.
     *
     * @var array<int, string>
     */
    private const CAMPOS_SENSIBLES = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'cookie',
        'authorization',
    ];

    /**
     * Crea y persiste un nuevo registro de auditoría en la base de datos.
     *
     * Extrae información contextual de la petición HTTP actual (o del objeto Request suministrado),
     * aplica filtros de sanitización para eliminar claves reservadas y asocia las claves
     * foráneas polimórficas del modelo auditado si se proporciona.
     *
     * @param  string  $accion  Identificador técnico del evento (ej: 'proveedor.actualizado', 'orden.entregada').
     * @param  string  $modulo  Nombre del subsistema o dominio funcional (ej: 'proveedores', 'ordenes', 'seguridad').
     * @param  string  $descripcion  Resumen legible en lenguaje natural de la acción efectuada.
     * @param  User|null  $actor  Usuario que desencadenó la acción (null si proviene de cron, CLI o invitado).
     * @param  Model|null  $modelo  Instancia del modelo de Eloquent sobre el cual recae la operación (opcional).
     * @param  User|null  $usuarioAfectado  Usuario secundario destinatario de la acción (ej: cambio de rol a otro usuario).
     * @param  array<string, mixed>|null  $valoresAnteriores  Fotografía de los datos antes de la modificación.
     * @param  array<string, mixed>|null  $valoresNuevos  Fotografía de los datos posteriores a la modificación.
     * @param  array<string, mixed>|null  $metadatos  Información complementaria contextual o parámetros específicos.
     * @param  string|null  $motivo  Justificación operativa provista por el operador (ej: cancelación o ajuste).
     * @param  string  $resultado  Estado del desenlace ('exitoso', 'fallido', 'bloqueado').
     * @param  Request|null  $request  Petición HTTP a inspeccionar (si es null, usa el helper request() global).
     * @return Auditoria Instancia creada del registro de auditoría.
     */
    public function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        ?User $actor = null,
        ?Model $modelo = null,
        ?User $usuarioAfectado = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?array $metadatos = null,
        ?string $motivo = null,
        string $resultado = 'exitoso',
        ?Request $request = null
    ): Auditoria {
        // Resuelve la petición HTTP actual si no fue inyectada explícitamente
        $request ??= request();

        return Auditoria::query()->create([
            'usuario_id' => $actor?->id,
            'usuario_afectado_id' => $usuarioAfectado?->id,
            'sesion_id' => $this->obtenerSesionId($request),
            'accion' => $accion,
            'modulo' => $modulo,
            // Identificación polimórfica del modelo de Eloquent asociado
            'modelo_tipo' => $modelo !== null
                ? $modelo::class
                : null,
            'modelo_id' => $modelo?->getKey(),
            'descripcion' => $descripcion,
            // Sanitización de estructuras JSON contra claves sensibles
            'valores_anteriores' => $this->filtrar(
                $valoresAnteriores
            ),
            'valores_nuevos' => $this->filtrar(
                $valoresNuevos
            ),
            'metadatos' => $this->filtrar($metadatos),
            'motivo' => $motivo,
            // Contexto telemático de la conexión del cliente
            'direccion_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'ruta' => $request->path(),
            'metodo_http' => $request->method(),
            'resultado' => $resultado,
        ]);
    }

    /**
     * Purga y excluye las claves de atributos sensibles de un arreglo antes de guardarlo.
     *
     * @param  array<string, mixed>|null  $datos  Estructura asociativa original a evaluar.
     * @return array<string, mixed>|null Estructura depurada sin claves sensibles, o null si la entrada era null.
     */
    private function filtrar(
        ?array $datos
    ): ?array {
        if ($datos === null) {
            return null;
        }

        // Remueve del array cualquier clave presente en la lista de CAMPOS_SENSIBLES
        return Arr::except(
            $datos,
            self::CAMPOS_SENSIBLES
        );
    }

    /**
     * Obtiene el identificador alfanumérico de la sesión activa de forma segura.
     *
     * Si la petición proviene de un contexto sin controlador de sesión (por ejemplo,
     * llamadas de consola de Artisan, pruebas unitarias o llamadas API sin estado),
     * evita invocar métodos no definidos retornando null.
     *
     * @param  Request  $request  Petición HTTP a inspeccionar.
     * @return string|null Identificador de sesión o null si no existe.
     */
    private function obtenerSesionId(
        Request $request
    ): ?string {
        if (! $request->hasSession()) {
            return null;
        }

        return $request->session()->getId();
    }
}
