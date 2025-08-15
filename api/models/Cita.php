<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Cita {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
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

        // Si es un médico específico logueado, solo mostrar sus citas
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
}
?>