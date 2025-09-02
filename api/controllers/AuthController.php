<?php
namespace App\Controllers;

use App\Models\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private $secretKey = "tu_clave_secreta_super_segura_2025";

    public function login(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones básicas
            if (empty($data['username']) || empty($data['password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario y contraseña son requeridos',
                    'code' => 'MISSING_CREDENTIALS'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (strlen($data['username']) < 3) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'El nombre de usuario debe tener al menos 3 caracteres',
                    'code' => 'INVALID_USERNAME'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $userModel = new Usuario();
            $user = $userModel->login($data['username'], $data['password']);

            if (!$user) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Credenciales incorrectas o usuario inactivo',
                    'code' => 'INVALID_CREDENTIALS'
                ]));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // ===== NUEVO: Obtener permisos del rol =====
            $permissions = $this->getRolePermissions($user['id_rol']);

            // Generar JWT
            $payload = [
                'iss' => 'clinica-medica-api',
                'aud' => 'clinica-medica-app',
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60), // 24 horas
                'user_id' => $user['id_usuario'],
                'username' => $user['username'],
                'rol' => $user['nombre_rol'],
                'rol_id' => $user['id_rol'],  // Agregamos el ID del rol
                'nombres' => $user['nombres'],
                'apellidos' => $user['apellidos']
            ];

            $token = JWT::encode($payload, $this->secretKey, 'HS256');

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'user' => [
                        'id' => $user['id_usuario'],
                        'username' => $user['username'],
                        'nombres' => $user['nombres'],
                        'apellidos' => $user['apellidos'],
                        'rol' => $user['nombre_rol'],
                        'rol_id' => $user['id_rol'],
                        'correo' => $user['correo']
                    ],
                    'permissions' => $permissions, // ✅ NUEVO: Incluir permisos
                    'token' => $token,
                    'expires_in' => 86400
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
                'code' => 'INTERNAL_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    // ===== NUEVO MÉTODO: Obtener permisos por rol =====
    private function getRolePermissions($roleId) {
        $rolePermissions = [
            1 => [ // Administrador
                'role' => 'Administrador',
                'role_id' => 1,
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
                'role_id' => 72,
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
                'role_id' => 70,
                'permissions' => [
                    'registro_medico' => false,
                    'consulta_medicos' => true,        // Punto 2 (solo su info)
                    'gestion_horarios' => true,        // Punto 2 (solo sus horarios)
                    'crear_citas' => false,
                    'ver_mis_citas' => true,           // Solo sus citas
                    'actualizar_horarios' => true,     // Puede actualizar sus horarios
                    'todas_vistas' => false
                ]
            ],
            71 => [ // Paciente
                'role' => 'Paciente',
                'role_id' => 71,
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

        return $rolePermissions[$roleId] ?? [
            'role' => 'Sin_Rol',
            'role_id' => 0,
            'permissions' => []
        ];
    }

    public function cambiarPassword(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            if (empty($data['username']) || empty($data['new_password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario y nueva contraseña son requeridos'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (strlen($data['new_password']) < 6) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La nueva contraseña debe tener al menos 6 caracteres'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $userModel = new Usuario();
            $result = $userModel->cambiarPassword($data['username'], $data['new_password']);

            if ($result) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Contraseña actualizada exitosamente'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado o error al actualizar'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>