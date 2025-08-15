<?php
namespace App\Controllers;

use App\Models\Usuario;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController {
    private $secretKey = "tu_clave_secreta_super_segura_2025";

    public function login(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones básicas
            if (empty($data['username']) || empty($data['password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario y contraseña son requeridos'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $userModel = new Usuario();
            $user = $userModel->login($data['username'], $data['password']);

            if (!$user) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
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
                'rol' => $user['nombre_rol']
            ];

            $token = JWT::encode($payload, $this->secretKey, 'HS256');

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
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
                    'message' => 'El username es requerido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (empty($data['nueva_password']) || empty($data['confirmar_password'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Nueva contraseña y confirmación son requeridas'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if ($data['nueva_password'] !== $data['confirmar_password']) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Las contraseñas no coinciden'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar fortaleza de contraseña
            if (strlen($data['nueva_password']) < 6) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La contraseña debe tener al menos 6 caracteres'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $userModel = new Usuario();
            $resultado = $userModel->cambiarPassword($data['username'], $data['nueva_password']);

            if (!$resultado) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Contraseña cambiada exitosamente'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>