<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class HorarioMedico {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function asignarHorario($datos) {
        try {
            $sql = "INSERT INTO doctor_horarios (id_doctor, id_sucursal, dia_semana, hora_inicio, hora_fin, duracion_cita, activo) 
                    VALUES (:id_doctor, :id_sucursal, :dia_semana, :hora_inicio, :hora_fin, :duracion_cita, :activo)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_doctor' => $datos['id_medico'],
                ':id_sucursal' => $datos['id_sucursal'],
                ':dia_semana' => $datos['dia_semana'],
                ':hora_inicio' => $datos['hora_inicio'],
                ':hora_fin' => $datos['hora_fin'],
                ':duracion_cita' => $datos['duracion_cita'] ?? 30,
                ':activo' => $datos['activo'] ?? 1
            ]);

            return $this->conn->lastInsertId();

        } catch (\Exception $e) {
            throw new \Exception("Error al asignar horario: " . $e->getMessage());
        }
    }

    public function actualizarHorario($idHorario, $datos) {
        try {
            $sql = "UPDATE doctor_horarios 
                    SET id_sucursal = :id_sucursal, 
                        dia_semana = :dia_semana, 
                        hora_inicio = :hora_inicio, 
                        hora_fin = :hora_fin, 
                        duracion_cita = :duracion_cita, 
                        activo = :activo 
                    WHERE id_horario = :id_horario";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_horario' => $idHorario,
                ':id_sucursal' => $datos['id_sucursal'],
                ':dia_semana' => $datos['dia_semana'],
                ':hora_inicio' => $datos['hora_inicio'],
                ':hora_fin' => $datos['hora_fin'],
                ':duracion_cita' => $datos['duracion_cita'] ?? 30,
                ':activo' => $datos['activo'] ?? 1
            ]);

            return $stmt->rowCount() > 0;

        } catch (\Exception $e) {
            throw new \Exception("Error al actualizar horario: " . $e->getMessage());
        }
    }

    public function obtenerPorMedico($idMedico) {
        try {
            $sql = "SELECT 
                        dh.*,
                        s.nombre_sucursal,
                        CASE dh.dia_semana
                            WHEN 1 THEN 'Lunes'
                            WHEN 2 THEN 'Martes'
                            WHEN 3 THEN 'Miércoles'
                            WHEN 4 THEN 'Jueves'
                            WHEN 5 THEN 'Viernes'
                            WHEN 6 THEN 'Sábado'
                            WHEN 7 THEN 'Domingo'
                        END as nombre_dia
                    FROM doctor_horarios dh
                    JOIN sucursales s ON dh.id_sucursal = s.id_sucursal
                    WHERE dh.id_doctor = :id_doctor 
                    AND dh.activo = 1
                    ORDER BY dh.dia_semana, dh.hora_inicio";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id_doctor' => $idMedico]);
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener horarios: " . $e->getMessage());
        }
    }

    public function verificarDisponibilidad($idMedico, $fechaHora) {
    try {
        // 1. Verificar que la fecha/hora no sea en el pasado
        if (strtotime($fechaHora) < time()) {
            return ['disponible' => false, 'motivo' => 'No se pueden programar citas en fechas pasadas'];
        }

        // 2. Obtener día de la semana (1=lunes, 7=domingo según tu BD)
        $diaSemana = date('N', strtotime($fechaHora));
        $hora = date('H:i:s', strtotime($fechaHora));
        $fecha = date('Y-m-d', strtotime($fechaHora));

        // 3. Verificar si el médico tiene horario disponible ese día/hora
        $sqlHorario = "SELECT COUNT(*) as tiene_horario, duracion_cita 
                      FROM doctor_horarios 
                      WHERE id_doctor = :id_doctor 
                      AND dia_semana = :dia_semana 
                      AND :hora BETWEEN hora_inicio AND hora_fin 
                      AND activo = 1
                      GROUP BY duracion_cita";
        
        $stmtHorario = $this->conn->prepare($sqlHorario);
        $stmtHorario->execute([
            ':id_doctor' => $idMedico,
            ':dia_semana' => $diaSemana,
            ':hora' => $hora
        ]);
        
        $horario = $stmtHorario->fetch();

        if (!$horario || $horario['tiene_horario'] == 0) {
            return [
                'disponible' => false, 
                'motivo' => 'El médico no tiene horario de atención en este día y hora. Consulte los horarios disponibles.',
                'codigo_error' => 'HORARIO_NO_LABORABLE'
            ];
        }

        // 4. Verificar excepciones del doctor (vacaciones, feriados, etc.)
        $sqlExcepcion = "SELECT COUNT(*) as tiene_excepcion, motivo 
                        FROM doctor_excepciones 
                        WHERE id_doctor = :id_doctor 
                        AND fecha = :fecha 
                        AND activo = 1";
        
        $stmtExcepcion = $this->conn->prepare($sqlExcepcion);
        $stmtExcepcion->execute([
            ':id_doctor' => $idMedico,
            ':fecha' => $fecha
        ]);
        
        $excepcion = $stmtExcepcion->fetch();

        if ($excepcion && $excepcion['tiene_excepcion'] > 0) {
            return [
                'disponible' => false, 
                'motivo' => 'El médico no está disponible en esta fecha: ' . $excepcion['motivo'],
                'codigo_error' => 'MEDICO_NO_DISPONIBLE'
            ];
        }

        // 5. 🔥 VALIDACIÓN PRINCIPAL: Verificar si ya hay una cita en esa fecha y hora
        $sqlCita = "SELECT 
                        COUNT(*) as tiene_cita,
                        CONCAT(up.nombres, ' ', up.apellidos) as paciente_existente,
                        c.estado,
                        c.motivo
                    FROM citas c
                    JOIN pacientes p ON c.id_paciente = p.id_paciente
                    JOIN usuarios up ON p.id_usuario = up.id_usuario
                    WHERE c.id_doctor = :id_doctor 
                    AND c.fecha_hora = :fecha_hora 
                    AND c.estado NOT IN ('Cancelada', 'No_Asistio')
                    GROUP BY up.nombres, up.apellidos, c.estado, c.motivo";
        
        $stmtCita = $this->conn->prepare($sqlCita);
        $stmtCita->execute([
            ':id_doctor' => $idMedico,
            ':fecha_hora' => $fechaHora
        ]);
        
        $citaExistente = $stmtCita->fetch();

        if ($citaExistente && $citaExistente['tiene_cita'] > 0) {
            return [
                'disponible' => false, 
                'motivo' => "❌ HORARIO OCUPADO: El médico ya tiene una cita programada el " . date('d/m/Y H:i', strtotime($fechaHora)) . " con el paciente " . $citaExistente['paciente_existente'] . " (Estado: " . $citaExistente['estado'] . ")",
                'codigo_error' => 'HORARIO_OCUPADO',
                'detalles' => [
                    'paciente_existente' => $citaExistente['paciente_existente'],
                    'estado_cita' => $citaExistente['estado'],
                    'fecha_conflicto' => $fechaHora
                ]
            ];
        }

        // 6. Todo OK - Horario disponible
        return [
            'disponible' => true, 
            'motivo' => '✅ Horario disponible para programar cita',
            'codigo_error' => null
        ];

    } catch (\Exception $e) {
        return [
            'disponible' => false,
            'motivo' => 'Error al verificar disponibilidad: ' . $e->getMessage(),
            'codigo_error' => 'ERROR_INTERNO'
        ];
    }
}

    public function obtenerHorariosDisponibles($idMedico, $fecha, $idSucursal = null) {
        try {
            $diaSemana = date('N', strtotime($fecha)); // 1=lunes, 7=domingo
            
            // Obtener horarios del médico para ese día
            $sql = "SELECT * FROM doctor_horarios 
                    WHERE id_doctor = :id_doctor 
                    AND dia_semana = :dia_semana 
                    AND activo = 1";
            
            $params = [
                ':id_doctor' => $idMedico,
                ':dia_semana' => $diaSemana
            ];

            if ($idSucursal) {
                $sql .= " AND id_sucursal = :id_sucursal";
                $params[':id_sucursal'] = $idSucursal;
            }

            $sql .= " ORDER BY hora_inicio";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $horarios = $stmt->fetchAll();

            $horariosDisponibles = [];

            foreach ($horarios as $horario) {
                $horaInicio = $horario['hora_inicio'];
                $horaFin = $horario['hora_fin'];
                $duracionCita = $horario['duracion_cita'];

                // Generar slots de tiempo
                $horaActual = strtotime($fecha . ' ' . $horaInicio);
                $horaLimite = strtotime($fecha . ' ' . $horaFin);

                while ($horaActual < $horaLimite) {
                    $fechaHoraSlot = date('Y-m-d H:i:s', $horaActual);
                    
                    // Verificar si este slot está disponible
                    $disponibilidad = $this->verificarDisponibilidad($idMedico, $fechaHoraSlot);
                    
                    if ($disponibilidad['disponible']) {
                        $horariosDisponibles[] = [
                            'fecha_hora' => $fechaHoraSlot,
                            'hora' => date('H:i', $horaActual),
                            'id_sucursal' => $horario['id_sucursal'],
                            'duracion_minutos' => $duracionCita
                        ];
                    }

                    $horaActual += ($duracionCita * 60); // Avanzar por duración de cita
                }
            }

            return $horariosDisponibles;

        } catch (\Exception $e) {
            throw new \Exception("Error al obtener horarios disponibles: " . $e->getMessage());
        }
    }
}
?>