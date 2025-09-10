<?php
namespace App\Controllers;

use App\Models\Medico;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class MedicoController {

    public function crear(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones requeridas según lista de cotejo
            $errores = [];

            if (empty($data['nombres'])) {
                $errores[] = 'El nombre es requerido';
            }

            if (empty($data['cedula'])) {
                $errores[] = 'La cédula es requerida';
            } elseif (!v::digit()->length(10, 10)->validate($data['cedula'])) {
                $errores[] = 'La cédula debe tener exactamente 10 dígitos';
            }

            if (empty($data['id_especialidad'])) {
                $errores[] = 'La especialidad es requerida';
            }

            if (empty($data['apellidos'])) {
                $errores[] = 'Los apellidos son requeridos';
            }

            if (empty($data['correo'])) {
                $errores[] = 'El correo es requerido';
            } elseif (!v::email()->validate($data['correo'])) {
                $errores[] = 'El formato del correo es inválido';
            }

            if (empty($data['contrasena'])) {
                $errores[] = 'La contraseña es requerida';
            }

            if (!empty($errores)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos de validación fallidos',
                    'errores' => $errores
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Verificar si la cédula ya existe
            $medicoModel = new Medico();
            $medicoExistente = $medicoModel->buscarPorCedula($data['cedula']);
            
            if ($medicoExistente) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Ya existe un médico registrado con esta cédula',
                    'codigo_error' => 'CEDULA_DUPLICADA'
                ]));
                return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
            }

            $medico = $medicoModel->crear($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Médico creado exitosamente',
                'data' => $medico
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al crear médico',
                'error' => $e->getMessage(),
                'codigo_error' => 'INTERNAL_SERVER_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    // Agregar este método a la clase MedicoController

 // ✅ AGREGAR: Método para buscar médico por cédula
    public function buscarPorCedula(Request $request, Response $response, array $args): Response {
        try {
            $cedula = $args['cedula'];

            if (empty($cedula)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Cédula es requerida'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if (!v::digit()->length(10, 10)->validate($cedula)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Formato de cédula inválido. Debe tener 10 dígitos'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $medicoModel = new Medico();
            $medico = $medicoModel->buscarPorCedula($cedula);

            if (!$medico) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Médico no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Médico encontrado',
                'data' => $medico
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al buscar médico',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
// ✅ AGREGAR ESTE MÉTODO AL FINAL DE TU CLASE MedicoController
    public function obtenerTodos(Request $request, Response $response): Response {
        try {
            error_log("=== OBTENIENDO MÉDICOS ===");
            
            $medicoModel = new Medico();
            $medicos = $medicoModel->obtenerTodos();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Médicos obtenidos exitosamente',
                'data' => $medicos
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("❌ Error en MedicoController::obtenerTodos: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al obtener médicos',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>