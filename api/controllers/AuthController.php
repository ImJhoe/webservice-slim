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

            // Validar longitud mínima
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

            // Generar JWT
            $payload = [
                'iss' => 'clinica-medica-api',
                'aud' => 'clinica-medica-app',
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60), // 24 horas
                'user_id' => $user['id_usuario'],
                'username' => $user['username'],
                'rol' => $user['nombre_rol'],
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
                        'correo' => $user['correo']
                    ],
                    'token' => $token,
                    'expires_in' => 86400 // 24 horas en segundos
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

    public function cambiarPassword(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones básicas
            if (empty($data['username'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'El username es requerido',
                    'code' => 'MISSING_USERNAME'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (empty($data['nueva_password']) || empty($data['confirmar_password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Nueva contraseña y confirmación son requeridas',
                    'code' => 'MISSING_PASSWORDS'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if ($data['nueva_password'] !== $data['confirmar_password']) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Las contraseñas no coinciden',
                    'code' => 'PASSWORD_MISMATCH'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar fortaleza de contraseña
            if (strlen($data['nueva_password']) < 6) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La contraseña debe tener al menos 6 caracteres',
                    'code' => 'WEAK_PASSWORD'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar que tenga al menos una letra y un número
            if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)/', $data['nueva_password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La contraseña debe contener al menos una letra y un número',
                    'code' => 'WEAK_PASSWORD'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $userModel = new Usuario();
            $resultado = $userModel->cambiarPassword($data['username'], $data['nueva_password']);

            if (!$resultado) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'code' => 'USER_NOT_FOUND'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Contraseña cambiada exitosamente',
                'code' => 'PASSWORD_CHANGED'
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
}
?>