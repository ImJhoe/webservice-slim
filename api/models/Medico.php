<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Medico {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function crear($datos) {
        try {
            $this->conn->beginTransaction();

            // Primero crear usuario - USANDO CAMPOS CORRECTOS DE TU BD
            $sqlUsuario = "INSERT INTO usuarios (nombres, apellidos, cedula, correo, password, id_rol, id_estado, sexo, nacionalidad, username) 
                          VALUES (:nombres, :apellidos, :cedula, :correo, :password, 70, 1, :sexo, :nacionalidad, :username)";
            
            // Generar username único
            $username = strtolower(str_replace(' ', '.', $datos['nombres'])) . '.' . strtolower(str_replace(' ', '.', $datos['apellidos']));
            
            $stmtUsuario = $this->conn->prepare($sqlUsuario);
            $stmtUsuario->execute([
                ':nombres' => $datos['nombres'],
                ':apellidos' => $datos['apellidos'],
                ':cedula' => (int)$datos['cedula'], // 👈 CONVERTIR A ENTERO
                ':correo' => $datos['correo'],
                ':password' => password_hash($datos['contrasena'], PASSWORD_DEFAULT), // 👈 USAR :password
                ':sexo' => $datos['sexo'] ?? 'M',
                ':nacionalidad' => $datos['nacionalidad'] ?? 'Ecuatoriana',
                ':username' => $username
            ]);

            $idUsuario = $this->conn->lastInsertId();

            // Luego crear médico - según tu tabla doctores
            $sqlMedico = "INSERT INTO doctores (id_usuario, id_especialidad, titulo_profesional) 
                         VALUES (:id_usuario, :id_especialidad, :titulo_profesional)";
            
            $stmtMedico = $this->conn->prepare($sqlMedico);
            $stmtMedico->execute([
                ':id_usuario' => $idUsuario,
                ':id_especialidad' => $datos['id_especialidad'],
                ':titulo_profesional' => $datos['titulo_profesional']
            ]);

            $idMedico = $this->conn->lastInsertId();

            $this->conn->commit();

            return [
                'id_medico' => $idMedico,
                'id_usuario' => $idUsuario,
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'cedula' => $datos['cedula'],
                'especialidad' => $datos['id_especialidad'],
                'username' => $username
            ];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw new \Exception("Error al crear médico: " . $e->getMessage());
        }
    }

    public function buscarPorCedula($cedula) {
        try {
            $sql = "SELECT d.*, u.nombres, u.apellidos, u.cedula, u.correo, e.nombre_especialidad
                    FROM doctores d
                    JOIN usuarios u ON d.id_usuario = u.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    WHERE u.cedula = :cedula AND u.id_estado = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':cedula' => (int)$cedula]); // 👈 CONVERTIR A ENTERO
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            throw new \Exception("Error al buscar médico: " . $e->getMessage());
        }
    }
}
?>