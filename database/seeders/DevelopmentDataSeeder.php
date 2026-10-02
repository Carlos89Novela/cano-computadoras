<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Servicio;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevelopmentDataSeeder extends Seeder
{
    // ---------------------------------------------------------
    // DATOS DE DESARROLLO
    // Reconstruye las cuentas locales necesarias para probar
    // los diferentes niveles de acceso del sistema.
    // ---------------------------------------------------------

    public function run(): void
    {
        // -----------------------------------------------------
        // ROLES Y PERMISOS
        // Garantiza que los roles y permisos existan antes de
        // asignarlos a las cuentas reconstruidas.
        // -----------------------------------------------------

        $this->call(
            RolesAndPermissionsSeeder::class
        );

        // -----------------------------------------------------
        // CONTRASEÑA LOCAL
        // Permite definir una contraseña desde el archivo
        // .env y utiliza una temporal si no está configurada.
        // -----------------------------------------------------

        $password = (string) env(
            'DEVELOPMENT_USER_PASSWORD',
            'Cano2026!'
        );

        // -----------------------------------------------------
        // DEFINICIÓN DE USUARIOS
        // El campo role conserva compatibilidad con la columna
        // legacy y syncRoles configura los roles de Spatie.
        // -----------------------------------------------------

        $usuarios = [
            [
                'name' => 'Administrador propietario',
                'email' => 'administrador@cano.test',
                'role' => 'administrador',
                'es_propietario' => true,
            ],
            [
                'name' => 'Administrador secundario',
                'email' => 'administrador.secundario@cano.test',
                'role' => 'administrador',
                'es_propietario' => false,
            ],
            [
                'name' => 'Supervisor',
                'email' => 'supervisor@cano.test',
                'role' => 'supervisor',
                'es_propietario' => false,
            ],
            [
                'name' => 'Empleado',
                'email' => 'empleado@cano.test',
                'role' => 'empleado',
                'es_propietario' => false,
            ],
            [
                'name' => 'Cliente uno',
                'email' => 'cliente1@cano.test',
                'role' => 'cliente',
                'es_propietario' => false,
            ],
            [
                'name' => 'Cliente dos',
                'email' => 'cliente2@cano.test',
                'role' => 'cliente',
                'es_propietario' => false,
            ],
        ];

        // -----------------------------------------------------
        // TRANSACCIÓN
        // La creación y asignación de roles se completa como
        // una sola operación para evitar datos incompletos.
        // -----------------------------------------------------

        DB::transaction(
            function () use (
                $usuarios,
                $password
            ): void {
                foreach ($usuarios as $datosUsuario) {
                    // -----------------------------------------
                    // LOCALIZAR O CREAR
                    // Utiliza el correo como identificador para
                    // evitar duplicados al ejecutar nuevamente.
                    // -----------------------------------------

                    $usuario = User::query()->firstOrNew([
                        'email' => $datosUsuario['email'],
                    ]);

                    // -----------------------------------------
                    // ACTUALIZAR ATRIBUTOS
                    // forceFill permite incluir role y
                    // email_verified_at aunque no estén dentro
                    // del arreglo fillable del modelo.
                    // -----------------------------------------

                    $usuario->forceFill([
                        'name' => $datosUsuario['name'],
                        'email' => $datosUsuario['email'],
                        'email_verified_at' => now(),
                        'password' => $password,
                        'role' => $datosUsuario['role'],
                        'es_propietario' => $datosUsuario[
                                'es_propietario'
                            ],
                    ]);

                    $usuario->save();

                    // -----------------------------------------
                    // ROL DE SPATIE
                    // Sincroniza un único rol y elimina roles
                    // incorrectos que pudiera tener la cuenta.
                    // -----------------------------------------

                    $usuario->syncRoles([
                        $datosUsuario['role'],
                    ]);
                }
            }
        );

        // -----------------------------------------------------
        // ENTORNO OPERATIVO
        // Reconstruye la empresa, sus dos sucursales, los
        // almacenes físicos y el almacén virtual de tránsito.
        // -----------------------------------------------------

        DB::transaction(function (): void {
            // -------------------------------------------------
            // USUARIOS RESPONSABLES
            // Recupera las cuentas creadas en el bloque
            // anterior para relacionarlas con la empresa.
            // -------------------------------------------------

            $propietario = User::query()
                ->where(
                    'email',
                    'administrador@cano.test'
                )
                ->firstOrFail();

            $administradorSecundario = User::query()
                ->where(
                    'email',
                    'administrador.secundario@cano.test'
                )
                ->firstOrFail();

            $supervisor = User::query()
                ->where(
                    'email',
                    'supervisor@cano.test'
                )
                ->firstOrFail();

            $empleado = User::query()
                ->where(
                    'email',
                    'empleado@cano.test'
                )
                ->firstOrFail();

            // -------------------------------------------------
            // EMPRESA
            // Crea o actualiza la empresa principal utilizando
            // su nombre como identificador de desarrollo.
            // -------------------------------------------------

            $empresa = Empresa::query()->updateOrCreate(
                [
                    'nombre' => 'Cano Computadoras',
                ],
                [
                    'activo' => true,
                    'creado_por_id' => $propietario->id,

                ]
            );

            // -------------------------------------------------
            // SUCURSAL PRINCIPAL
            // Reconstruye la sede principal de la empresa.
            // -------------------------------------------------

            $sucursalPrincipal = Sucursal::query()
                ->updateOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'codigo' => 'MATRIZ',
                    ],
                    [
                        'nombre' => 'Sucursal principal',
                        'telefono' => null,
                        'correo' => null,
                        'direccion' => null,
                        'ciudad' => 'Hermosillo',
                        'estado' => 'Sonora',
                        'codigo_postal' => null,
                        'es_principal' => true,
                        'activo' => true,
                        'creado_por_id' => $propietario->id,
                        'actualizado_por_id' => null,
                        'desactivado_por_id' => null,
                        'desactivado_at' => null,
                        'motivo_desactivacion' => null,
                    ]
                );

            // -------------------------------------------------
            // SUCURSAL NORTE
            // Reconstruye una segunda sede para validar el
            // funcionamiento multi-sucursal.
            // -------------------------------------------------

            $sucursalNorte = Sucursal::query()
                ->updateOrCreate(
                    [
                        'empresa_id' => $empresa->id,
                        'codigo' => 'NORTE',
                    ],
                    [
                        'nombre' => 'Sucursal Norte',
                        'telefono' => null,
                        'correo' => null,
                        'direccion' => null,
                        'ciudad' => 'Hermosillo',
                        'estado' => 'Sonora',
                        'codigo_postal' => null,
                        'es_principal' => false,
                        'activo' => true,
                        'creado_por_id' => $propietario->id,
                        'actualizado_por_id' => null,
                        'desactivado_por_id' => null,
                        'desactivado_at' => null,
                        'motivo_desactivacion' => null,
                    ]
                );

            // -------------------------------------------------
            // ALMACÉN PRINCIPAL DE MATRIZ
            // Cada sucursal mantiene su propio almacén físico
            // principal para controlar existencias separadas.
            // -------------------------------------------------

            Almacen::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'codigo' => 'ALM-MATRIZ',
                ],
                [
                    'sucursal_id' => $sucursalPrincipal->id,
                    'nombre' => 'Almacén Sucursal principal',
                    'tipo' => Almacen::TIPO_PRINCIPAL,
                    'es_virtual' => false,
                    'permite_existencias' => true,
                    'activo' => true,
                    'creado_por_id' => $propietario->id,
                    'actualizado_por_id' => null,
                    'desactivado_por_id' => null,
                    'desactivado_at' => null,
                    'motivo_desactivacion' => null,
                ]
            );

            // -------------------------------------------------
            // ALMACÉN PRINCIPAL DE NORTE
            // Mantiene las existencias de la segunda sucursal
            // separadas de la sede principal.
            // -------------------------------------------------

            Almacen::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'codigo' => 'ALM-NORTE',
                ],
                [
                    'sucursal_id' => $sucursalNorte->id,
                    'nombre' => 'Almacén Sucursal Norte',
                    'tipo' => Almacen::TIPO_PRINCIPAL,
                    'es_virtual' => false,
                    'permite_existencias' => true,
                    'activo' => true,
                    'creado_por_id' => $propietario->id,
                    'actualizado_por_id' => null,
                    'desactivado_por_id' => null,
                    'desactivado_at' => null,
                    'motivo_desactivacion' => null,
                ]
            );

            // -------------------------------------------------
            // ALMACÉN VIRTUAL DE TRÁNSITO
            // Funciona como intermediario para las futuras
            // transferencias entre almacenes.
            // -------------------------------------------------

            Almacen::query()->updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'codigo' => 'TRANSITO',
                ],
                [
                    'sucursal_id' => null,
                    'nombre' => 'Almacén virtual de tránsito',
                    'tipo' => Almacen::TIPO_TRANSITO,
                    'es_virtual' => true,
                    'permite_existencias' => true,
                    'activo' => true,
                    'creado_por_id' => $propietario->id,
                    'actualizado_por_id' => null,
                    'desactivado_por_id' => null,
                    'desactivado_at' => null,
                    'motivo_desactivacion' => null,
                ]
            );

            // -------------------------------------------------
            // ASIGNACIÓN DEL PROPIETARIO
            // Asocia al propietario con la sucursal principal
            // sin duplicar la relación si el seeder se repite.
            // -------------------------------------------------

            DB::table('sucursal_usuario')->updateOrInsert(
                [
                    'sucursal_id' => $sucursalPrincipal->id,
                    'user_id' => $propietario->id,
                ],
                [
                    'es_principal' => true,
                    'es_gerente' => true,
                    'activo' => true,
                    'asignado_por_id' => $propietario->id,
                    'asignado_at' => now(),
                    'finalizado_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // -------------------------------------------------
            // ADMINISTRADOR SECUNDARIO
            // Asocia el administrador delegado con la sede
            // principal sin convertirlo en propietario.
            // -------------------------------------------------

            DB::table('sucursal_usuario')->updateOrInsert(
                [
                    'sucursal_id' => $sucursalPrincipal->id,
                    'user_id' => $administradorSecundario->id,
                ],
                [
                    'es_principal' => true,
                    'es_gerente' => false,
                    'activo' => true,
                    'asignado_por_id' => $propietario->id,
                    'asignado_at' => now(),
                    'finalizado_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // -------------------------------------------------
            // SUPERVISOR COMO GERENTE
            // Designa al supervisor como gerente activo de la
            // segunda sucursal.
            // -------------------------------------------------

            DB::table('sucursal_usuario')->updateOrInsert(
                [
                    'sucursal_id' => $sucursalNorte->id,
                    'user_id' => $supervisor->id,
                ],
                [
                    'es_principal' => true,
                    'es_gerente' => true,
                    'activo' => true,
                    'asignado_por_id' => $propietario->id,
                    'asignado_at' => now(),
                    'finalizado_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // -------------------------------------------------
            // EMPLEADO DE SUCURSAL
            // Asocia al empleado como integrante operativo de
            // la sucursal Norte.
            // -------------------------------------------------

            DB::table('sucursal_usuario')->updateOrInsert(
                [
                    'sucursal_id' => $sucursalNorte->id,
                    'user_id' => $empleado->id,
                ],
                [
                    'es_principal' => true,
                    'es_gerente' => false,
                    'activo' => true,
                    'asignado_por_id' => $propietario->id,
                    'asignado_at' => now(),
                    'finalizado_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        // -----------------------------------------------------
        // CATÁLOGO DE SERVICIOS
        // Reconstruye los servicios técnicos disponibles para
        // mostrarlos en el catálogo y crear nuevas órdenes.
        // -----------------------------------------------------

        $servicios = [
            [
                'nombre' => 'Diagnóstico general',
                'descripcion' => 'Revisión inicial del equipo para identificar fallas de hardware, software, energía o conectividad.',
                'precio' => 350,
                'activo' => true,
            ],
            [
                'nombre' => 'Diagnóstico avanzado',
                'descripcion' => 'Diagnóstico profundo con pruebas de componentes, temperaturas, almacenamiento, memoria y estabilidad.',
                'precio' => 600,
                'activo' => true,
            ],
            [
                'nombre' => 'Mantenimiento preventivo',
                'descripcion' => 'Limpieza interna, revisión general, optimización y mantenimiento básico del equipo.',
                'precio' => 650,
                'activo' => true,
            ],
            [
                'nombre' => 'Limpieza interna y cambio de pasta térmica',
                'descripcion' => 'Limpieza física interna del equipo, ventiladores y disipadores, con reemplazo de pasta térmica.',
                'precio' => 550,
                'activo' => true,
            ],
            [
                'nombre' => 'Formateo e instalación de sistema operativo',
                'descripcion' => 'Instalación limpia del sistema operativo, controladores y configuración inicial del equipo.',
                'precio' => 750,
                'activo' => true,
            ],
            [
                'nombre' => 'Instalación y configuración de programas',
                'descripcion' => 'Instalación y configuración de aplicaciones autorizadas necesarias para el usuario.',
                'precio' => 350,
                'activo' => true,
            ],
            [
                'nombre' => 'Eliminación de virus y malware',
                'descripcion' => 'Análisis, eliminación de software malicioso y aplicación de medidas básicas de protección.',
                'precio' => 550,
                'activo' => true,
            ],
            [
                'nombre' => 'Respaldo de información',
                'descripcion' => 'Copia de documentos y archivos del usuario hacia un medio de almacenamiento disponible.',
                'precio' => 450,
                'activo' => true,
            ],
            [
                'nombre' => 'Recuperación de información',
                'descripcion' => 'Evaluación e intento de recuperación de archivos desde unidades con fallas lógicas.',
                'precio' => 1200,
                'activo' => true,
            ],
            [
                'nombre' => 'Instalación o cambio de componentes',
                'descripcion' => 'Instalación de memoria, almacenamiento, fuente de poder, tarjeta gráfica u otro componente compatible.',
                'precio' => 450,
                'activo' => true,
            ],
            [
                'nombre' => 'Cambio de pantalla de laptop',
                'descripcion' => 'Desmontaje e instalación de pantalla compatible. El precio no incluye la refacción.',
                'precio' => 850,
                'activo' => true,
            ],
            [
                'nombre' => 'Reparación de laptop',
                'descripcion' => 'Servicio técnico para diagnóstico y reparación de fallas generales en computadoras portátiles.',
                'precio' => 900,
                'activo' => true,
            ],
            [
                'nombre' => 'Reparación de computadora de escritorio',
                'descripcion' => 'Servicio técnico para diagnóstico y reparación de fallas generales en equipos de escritorio.',
                'precio' => 800,
                'activo' => true,
            ],
            [
                'nombre' => 'Actualización de hardware',
                'descripcion' => 'Evaluación de compatibilidad e instalación de mejoras de memoria, almacenamiento u otros componentes.',
                'precio' => 500,
                'activo' => true,
            ],
            [
                'nombre' => 'Configuración de red y conectividad',
                'descripcion' => 'Diagnóstico y configuración básica de conexión por cable, Wi-Fi, impresoras o recursos compartidos.',
                'precio' => 550,
                'activo' => true,
            ],
        ];

        // -----------------------------------------------------
        // CREACIÓN O ACTUALIZACIÓN
        // Utiliza el nombre como identificador único lógico
        // para evitar duplicados al repetir el seeder.
        // -----------------------------------------------------

        DB::transaction(
            function () use ($servicios): void {
                foreach ($servicios as $datosServicio) {
                    Servicio::query()->updateOrCreate(
                        [
                            'nombre' => $datosServicio['nombre'],
                        ],
                        [
                            'descripcion' => $datosServicio[
                                    'descripcion'
                                ],
                            'precio' => $datosServicio['precio'],
                            'activo' => $datosServicio['activo'],
                        ]
                    );
                }
            }
        );
    }
}
