<?php

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers para CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar autoloader de Composer
require __DIR__ . '/../vendor/autoload.php';

// Importar clases necesarias
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Controllers\AuthController;
use App\Controllers\HistorialController;
use App\Middleware\AuthMiddleware;

// Configurar base path para Slim
$app = AppFactory::create();

// Detectar si estamos en un subdirectorio
$basePath = '';
if (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    if (strpos($requestUri, '/webservice-slim') === 0) {
        $basePath = '/webservice-slim';
    }
}

// Establecer base path
if ($basePath) {
    $app->setBasePath($basePath);
}

// Middleware para parsing JSON
$app->addBodyParsingMiddleware();

// Middleware de manejo de errores
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Middleware CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Manejar requests OPTIONS para CORS
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// ============ RUTAS PÚBLICAS (sin autenticación) ============

// Ruta de bienvenida
$app->get('/', function (Request $request, Response $response) use ($basePath) {
    $baseUrl = 'http://localhost:8081' . $basePath;

    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'API de Citas Médicas - Sistema funcionando correctamente',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'base_url' => $baseUrl,
        'endpoints' => [
            'POST /auth/login' => 'Iniciar sesión',
            'POST /api/medicos' => 'Crear médico',
            'GET /api/medicos' => 'Obtener médicos',
            'POST /api/pacientes' => 'Crear paciente',
            'GET /api/pacientes/{cedula}' => 'Buscar paciente por cédula',
            'POST /api/citas' => 'Crear cita',
            'POST /api/citas/consultar' => 'Consultar citas',
            'GET /api/especialidades' => 'Obtener especialidades',
            'GET /api/sucursales' => 'Obtener sucursales',
            'POST /api/horarios' => 'Asignar horarios',
            'GET /api/horarios/medico/{id_medico}' => 'Consultar horarios',
            'GET /api/horarios/medico/{id_medico}/disponibles' => 'Horarios disponibles'
        ]
    ], JSON_PRETTY_PRINT));

    return $response->withHeader('Content-Type', 'application/json');
});

// Test de conexión a base de datos
$app->get('/test-db', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
        $result = $stmt->fetch();

        $stmt2 = $db->query("SELECT COUNT(*) as total_especialidades FROM especialidades");
        $result2 = $stmt2->fetch();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Conexión a base de datos exitosa',
            'data' => [
                'total_usuarios' => $result['total'],
                'total_especialidades' => $result2['total_especialidades'],
                'database' => 'menudinamico',
                'connection_test' => 'OK'
            ]
        ], JSON_PRETTY_PRINT));
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error de conexión a base de datos',
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT));
    }
    return $response->withHeader('Content-Type', 'application/json');
});

// ============ RUTAS DE AUTENTICACIÓN ============

$app->post('/auth/login', [AuthController::class, 'login']);
$app->post('/auth/cambiar-password', [AuthController::class, 'cambiarPassword']);

// ============ ENDPOINT ESPECIALIDADES CORREGIDO ============
$app->get('/api/especialidades', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();
        
        // ✅ SIN FILTRO 'activo' porque no existe esa columna
        $stmt = $db->prepare("SELECT * FROM especialidades ORDER BY nombre_especialidad");
        $stmt->execute();
        $especialidades = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Especialidades obtenidas exitosamente',
            'data' => $especialidades
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
// ============ ENDPOINT PARA OBTENER SUCURSALES ============
$app->get('/api/sucursales', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();

        $stmt = $db->query("SELECT * FROM sucursales WHERE estado = 1 ORDER BY nombre_sucursal");
        $sucursales = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Sucursales obtenidas exitosamente',
            'data' => $sucursales
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA REGISTRAR MÉDICOS ============
$app->post('/api/medicos', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);

        // Validaciones básicas
        if (empty($data['cedula'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'La cédula es requerida'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['nombre'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El nombre es requerido'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['apellido'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El apellido es requerido'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['email'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El email es requerido'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['password'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'La contraseña es requerida'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['especialidad'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'La especialidad es requerida'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Conectar a la base de datos
        $db = App\Config\Database::getConnection();

        // Verificar si la cédula ya existe
        $stmt = $db->prepare("SELECT cedula FROM usuarios WHERE cedula = :cedula");
        $stmt->bindParam(':cedula', $data['cedula']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un usuario con esa cédula'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT correo FROM usuarios WHERE correo = :email");
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un usuario con ese email'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Obtener ID de la especialidad
        $stmt = $db->prepare("SELECT id_especialidad FROM especialidades WHERE nombre_especialidad = :especialidad");
        $stmt->bindParam(':especialidad', $data['especialidad']);
        $stmt->execute();
        $especialidadResult = $stmt->fetch();

        if (!$especialidadResult) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Especialidad no encontrada'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Hash de la contraseña
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Generar username único
        $username = strtolower(str_replace(' ', '.', $data['nombre'] . '.' . $data['apellido']));

        // Insertar usuario
        $stmt = $db->prepare("
            INSERT INTO usuarios (cedula, username, nombres, apellidos, correo, password, id_rol, id_estado) 
            VALUES (:cedula, :username, :nombres, :apellidos, :correo, :password, 70, 1)
        ");

        $stmt->bindParam(':cedula', $data['cedula']);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':nombres', $data['nombre']);
        $stmt->bindParam(':apellidos', $data['apellido']);
        $stmt->bindParam(':correo', $data['email']);
        $stmt->bindParam(':password', $hashedPassword);

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar usuario');
        }

        $userId = $db->lastInsertId();

        // Insertar doctor
        $stmt = $db->prepare("
            INSERT INTO doctores (id_usuario, id_especialidad) 
            VALUES (:id_usuario, :id_especialidad)
        ");

        $stmt->bindParam(':id_usuario', $userId);
        $stmt->bindParam(':id_especialidad', $especialidadResult['id_especialidad']);

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar doctor');
        }

        $doctorId = $db->lastInsertId();

        // Respuesta exitosa
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Médico registrado exitosamente',
            'data' => [
                'id' => (int) $userId,
                'id_doctor' => (int) $doctorId,
                'cedula' => $data['cedula'],
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'email' => $data['email'],
                'username' => $username,
                'especialidad' => $data['especialidad']
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA OBTENER MÉDICOS ============
$app->get('/api/medicos', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();

        $stmt = $db->query("
            SELECT 
                u.id_usuario as id,
                u.cedula,
                u.nombres as nombre,
                u.apellidos as apellido,
                u.correo as email,
                u.username,
                e.nombre_especialidad as especialidad,
                d.id_doctor,
                CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo,
                u.id_estado as activo
            FROM usuarios u 
            JOIN doctores d ON u.id_usuario = d.id_usuario
            JOIN especialidades e ON d.id_especialidad = e.id_especialidad
            WHERE u.id_estado = 1
            ORDER BY u.nombres
        ");

        $medicos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Médicos obtenidos exitosamente',
            'data' => $medicos
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA BUSCAR PACIENTE POR CÉDULA ============
$app->get('/api/pacientes/{cedula}', function (Request $request, Response $response, $args) {
    try {
        $cedula = $args['cedula'];
        $db = App\Config\Database::getConnection();

        $stmt = $db->prepare("
            SELECT 
                p.*,
                u.nombres,
                u.apellidos,
                u.correo,
                CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo
            FROM pacientes p
            JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE u.cedula = :cedula AND u.id_estado = 1
        ");

        $stmt->bindParam(':cedula', $cedula);
        $stmt->execute();
        $paciente = $stmt->fetch();

        if ($paciente) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Paciente encontrado',
                'data' => $paciente
            ]));
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA REGISTRAR PACIENTE ============
$app->post('/api/pacientes', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);

        // Validaciones básicas
        if (empty($data['cedula'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'La cédula es requerida'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['nombres'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Los nombres son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['apellidos'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Los apellidos son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = App\Config\Database::getConnection();

        // Verificar si la cédula ya existe
        $stmt = $db->prepare("SELECT cedula FROM usuarios WHERE cedula = :cedula");
        $stmt->bindParam(':cedula', $data['cedula']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un usuario con esa cédula'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Hash de la contraseña (usar cédula como contraseña inicial)
        $hashedPassword = password_hash($data['cedula'], PASSWORD_DEFAULT);

        // Generar username
        $username = $data['cedula'];

        // Insertar usuario
        $stmt = $db->prepare("
            INSERT INTO usuarios (cedula, username, nombres, apellidos, sexo, nacionalidad, correo, password, id_rol, id_estado) 
            VALUES (:cedula, :username, :nombres, :apellidos, :sexo, :nacionalidad, :correo, :password, 71, 1)
        ");

        $stmt->bindParam(':cedula', $data['cedula']);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':nombres', $data['nombres']);
        $stmt->bindParam(':apellidos', $data['apellidos']);
        // ✅ CORRECTO:
        $sexo = $data['sexo'] ?? 'No especificado';
        $nacionalidad = $data['nacionalidad'] ?? 'Ecuatoriana';
        $correo = $data['correo'] ?? '';
        $telefono = $data['telefono'] ?? '';
        $fechaNacimiento = $data['fecha_nacimiento'] ?? null;
        $tipoSangre = $data['tipo_sangre'] ?? '';
        $alergias = $data['alergias'] ?? '';
        $antecedentesMedicos = $data['antecedentes_medicos'] ?? '';
        $contactoEmergencia = $data['contacto_emergencia'] ?? '';
        $telefonoEmergencia = $data['telefono_emergencia'] ?? '';
        $numeroSeguro = $data['numero_seguro'] ?? '';

        $stmt->bindParam(':sexo', $sexo);
        $stmt->bindParam(':nacionalidad', $nacionalidad);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':fecha_nacimiento', $fechaNacimiento);
        $stmt->bindParam(':tipo_sangre', $tipoSangre);
        $stmt->bindParam(':alergias', $alergias);
        $stmt->bindParam(':antecedentes_medicos', $antecedentesMedicos);
        $stmt->bindParam(':contacto_emergencia', $contactoEmergencia);
        $stmt->bindParam(':telefono_emergencia', $telefonoEmergencia);
        $stmt->bindParam(':numero_seguro', $numeroSeguro);

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar usuario');
        }

        $userId = $db->lastInsertId();

        // Insertar paciente
        $stmt = $db->prepare("
            INSERT INTO pacientes (
                id_usuario, telefono, fecha_nacimiento, tipo_sangre, 
                alergias, antecedentes_medicos, contacto_emergencia, 
                telefono_emergencia, numero_seguro
            ) VALUES (
                :id_usuario, :telefono, :fecha_nacimiento, :tipo_sangre,
                :alergias, :antecedentes_medicos, :contacto_emergencia,
                :telefono_emergencia, :numero_seguro
            )
        ");

        // POR:
        $stmt->bindValue(':id_usuario', $userId);
        $stmt->bindValue(':telefono', $data['telefono'] ?? '');
        $stmt->bindValue(':fecha_nacimiento', $data['fecha_nacimiento'] ?? null);
        $stmt->bindValue(':tipo_sangre', $data['tipo_sangre'] ?? '');
        $stmt->bindValue(':alergias', $data['alergias'] ?? '');
        $stmt->bindValue(':antecedentes_medicos', $data['antecedentes_medicos'] ?? '');
        $stmt->bindValue(':contacto_emergencia', $data['contacto_emergencia'] ?? '');
        $stmt->bindValue(':telefono_emergencia', $data['telefono_emergencia'] ?? '');
        $stmt->bindValue(':numero_seguro', $data['numero_seguro'] ?? '');

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar paciente');
        }

        $pacienteId = $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Paciente registrado exitosamente',
            'data' => [
                'id_usuario' => (int) $userId,
                'id_paciente' => (int) $pacienteId,
                'cedula' => $data['cedula'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'nombre_completo' => $data['nombres'] . ' ' . $data['apellidos']
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA ASIGNAR HORARIO INDIVIDUAL ============
$app->post('/api/horarios', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        // Validaciones
        $errores = [];
        
        if (empty($data['id_medico'])) {
            $errores[] = 'El ID del médico es requerido';
        }
        
        if (empty($data['id_sucursal'])) {
            $errores[] = 'El ID de la sucursal es requerido';
        }
        
        if (empty($data['dia_semana']) || $data['dia_semana'] < 1 || $data['dia_semana'] > 7) {
            $errores[] = 'El día de la semana debe estar entre 1 (Lunes) y 7 (Domingo)';
        }
        
        if (empty($data['hora_inicio'])) {
            $errores[] = 'La hora de inicio es requerida';
        }
        
        if (empty($data['hora_fin'])) {
            $errores[] = 'La hora de fin es requerida';
        }

        if (!empty($errores)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Datos de validación fallidos',
                'errores' => $errores
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar que no existe conflicto de horarios
        $sqlConflicto = "SELECT COUNT(*) as conflictos FROM doctor_horarios 
                        WHERE id_doctor = :id_doctor 
                        AND id_sucursal = :id_sucursal 
                        AND dia_semana = :dia_semana 
                        AND (
                            (:hora_inicio >= hora_inicio AND :hora_inicio < hora_fin) OR
                            (:hora_fin > hora_inicio AND :hora_fin <= hora_fin) OR
                            (:hora_inicio <= hora_inicio AND :hora_fin >= hora_fin)
                        )
                        AND activo = 1";

        $stmtConflicto = $db->prepare($sqlConflicto);
        $stmtConflicto->execute([
            ':id_doctor' => $data['id_medico'],
            ':id_sucursal' => $data['id_sucursal'],
            ':dia_semana' => $data['dia_semana'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin' => $data['hora_fin']
        ]);

        $conflicto = $stmtConflicto->fetch();
        
        if ($conflicto['conflictos'] > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un horario que se superpone con el horario que intenta asignar',
                'codigo_error' => 'HORARIO_CONFLICTO'
            ]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        // Insertar el horario
        $sql = "INSERT INTO doctor_horarios (id_doctor, id_sucursal, dia_semana, hora_inicio, hora_fin, duracion_cita, activo) 
                VALUES (:id_doctor, :id_sucursal, :dia_semana, :hora_inicio, :hora_fin, :duracion_cita, 1)";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':id_doctor' => $data['id_medico'],
            ':id_sucursal' => $data['id_sucursal'],
            ':dia_semana' => $data['dia_semana'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin' => $data['hora_fin'],
            ':duracion_cita' => $data['duracion_cita'] ?? 30
        ]);

        if ($result) {
            $idHorario = $db->lastInsertId();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horario asignado exitosamente',
                'data' => [
                    'id_horario' => (int) $idHorario,
                    'id_doctor' => (int) $data['id_medico'],
                    'id_sucursal' => (int) $data['id_sucursal'],
                    'dia_semana' => (int) $data['dia_semana'],
                    'hora_inicio' => $data['hora_inicio'],
                    'hora_fin' => $data['hora_fin'],
                    'duracion_cita' => $data['duracion_cita'] ?? 30
                ]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al insertar el horario en la base de datos'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
            'codigo_error' => 'HORARIO_ASSIGNMENT_ERROR'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA CONSULTAR HORARIOS POR MÉDICO ============
$app->get('/api/horarios/medico/{id_medico}', function (Request $request, Response $response, $args) {
    try {
        $idMedico = $args['id_medico'];
        $db = App\Config\Database::getConnection();

        $stmt = $db->prepare("
            SELECT 
                hm.*,
                s.nombre_sucursal,
                s.direccion
            FROM horarios_medicos hm
            JOIN sucursales s ON hm.id_sucursal = s.id_sucursal
            WHERE hm.id_doctor = :id_medico
            ORDER BY 
                FIELD(hm.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'),
                hm.hora_inicio
        ");

        $stmt->bindParam(':id_medico', $idMedico);
        $stmt->execute();
        $horarios = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Horarios obtenidos exitosamente',
            'data' => $horarios
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA HORARIOS DISPONIBLES ============
$app->get('/api/horarios/medico/{id_medico}/disponibles', function (Request $request, Response $response, $args) {
    try {
        $idMedico = $args['id_medico'];
        $fecha = $request->getQueryParams()['fecha'] ?? date('Y-m-d');
        $idSucursal = $request->getQueryParams()['sucursal'] ?? 1;

        $db = App\Config\Database::getConnection();

        // Obtener día de la semana en español
        $diaSemana = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ][date('l', strtotime($fecha))];

        $stmt = $db->prepare("
            SELECT hora_inicio, hora_fin
            FROM horarios_medicos 
            WHERE id_doctor = :id_medico 
            AND id_sucursal = :id_sucursal 
            AND dia_semana = :dia_semana
        ");

        $stmt->bindParam(':id_medico', $idMedico);
        $stmt->bindParam(':id_sucursal', $idSucursal);
        $stmt->bindParam(':dia_semana', $diaSemana);
        $stmt->execute();
        $horario = $stmt->fetch();

        if (!$horario) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El médico no tiene horarios asignados para este día'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Generar horarios cada 30 minutos
        $horariosDisponibles = [];
        $inicio = strtotime($horario['hora_inicio']);
        $fin = strtotime($horario['hora_fin']);

        while ($inicio < $fin) {
            $horariosDisponibles[] = date('H:i', $inicio);
            $inicio += 1800; // 30 minutos
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Horarios disponibles obtenidos exitosamente',
            'data' => $horariosDisponibles
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA CREAR CITAS ============
$app->post('/api/citas', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);

        // Validaciones básicas
        $camposRequeridos = ['id_paciente', 'id_doctor', 'id_sucursal', 'fecha_hora', 'motivo'];
        foreach ($camposRequeridos as $campo) {
            if (empty($data[$campo])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "El campo $campo es requerido"
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $db = App\Config\Database::getConnection();
        // Verificar si ya existe una cita en esa fecha/hora para el médico
        $stmt = $db->prepare("
           SELECT id_cita 
           FROM citas 
           WHERE id_doctor = :id_doctor 
           AND DATE(fecha_hora) = DATE(:fecha_hora) 
           AND TIME(fecha_hora) = TIME(:fecha_hora)
           AND estado_cita NOT IN ('Cancelada', 'Completada')
       ");

        $stmt->bindParam(':id_doctor', $data['id_doctor']);
        $stmt->bindParam(':fecha_hora', $data['fecha_hora']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe una cita programada para esa fecha y hora'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Insertar la cita
        $stmt = $db->prepare("
           INSERT INTO citas (
               id_paciente, id_doctor, id_sucursal, id_tipo_cita, 
               fecha_hora, motivo, tipo_cita, estado_cita, notas, enlace_virtual
           ) VALUES (
               :id_paciente, :id_doctor, :id_sucursal, :id_tipo_cita,
               :fecha_hora, :motivo, :tipo_cita, 'Confirmada', :notas, :enlace_virtual
           )
       ");

        $stmt->bindParam(':id_paciente', $data['id_paciente']);
        $stmt->bindParam(':id_doctor', $data['id_doctor']);
        $stmt->bindParam(':id_sucursal', $data['id_sucursal']);
        $idTipoCita = $data['id_tipo_cita'] ?? 1;
        $tipoCita = $data['tipo_cita'] ?? 'presencial';
        $notas = $data['notas'] ?? '';
        $enlaceVirtual = $data['enlace_virtual'] ?? '';

        $stmt->bindParam(':id_tipo_cita', $idTipoCita);
        $stmt->bindParam(':tipo_cita', $tipoCita);
        $stmt->bindParam(':notas', $notas);
        $stmt->bindParam(':enlace_virtual', $enlaceVirtual);

        if (!$stmt->execute()) {
            throw new Exception('Error al crear la cita');
        }

        $citaId = $db->lastInsertId();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Cita creada exitosamente',
            'data' => [
                'id_cita' => (int) $citaId,
                'fecha_hora' => $data['fecha_hora'],
                'estado' => 'Confirmada'
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA CONSULTAR CITAS ============
$app->post('/api/citas/consultar', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        $sql = "
           SELECT 
               c.*,
               CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
               CONCAT(um.nombres, ' ', um.apellidos) as nombre_medico,
               e.nombre_especialidad,
               s.nombre_sucursal,
               tc.nombre_tipo
           FROM citas c
           JOIN pacientes p ON c.id_paciente = p.id_paciente
           JOIN usuarios up ON p.id_usuario = up.id_usuario
           JOIN doctores d ON c.id_doctor = d.id_doctor
           JOIN usuarios um ON d.id_usuario = um.id_usuario
           JOIN especialidades e ON d.id_especialidad = e.id_especialidad
           JOIN sucursales s ON c.id_sucursal = s.id_sucursal
           LEFT JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
           WHERE 1=1
       ";

        $params = [];

        if (!empty($data['id_medico'])) {
            $sql .= " AND c.id_doctor = :id_medico";
            $params[':id_medico'] = $data['id_medico'];
        }

        if (!empty($data['fecha_inicio']) && !empty($data['fecha_fin'])) {
            $sql .= " AND DATE(c.fecha_hora) BETWEEN :fecha_inicio AND :fecha_fin";
            $params[':fecha_inicio'] = $data['fecha_inicio'];
            $params[':fecha_fin'] = $data['fecha_fin'];
        }

        $sql .= " ORDER BY c.fecha_hora DESC";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $citas = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Citas obtenidas exitosamente',
            'data' => $citas
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ RUTAS PROTEGIDAS CON AUTENTICACIÓN ============

$app->group('/historial', function ($group) {
    $group->post('/buscar-cedula', [HistorialController::class, 'buscarPorCedula']);
})->add(new AuthMiddleware());

// ============ MANEJO DE RUTAS NO ENCONTRADAS ============
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) use ($basePath) {
    $method = $request->getMethod();
    $uri = $request->getUri()->getPath();
    $baseUrl = 'http://localhost:8081' . $basePath;

    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado',
        'error_code' => 'ENDPOINT_NOT_FOUND',
        'details' => [
            'method' => $method,
            'path' => $uri,
            'base_url' => $baseUrl
        ],
        'available_endpoints' => [
            'GET /' => 'Información del API',
            'GET /test-db' => 'Probar conexión BD',
            'POST /auth/login' => 'Iniciar sesión',
            'GET /api/especialidades' => 'Listar especialidades',
            'GET /api/sucursales' => 'Listar sucursales',
            'POST /api/medicos' => 'Crear médico',
            'GET /api/medicos' => 'Listar médicos',
            'GET /api/pacientes/{cedula}' => 'Buscar paciente',
            'POST /api/pacientes' => 'Crear paciente',
            'POST /api/horarios' => 'Asignar horarios',
            'GET /api/horarios/medico/{id}' => 'Horarios médico',
            'GET /api/horarios/medico/{id}/disponibles' => 'Horarios disponibles',
            'POST /api/citas' => 'Crear cita',
            'POST /api/citas/consultar' => 'Consultar citas'
        ]
    ], JSON_PRETTY_PRINT));

    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});

// Iniciar la aplicación
try {
    $app->run();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar la aplicación',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>