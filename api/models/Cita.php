<?php
namespace App\Models;

use App\Config\Database;
use App\Models\HorarioMedico;
use PDO;

class Cita {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function crear($datos) {
        try {
            $this->conn->beginTransaction();

            // Validar disponibilidad de horario
            $horarioModel = new HorarioMedico();
            $disponibilidad = $horarioModel->verificarDisponibilidad($datos['id_medico'], $datos['fecha_hora']);
            
            if (!$disponibilidad['disponible']) {
                throw new \Exception($disponibilidad['motivo']);
            }

            // Crear la cita
            $sql = "INSERT INTO citas (id_paciente, id_doctor, id_sucursal, id_tipo_cita, fecha_hora, motivo, tipo_cita, estado, notas) 
                    VALUES (:id_paciente, :id_doctor, :id_sucursal, :id_tipo_cita, :fecha_hora, :motivo, :tipo_cita, 'Pendiente', :notas)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_paciente' => $datos['id_paciente'],
                ':id_doctor' => $datos['id_medico'],
                ':id_sucursal' => $datos['id_sucursal'],
                ':id_tipo_cita' => $datos['id_tipo_cita'] ?? 1,
                ':fecha_hora' => $datos['fecha_hora'],
                ':motivo' => $datos['motivo'],
                ':tipo_cita' => $datos['tipo_cita'] ?? 'presencial',
                ':notas' => $datos['notas'] ?? null
            ]);

            $idCita = $this->conn->lastInsertId();

            // Obtener datos completos de la cita creada
            $citaCompleta = $this->obtenerPorId($idCita);

            $this->conn->commit();

            // Enviar correo de confirmación
            $this->enviarCorreoCita($citaCompleta, 'creacion');

            return $citaCompleta;

        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw new \Exception("Error al crear cita: " . $e->getMessage());
        }
    }

    public function obtenerPorId($idCita) {
        try {
            $sql = "SELECT 
                        c.*,
                        CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
                        up.cedula as cedula_paciente,
                        up.correo as correo_paciente,
                        CONCAT(ud.nombres, ' ', ud.apellidos) as nombre_doctor,
                        ud.correo as correo_doctor,
                        e.nombre_especialidad,
                        s.nombre_sucursal,
                        s.direccion as direccion_sucursal,
                        tc.nombre_tipo
                    FROM citas c
                    JOIN pacientes p ON c.id_paciente = p.id_paciente
                    JOIN usuarios up ON p.id_usuario = up.id_usuario
                    JOIN doctores d ON c.id_doctor = d.id_doctor
                    JOIN usuarios ud ON d.id_usuario = ud.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                    JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
                    WHERE c.id_cita = :id_cita";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id_cita' => $idCita]);
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener cita: " . $e->getMessage());
        }
    }

    public function cambiarEstado($idCita, $nuevoEstado, $notas = '') {
        try {
            $this->conn->beginTransaction();

            // Obtener datos actuales de la cita
            $citaActual = $this->obtenerPorId($idCita);
            if (!$citaActual) {
                throw new \Exception("Cita no encontrada");
            }

            // Preparar las notas con el cambio de estado
            $notasActualizadas = $citaActual['notas'];
            if (!empty($notas)) {
                $notasActualizadas .= "\n[" . strtoupper($nuevoEstado) . " " . date('Y-m-d H:i:s') . "] " . $notas;
            }

            // Actualizar el estado
            $sql = "UPDATE citas SET estado = :estado, notas = :notas WHERE id_cita = :id_cita";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':estado' => $nuevoEstado,
                ':notas' => $notasActualizadas,
                ':id_cita' => $idCita
            ]);

            // Obtener datos actualizados
            $citaActualizada = $this->obtenerPorId($idCita);

            $this->conn->commit();

            // Enviar correo de notificación
            $this->enviarCorreoCita($citaActualizada, 'cambio_estado');

            return $citaActualizada;

        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw new \Exception("Error al cambiar estado de cita: " . $e->getMessage());
        }
    }

    public function obtenerPorEspecialidadYMedico($idEspecialidad = null, $idMedico = null, $idMedicoLogueado = null) {
        try {
            $sql = "SELECT 
                        c.*,
                        CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
                        up.cedula as cedula_paciente,
                        CONCAT(ud.nombres, ' ', ud.apellidos) as nombre_doctor,
                        e.nombre_especialidad,
                        s.nombre_sucursal,
                        tc.nombre_tipo
                    FROM citas c
                    JOIN pacientes p ON c.id_paciente = p.id_paciente
                    JOIN usuarios up ON p.id_usuario = up.id_usuario
                    JOIN doctores d ON c.id_doctor = d.id_doctor
                    JOIN usuarios ud ON d.id_usuario = ud.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                    JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
                    WHERE 1=1";

            $params = [];

            if ($idMedicoLogueado) {
                $sql .= " AND c.id_doctor = :id_medico_logueado";
                $params[':id_medico_logueado'] = $idMedicoLogueado;
            }

            if ($idEspecialidad) {
                $sql .= " AND d.id_especialidad = :id_especialidad";
                $params[':id_especialidad'] = $idEspecialidad;
            }

            if ($idMedico) {
                $sql .= " AND c.id_doctor = :id_medico";
                $params[':id_medico'] = $idMedico;
            }

            $sql .= " ORDER BY c.fecha_hora DESC";

            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener citas: " . $e->getMessage());
        }
    }

    public function obtenerPorRangoFechas($fechaInicio, $fechaFin, $idMedico = null) {
        try {
            $sql = "SELECT 
                        c.*,
                        CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
                        up.cedula as cedula_paciente,
                        CONCAT(ud.nombres, ' ', ud.apellidos) as nombre_doctor,
                        e.nombre_especialidad,
                        s.nombre_sucursal,
                        tc.nombre_tipo
                    FROM citas c
                    JOIN pacientes p ON c.id_paciente = p.id_paciente
                    JOIN usuarios up ON p.id_usuario = up.id_usuario
                    JOIN doctores d ON c.id_doctor = d.id_doctor
                    JOIN usuarios ud ON d.id_usuario = ud.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                    JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
                    WHERE DATE(c.fecha_hora) BETWEEN :fecha_inicio AND :fecha_fin";

            $params = [
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin
            ];

            if ($idMedico) {
                $sql .= " AND c.id_doctor = :id_medico";
                $params[':id_medico'] = $idMedico;
            }

            $sql .= " ORDER BY c.fecha_hora ASC";

            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener citas por fechas: " . $e->getMessage());
        }
    }

    private function enviarCorreoCita($cita, $tipoEvento) {
        try {
            // Configurar correo (puedes usar PHPMailer aquí)
            $asunto = '';
            $mensaje = '';

            switch($tipoEvento) {
                case 'creacion':
                    $asunto = 'Confirmación de Cita Médica - #' . $cita['id_cita'];
                    $mensaje = $this->generarMensajeCreacion($cita);
                    break;
                case 'cambio_estado':
                    $asunto = 'Actualización de Cita Médica - #' . $cita['id_cita'];
                    $mensaje = $this->generarMensajeCambioEstado($cita);
                    break;
            }

            // Enviar al paciente
            $this->enviarCorreo($cita['correo_paciente'], $asunto, $mensaje);
            
            // Enviar al médico
            $this->enviarCorreo($cita['correo_doctor'], $asunto, $mensaje);

            return true;
        } catch (\Exception $e) {
            // Log del error pero no fallar la operación principal
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }

    private function generarMensajeCreacion($cita) {
        return "
        Estimado/a {$cita['nombre_paciente']},

        Su cita médica ha sido programada exitosamente:

        Número de Cita: {$cita['id_cita']}
        Médico: Dr/a. {$cita['nombre_doctor']}
        Especialidad: {$cita['nombre_especialidad']}
        Fecha y Hora: {$cita['fecha_hora']}
        Sucursal: {$cita['nombre_sucursal']}
        Dirección: {$cita['direccion_sucursal']}
        Motivo: {$cita['motivo']}
        Estado: {$cita['estado']}

        Por favor, llegue 15 minutos antes de su cita.

        Saludos cordiales,
        Sistema de Citas Médicas
        ";
    }

    private function generarMensajeCambioEstado($cita) {
        return "
        Estimado/a {$cita['nombre_paciente']},

        El estado de su cita médica ha sido actualizado:

        Número de Cita: {$cita['id_cita']}
        Médico: Dr/a. {$cita['nombre_doctor']}
        Fecha y Hora: {$cita['fecha_hora']}
        Nuevo Estado: {$cita['estado']}

        Para más información, contacte a la clínica.

        Saludos cordiales,
        Sistema de Citas Médicas
        ";
    }

    private function enviarCorreo($destinatario, $asunto, $mensaje) {
        // Implementación básica con mail() - puedes mejorar con PHPMailer
        $headers = "From: sistema@clinica.com\r\n";
        $headers .= "Reply-To: sistema@clinica.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        return mail($destinatario, $asunto, $mensaje, $headers);
    }
}
?>