<?php
namespace App\Controllers;

use App\Models\Cita;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class CitaController {

    public function consultarPorEspecialidadYMedico(Request $request, Response $response): Response {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $user = $request->getAttribute('user');

        // Verificar si es médico y obtener su ID
        $idMedicoLogueado = null;
        if ($user->rol === 'Medico') {
            // Obtener ID del médico basado en el usuario logueado
            $conexion = \App\Config\Database::getConnection();
            $stmt = $conexion->prepare("SELECT id_doctor FROM doctores WHERE id_usuario = ?");
            $stmt->execute([$user->user_id]);
            $doctor = $stmt->fetch();
            if ($doctor) {
                $idMedicoLogueado = $doctor['id_doctor'];
            }
        }

        if (empty($data['id_especialidad']) && empty($data['id_medico'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Debe proporcionar al menos id_especialidad o id_medico'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $citaModel = new Cita();
        $citas = $citaModel->obtenerPorEspecialidadYMedico(
            $data['id_especialidad'] ?? null,
            $data['id_medico'] ?? null,
            $idMedicoLogueado // Filtrar por médico si es necesario
        );

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Citas encontradas',
            'data' => $citas,
            'total' => count($citas)
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

    public function consultarPorRangoFechas(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones básicas
            if (empty($data['fecha_inicio']) || empty($data['fecha_fin'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Fecha de inicio y fecha fin son requeridas'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar formato de fechas
            if (!v::date('Y-m-d')->validate($data['fecha_inicio']) || 
                !v::date('Y-m-d')->validate($data['fecha_fin'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Formato de fecha inválido. Use YYYY-MM-DD'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $citaModel = new Cita();
            $citas = $citaModel->obtenerPorRangoFechas(
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['id_medico'] ?? null
            );

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Citas encontradas en el rango de fechas',
                'data' => $citas,
                'total' => count($citas),
                'filtros' => [
                    'fecha_inicio' => $data['fecha_inicio'],
                    'fecha_fin' => $data['fecha_fin'],
                    'id_medico' => $data['id_medico'] ?? 'Todos'
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