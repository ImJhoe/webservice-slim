<?php
// api/models/Medico.php - VERSIÓN CORREGIDA
namespace App\Models;

use App\Config\Database;
use PDO;

class Medico {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function obtenerTodos() {
        try {
            $sql = "SELECT 
                        d.id_doctor,
                        d.id_usuario,
                        u.nombres,
                        u.apellidos,
                        u.cedula,
                        u.correo,
                        u.username,
                        e.id_especialidad,
                        e.nombre_especialidad,
                        d.titulo_profesional,
                        CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo,
                        u.id_estado as activo
                    FROM doctores d
                    JOIN usuarios u ON d.id_usuario = u.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    WHERE u.id_estado = 1
                    ORDER BY u.nombres";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ CORREGIR: Asegurar que cedula se devuelva como STRING
            $medicosCorregidos = [];
            foreach ($medicos as $medico) {
                $medicosCorregidos[] = [
                    'id_medico' => (int)$medico['id_doctor'],
                    'id_usuario' => (int)$medico['id_usuario'],
                    'nombres' => $medico['nombres'],
                    'apellidos' => $medico['apellidos'],
                    'cedula' => (string)$medico['cedula'], // ✅ CONVERTIR A STRING
                    'correo' => $medico['correo'],
                    'username' => $medico['username'],
                    'id_especialidad' => (int)$medico['id_especialidad'],
                    'nombre_especialidad' => $medico['nombre_especialidad'],
                    'titulo_profesional' => $medico['titulo_profesional'],
                    'nombre_completo' => $medico['nombre_completo'],
                    'activo' => (int)$medico['activo']
                ];
            }
            
            return $medicosCorregidos;
        } catch (\Exception $e) {
            throw new \Exception("Error al obtener médicos: " . $e->getMessage());
        }
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
                ':cedula' => (int)$datos['cedula'], // Mantener como int en BD
                ':correo' => $datos['correo'],
                ':password' => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
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
                ':titulo_profesional' => $datos['titulo_profesional'] ?? ''
            ]);

            $idMedico = $this->conn->lastInsertId();

            $this->conn->commit();

            // ✅ DEVOLVER CEDULA COMO STRING PARA CONSISTENCIA
            return [
                'id_medico' => (int)$idMedico,
                'id_usuario' => (int)$idUsuario,
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'cedula' => (string)$datos['cedula'], // ✅ CONVERTIR A STRING
                'especialidad' => (int)$datos['id_especialidad'],
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
            $stmt->execute([':cedula' => (int)$cedula]); // Buscar como int
            
            $result = $stmt->fetch();
            
            // ✅ CONVERTIR CEDULA A STRING EN RESULTADO
            if ($result) {
                $result['cedula'] = (string)$result['cedula'];
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new \Exception("Error al buscar médico: " . $e->getMessage());
        }
    }
}
?>