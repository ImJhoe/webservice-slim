<?php
namespace App\Controllers;

use App\Models\Paciente;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class PacienteController {

    public function crear(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones requeridas
            $errores = [];

            if (empty($data['nombres'])) {
                $errores[] = 'El nombre es requerido';
            }

            if (empty($data['apellidos'])) {
                $errores[] = 'Los apellidos son requeridos';
            }

            if (empty($data['cedula'])) {
                $errores[] = 'La cédula es requerida';
            } elseif (!v::digit()->length(10, 10)->validate($data['cedula'])) {
                $errores[] = 'La cédula debe tener exactamente 10 dígitos';
            }

            if (empty($data['correo'])) {
                $errores[] = 'El correo es requerido';
            } elseif (!v::email()->validate($data['correo'])) {
                $errores[] = 'El formato del correo es inválido';
            }

            if (empty($data['fecha_nacimiento'])) {
                $errores[] = 'La fecha de nacimiento es requerida';
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
            $pacienteModel = new Paciente();
            $pacienteExistente = $pacienteModel->buscarPorCedula($data['cedula']);
            
            if ($pacienteExistente) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Ya existe un paciente registrado con esta cédula',
                    'codigo_error' => 'CEDULA_DUPLICADA'
                ]));
                return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
            }

            $paciente = $pacienteModel->crear($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Paciente creado exitosamente',
                'data' => $paciente
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al crear paciente',
                'error' => $e->getMessage(),
                'codigo_error' => 'INTERNAL_SERVER_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function buscarPorCedula(Request $request, Response $response, array $args): Response {
        try {
            $cedula = $args['cedula'];

            if (empty($cedula)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'La cédula es requerida',
                    'codigo_error' => 'CEDULA_REQUERIDA'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $pacienteModel = new Paciente();
            $paciente = $pacienteModel->buscarPorCedula($cedula);

            if (!$paciente) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'No se encontró ningún paciente con la cédula proporcionada',
                    'codigo_error' => 'PACIENTE_NO_ENCONTRADO'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Paciente encontrado',
                'data' => $paciente
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al buscar paciente',
                'error' => $e->getMessage(),
                'codigo_error' => 'INTERNAL_SERVER_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>