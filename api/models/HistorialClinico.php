<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class HistorialClinico {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function buscarPorCedula($cedula) {
        try {
            $sql = "SELECT 
                        p.*, 
                        u.nombres, 
                        u.apellidos, 
                        u.correo,
                        hc.fecha_creacion as fecha_historial,
                        hc.ultima_actualizacion
                    FROM pacientes p
                    JOIN usuarios u ON p.id_usuario = u.id_usuario
                    LEFT JOIN historiales_clinicos hc ON p.id_paciente = hc.id_paciente
                    WHERE u.cedula = :cedula";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':cedula', $cedula);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            throw new \Exception("Error al buscar historial: " . $e->getMessage());
        }
    }

    public function obtenerConsultas($idPaciente) {
        try {
            $sql = "SELECT 
                        cm.*,
                        c.fecha_hora as fecha_cita,
                        c.motivo as motivo_cita,
                        CONCAT(u.nombres, ' ', u.apellidos) as nombre_doctor,
                        e.nombre_especialidad,
                        s.nombre_sucursal
                    FROM consultas_medicas cm
                    JOIN citas c ON cm.id_cita = c.id_cita
                    JOIN doctores d ON c.id_doctor = d.id_doctor
                    JOIN usuarios u ON d.id_usuario = u.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                    WHERE c.id_paciente = :id_paciente
                    ORDER BY cm.fecha_hora DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id_paciente', $idPaciente);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener consultas: " . $e->getMessage());
        }
    }
}
?>