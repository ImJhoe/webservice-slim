<?php
namespace App\Controllers;

use App\Models\Cita;
use App\Models\Paciente;
use App\Models\HorarioMedico;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class CitaController {

    public function crear(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones requeridas
            $errores = [];

            if (empty($data['id_paciente'])) {
                $errores[] = 'El ID del paciente es requerido';
            }

            if (empty($data['id_medico'])) {
                $errores[] = 'El ID del médico es requerido';
            }

            if (empty($data['id_sucursal'])) {
                $errores[] = 'El ID de la sucursal es requerido';
            }

            if (empty($data['fecha_hora'])) {
                $errores[] = 'La fecha y hora son requeridas';
            } elseif (!v::dateTime('Y-m-d H:i:s')->validate($data['fecha_hora'])) {
                $errores[] = 'Formato de fecha y hora inválido. Use YYYY-MM-DD HH:MM:SS';
            }

            if (empty($data['motivo'])) {
                $errores[] = 'El motivo de la cita es requerido';
            }

            if (!empty($errores)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos de validación fallidos',
                    'errores' => $errores
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Validar que el paciente existe
            $pacienteModel = new Paciente();
            $pacienteExiste = $pacienteModel->buscarPorCedula($data['cedula_paciente'] ?? '');
            
            if (!$pacienteExiste) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Paciente no encontrado con la cédula proporcionada',
                    'codigo_error' => 'PACIENTE_NO_ENCONTRADO'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $citaModel = new Cita();
            $cita = $citaModel->crear($data);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'data' => $cita
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al crear cita',
                'error' => $e->getMessage(),
                'codigo_error' => 'CITA_CREATION_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function obtenerPorId(Request $request, Response $response, array $args): Response {
        try {
            $idCita = $args['id_cita'];

            if (empty($idCita)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'El número de cita es requerido'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $citaModel = new Cita();
            $cita = $citaModel->obtenerPorId($idCita);

            if (!$cita) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Cita no encontrada con el número proporcionado',
                    'codigo_error' => 'CITA_NO_ENCONTRADA'
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Cita encontrada',
                'data' => $cita
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno del servidor al consultar cita',
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function cambiarEstado(Request $request, Response $response, array $args): Response {
        try {
            $idCita = $args['id_cita'];
            $data = json_decode($request->getBody()->getContents(), true);

            // Validaciones
            $errores = [];

            if (empty($idCita)) {
                $errores[] = 'El ID de la cita es requerido';
            }

            if (empty($data['estado'])) {
                $errores[] = 'El nuevo estado es requerido';
            }

            $estadosValidos = ['Pendiente', 'Confirmada', 'En_Curso', 'Completada', 'Cancelada', 'No_Asistio'];
            if (!empty($data['estado']) && !in_array($data['estado'], $estadosValidos)) {
                $errores[] = 'Estado inválido. Estados válidos: ' . implode(', ', $estadosValidos);
            }

            if (!empty($errores)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Datos de validación fallidos',
                    'errores' => $errores
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $citaModel = new Cita();
            $citaActualizada = $citaModel->cambiarEstado($idCita, $data['estado'], $data['notas'] ?? '');

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Estado de cita actualizado exitosamente',
                'data' => $citaActualizada
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al cambiar estado de cita',
                'error' => $e->getMessage(),
                'codigo_error' => 'ESTADO_CHANGE_ERROR'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function consultarPorEspecialidadYMedico(Request $request, Response $response): Response {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
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
                $data['id_medico'] ?? null
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

            if (empty($data['fecha_inicio']) || empty($data['fecha_fin'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Fecha de inicio y fecha fin son requeridas'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

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