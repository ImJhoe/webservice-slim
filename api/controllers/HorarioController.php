<?php
namespace App\Controllers;

use App\Models\HorarioMedico;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HorarioController {

    public function asignar(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones
            $errores = [];

            if (empty($data['id_medico'])) {
                $errores[] = 'El ID del médico es requerido';
            }

            if (empty($data['id_sucursal'])) {
                $errores[] = 'El ID de la sucursal es requerido';
            }

            if (empty($data['dia_semana']) && $data['dia_semana'] !== 0) {
                $errores[] = 'El día de la semana es requerido (1-7: 1=Lunes, 7=Domingo)';
            }

            if ($data['dia_semana'] < 1 || $data['dia_semana'] > 7) {
                $errores[] = 'El día de la semana debe estar entre 1 y 7 (1=Lunes, 7=Domingo)';
            }

            if (empty($data['hora_inicio'])) {
                $errores[] = 'La hora de inicio es requerida (formato HH:MM:SS)';
            }

            if (empty($data['hora_fin'])) {
                $errores[] = 'La hora de fin es requerida (formato HH:MM:SS)';
            }

            if (!empty($errores)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos de validación fallidos',
                    'errores' => $errores
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $horarioModel = new HorarioMedico();
            $idHorario = $horarioModel->asignarHorario($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horario asignado exitosamente',
                'data' => [
                    'id_horario' => $idHorario,
                    'id_medico' => $data['id_medico'],
                    'id_sucursal' => $data['id_sucursal'],
                    'dia_semana' => $data['dia_semana'],
                    'hora_inicio' => $data['hora_inicio'],
                    'hora_fin' => $data['hora_fin'],
                    'duracion_cita' => $data['duracion_cita'] ?? 30
                ]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al asignar horario',
                'error' => $e->getMessage(),
                'codigo_error' => 'HORARIO_ASSIGNMENT_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function actualizar(Request $request, Response $response, array $args): Response {
        try {
            $idHorario = $args['id_horario'];
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones similares al método asignar
            $errores = [];

            if (empty($idHorario)) {
                $errores[] = 'El ID del horario es requerido';
            }

            // ... (mismas validaciones que en asignar)

            if (!empty($errores)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos de validación fallidos',
                    'errores' => $errores
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $horarioModel = new HorarioMedico();
            $actualizado = $horarioModel->actualizarHorario($idHorario, $data);

            if (!$actualizado) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Horario no encontrado o no se pudo actualizar'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horario actualizado exitosamente'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al actualizar horario',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function consultarPorMedico(Request $request, Response $response, array $args): Response {
        try {
            $idMedico = $args['id_medico'];

            if (empty($idMedico)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'El ID del médico es requerido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $horarioModel = new HorarioMedico();
            $horarios = $horarioModel->obtenerPorMedico($idMedico);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horarios obtenidos correctamente',
                'data' => $horarios,
                'total' => count($horarios)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al consultar horarios',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function obtenerDisponibles(Request $request, Response $response, array $args): Response {
        try {
            $idMedico = $args['id_medico'];
            $queryParams = $request->getQueryParams();
            $fecha = $queryParams['fecha'] ?? date('Y-m-d');
            $idSucursal = $queryParams['id_sucursal'] ?? null;

            if (empty($idMedico)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'El ID del médico es requerido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $horarioModel = new HorarioMedico();
            $horariosDisponibles = $horarioModel->obtenerHorariosDisponibles($idMedico, $fecha, $idSucursal);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horarios disponibles obtenidos correctamente',
                'data' => $horariosDisponibles,
                'fecha_consultada' => $fecha,
                'total_slots' => count($horariosDisponibles)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al obtener horarios disponibles',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>