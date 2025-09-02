<?php
namespace App\Controllers;

use App\Config\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class RoleController {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Obtener menús y submenús de un rol específico
     */
    public function getMenusByRole(Request $request, Response $response, $args): Response {
        try {
            $roleId = $args['roleId'];

            $sql = "SELECT 
                        m.id_menu,
                        m.nombre_menu,
                        s.id_submenu,
                        s.nombre_submenu,
                        s.url_submenu,
                        p.puede_crear,
                        p.puede_editar,
                        p.puede_eliminar
                    FROM roles r
                    JOIN roles_submenus rs ON r.id_rol = rs.id_rol
                    JOIN submenus s ON rs.id_submenu = s.id_submenu
                    JOIN menus m ON s.id_menu = m.id_menu
                    LEFT JOIN permisos_roles_submenus p ON rs.id_roles_submenus = p.id_roles_submenus
                    WHERE r.id_rol = :role_id
                    ORDER BY m.nombre_menu, s.nombre_submenu";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Organizar por menús
            $menus = [];
            foreach ($results as $row) {
                $menuId = $row['id_menu'];
                if (!isset($menus[$menuId])) {
                    $menus[$menuId] = [
                        'id_menu' => $menuId,
                        'nombre_menu' => $row['nombre_menu'],
                        'submenus' => []
                    ];
                }

                $menus[$menuId]['submenus'][] = [
                    'id_submenu' => $row['id_submenu'],
                    'nombre_submenu' => $row['nombre_submenu'],
                    'url_submenu' => $row['url_submenu'],
                    'permisos' => [
                        'puede_crear' => (bool)$row['puede_crear'],
                        'puede_editar' => (bool)$row['puede_editar'],
                        'puede_eliminar' => (bool)$row['puede_eliminar']
                    ]
                ];
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Menús obtenidos correctamente',
                'data' => array_values($menus)
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener menús del rol',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Mapear roles a funcionalidades específicas de la app móvil
     */
    public function getRolePermissions(Request $request, Response $response, $args): Response {
        try {
            $roleId = $args['roleId'];

            // Mapeo específico para la app móvil según la lista de cotejo
            $rolePermissions = [
                1 => [ // Administrador
                    'role' => 'Administrador',
                    'permissions' => [
                        'registro_medico' => true,         // Punto 1
                        'consulta_medicos' => true,        // Punto 2
                        'gestion_horarios' => true,        // Punto 1 y 2
                        'crear_citas' => true,             // Acceso completo
                        'ver_todas_citas' => true,         // Acceso completo
                        'gestionar_pacientes' => true,     // Acceso completo
                        'todas_vistas' => true             // Acceso completo
                    ]
                ],
                72 => [ // Recepcionista
                    'role' => 'Recepcionista',
                    'permissions' => [
                        'registro_medico' => false,
                        'consulta_medicos' => false,
                        'gestion_horarios' => false,
                        'crear_citas' => true,             // Punto 3
                        'buscar_pacientes' => true,        // Punto 4
                        'registrar_pacientes' => true,     // Punto 5
                        'ver_horarios_medicos' => true,    // Punto 7
                        'flujo_completo_citas' => true,    // Puntos 3-7
                        'todas_vistas' => false
                    ]
                ],
                70 => [ // Médico
                    'role' => 'Medico',
                    'permissions' => [
                        'registro_medico' => false,
                        'consulta_medicos' => true,        // Punto 2 (solo su info)
                        'gestion_horarios' => true,        // Punto 2 (solo sus horarios)
                        'crear_citas' => false,
                        'ver_mis_citas' => true,           // Solo sus citas
                        'todas_vistas' => false
                    ]
                ],
                71 => [ // Paciente
                    'role' => 'Paciente',
                    'permissions' => [
                        'registro_medico' => false,
                        'consulta_medicos' => false,
                        'gestion_horarios' => false,
                        'crear_citas' => false,
                        'ver_mis_citas' => true,           // Solo sus citas
                        'todas_vistas' => false
                    ]
                ]
            ];

            if (!isset($rolePermissions[$roleId])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Permisos obtenidos correctamente',
                'data' => $rolePermissions[$roleId]
            ]));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener permisos del rol',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>