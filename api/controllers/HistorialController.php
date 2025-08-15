<?php
namespace App\Controllers;

use App\Models\HistorialClinico;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class HistorialController {

    public function buscarPorCedula(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validación de cédula
            if (empty($data['cedula'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Cédula es requerida'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar formato de cédula ecuatoriana (10 dígitos)
            if (!v::digit()->length(10, 10)->validate($data['cedula'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Formato de cédula inválido. Debe tener 10 dígitos'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $historialModel = new HistorialClinico();
            $paciente = $historialModel->buscarPorCedula($data['cedula']);

            if (!$paciente) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Obtener consultas médicas
            $consultas = $historialModel->obtenerConsultas($paciente['id_paciente']);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Historial clínico encontrado',
                'data' => [
                    'paciente' => $paciente,
                    'consultas' => $consultas
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
}
?>