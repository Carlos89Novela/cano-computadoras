<?php

namespace App\Enums;

/**
 * Enumeración y Máquina de Estados para las Órdenes de Servicio en Taller.
 *
 * Define las fases del ciclo de vida de un equipo en reparación y gobierna
 * el grafo estricto de transiciones admitidas entre estados operativos:
 * - RECIBIDO: El equipo ingresó al taller y fue registrado formalmente.
 * - EN_DIAGNOSTICO: El técnico asignado evalúa la falla y formula presupuesto.
 * - ESPERANDO_AUTORIZACION: El diagnóstico y costo fueron aprobados por supervisión y esperan al cliente.
 * - ESPERANDO_REFACCION: Autorizado por cliente, en espera de piezas o partes de proveedor.
 * - EN_REPARACION: Técnico ejecutando activamente las tareas de reparación física/lógica.
 * - EN_PRUEBAS: Fase de control de calidad, estabilidad y verificación de funcionalidad.
 * - LISTO_PARA_ENTREGA: Trabajos finalizados y costo definitivo establecido; espera al cliente.
 * - ENTREGADO: Equipo devuelto físicamente al cliente con conformidad (estado terminal).
 * - CANCELADO: Orden cancelada por el cliente o taller (estado terminal).
 */
enum EstadoOrden: string
{
    case RECIBIDO = 'Recibido';

    case EN_DIAGNOSTICO = 'En diagnóstico';

    case ESPERANDO_AUTORIZACION = 'Esperando autorización';

    case ESPERANDO_REFACCION = 'Esperando refacción';

    case EN_REPARACION = 'En reparación';

    case EN_PRUEBAS = 'En pruebas';

    case LISTO_PARA_ENTREGA = 'Listo para entrega';

    case ENTREGADO = 'Entregado';

    case CANCELADO = 'Cancelado';

    /**
     * Obtiene todos los valores escalares permitidos.
     *
     * @return array<int, string> Lista de valores de estados.
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtiene los estados que representan el fin definitivo del ciclo de vida (estados terminales).
     *
     * @return array<int, string> Lista de estados finales ('Entregado', 'Cancelado').
     */
    public static function finalizados(): array
    {
        return [
            self::ENTREGADO->value,
            self::CANCELADO->value,
        ];
    }

    /**
     * Obtiene los estados utilizados como filtros rápidos en paneles de administración y supervisión.
     *
     * La clave representa el valor real almacenado en la base de datos
     * y el valor representa el texto descriptivo mostrado en la interfaz.
     *
     * @return array<string, string> Matriz asociativa de valor => etiqueta.
     */
    public static function filtrosRapidos(): array
    {
        return [
            self::RECIBIDO->value => 'Recibido',
            self::EN_DIAGNOSTICO->value => 'En diagnóstico',
            self::ESPERANDO_AUTORIZACION->value => 'Esperando autorización',
            self::ESPERANDO_REFACCION->value => 'Esperando refacción',
            self::EN_REPARACION->value => 'En reparación',
            self::EN_PRUEBAS->value => 'En pruebas',
            self::LISTO_PARA_ENTREGA->value => 'Listo',
            self::ENTREGADO->value => 'Entregado',
            self::CANCELADO->value => 'Cancelado',
        ];
    }

    /**
     * Define el grafo determinista de transiciones válidas salientes desde el estado actual.
     *
     * Los estados terminales ENTREGADO y CANCELADO retornan un arreglo vacío,
     * garantizando su inmutabilidad en la lógica de negocio.
     *
     * @return array<int, self> Arreglo de estados destinos válidos.
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            // Desde recepción puede iniciar diagnóstico, pedir autorización directa, iniciar trabajo o cancelarse
            self::RECIBIDO => [
                self::EN_DIAGNOSTICO,
                self::ESPERANDO_AUTORIZACION,
                self::EN_REPARACION,
                self::CANCELADO,
            ],

            // Al concluir diagnóstico puede pedir autorización, solicitar piezas o iniciar trabajo
            self::EN_DIAGNOSTICO => [
                self::ESPERANDO_AUTORIZACION,
                self::ESPERANDO_REFACCION,
                self::EN_REPARACION,
                self::CANCELADO,
            ],

            // Al recibir respuesta del cliente puede esperar piezas o pasar a mesa de trabajo
            self::ESPERANDO_AUTORIZACION => [
                self::ESPERANDO_REFACCION,
                self::EN_REPARACION,
                self::CANCELADO,
            ],

            // Al llegar las refacciones pasa a reparación directa
            self::ESPERANDO_REFACCION => [
                self::EN_REPARACION,
                self::CANCELADO,
            ],

            // Durante la reparación puede requerir piezas adicionales, pasar a pruebas o quedar lista
            self::EN_REPARACION => [
                self::ESPERANDO_REFACCION,
                self::EN_PRUEBAS,
                self::LISTO_PARA_ENTREGA,
                self::CANCELADO,
            ],

            // En pruebas puede requerir ajustes adicionales en reparación o quedar lista para entrega
            self::EN_PRUEBAS => [
                self::EN_REPARACION,
                self::LISTO_PARA_ENTREGA,
                self::CANCELADO,
            ],

            // Lista para entrega puede reabrirse para ajustes técnicos o proceder a entrega física
            self::LISTO_PARA_ENTREGA => [
                self::EN_REPARACION,
                self::ENTREGADO,
                self::CANCELADO,
            ],

            // Estados terminales inmutables
            self::ENTREGADO => [],

            self::CANCELADO => [],
        };
    }

    /**
     * Obtiene los valores de texto de los estados permitidos a partir del estado actual.
     *
     * @return array<int, string> Arreglo de valores de texto admitidos.
     */
    public function valoresPermitidos(): array
    {
        return array_map(
            fn (self $estado): string => $estado->value,
            $this->transicionesPermitidas()
        );
    }

    /**
     * Evalúa si una transición hacia un estado destino específico es legal.
     *
     * @param  self  $nuevoEstado  Estado objetivo al que se desea mover la orden.
     * @return bool Verdadero si la transición está contemplada en el grafo de la máquina de estados.
     */
    public function permiteTransicionA(self $nuevoEstado): bool
    {
        return in_array(
            $nuevoEstado,
            $this->transicionesPermitidas(),
            true
        );
    }
}
