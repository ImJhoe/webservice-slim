<?php
namespace App\Controllers;

use App\Config\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConsultaController {

    public function obtenerEspecialidades(Request $request, Response $response): Response {
        try {
            $conexion = Database::getConnection();
            $stmt = $conexion->query("SELECT * FROM especialidades ORDER BY nombre_especialidad");
            $especialidades = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $especialidades
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener especialidades: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function obtenerMedicos(Request $request, Response $response): Response {
        try {
            $conexion = Database::getConnection();
            $sql = "SELECT 
                        d.id_doctor,
                        CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo,
                        e.nombre_especialidad,
                        d.titulo_profesional
                    FROM doctores d
                    JOIN usuarios u ON d.id_usuario = u.id_usuario
                    JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                    WHERE u.id_estado = 1
                    ORDER BY u.nombres, u.apellidos";
            
            $stmt = $conexion->query($sql);
            $medicos = $stmt->fetchAll();

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $medicos
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al obtener médicos: ' . $e->getMessage()
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>