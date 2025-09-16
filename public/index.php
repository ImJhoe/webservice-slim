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
use App\Controllers\MedicoController;

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

// ============ RUTAS DE MÉDICOS (USAR CONTROLADOR) ============
$app->post('/api/medicos', [MedicoController::class, 'crear']);
$app->get('/api/medicos', [MedicoController::class, 'obtenerTodos']);

// ============ ENDPOINT PARA CREAR PACIENTES ============
// ============ ENDPOINT PARA CREAR PACIENTES ============
$app->post('/api/pacientes', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        
        // Log para debug
        error_log("=== CREANDO PACIENTE ===");
        error_log("Datos recibidos: " . json_encode($data, JSON_PRETTY_PRINT));
        
        $db = App\Config\Database::getConnection();
        $db->beginTransaction();

        // Validaciones básicas
        $errores = [];
        if (empty($data['nombres'])) $errores[] = 'Los nombres son requeridos';
        if (empty($data['apellidos'])) $errores[] = 'Los apellidos son requeridos';
        if (empty($data['cedula'])) $errores[] = 'La cédula es requerida';
        if (empty($data['correo'])) $errores[] = 'El correo es requerido';

        // Validar formato de cédula
        if (!empty($data['cedula']) && strlen($data['cedula']) != 10) {
            $errores[] = 'La cédula debe tener exactamente 10 dígitos';
        }

        if (!empty($errores)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Errores de validación',
                'errores' => $errores
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si ya existe la cédula
        $stmt = $db->prepare("SELECT COUNT(*) as existe FROM usuarios WHERE cedula = :cedula");
        $stmt->bindParam(':cedula', $data['cedula']);
        $stmt->execute();
        $existe = $stmt->fetch();

        if ($existe['existe'] > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un usuario con esta cédula'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 1. Crear usuario (SIN telefono - va en tabla pacientes)
        $sqlUsuario = "INSERT INTO usuarios (
            nombres, apellidos, cedula, correo, password, 
            id_rol, id_estado, sexo, nacionalidad, username
        ) VALUES (
            :nombres, :apellidos, :cedula, :correo, :password,
            71, 1, :sexo, :nacionalidad, :username
        )";

        // Generar username único
        $username = strtolower(str_replace(' ', '.', $data['nombres'])) . '.' . 
                   strtolower(str_replace(' ', '.', $data['apellidos'])) . '.paciente';

        $stmtUsuario = $db->prepare($sqlUsuario);
        $stmtUsuario->execute([
            ':nombres' => $data['nombres'],
            ':apellidos' => $data['apellidos'],
            ':cedula' => $data['cedula'], // Mantener como string
            ':correo' => $data['correo'],
            ':password' => password_hash($data['contrasena'] ?? '123456', PASSWORD_DEFAULT),
            ':sexo' => $data['sexo'] ?? 'M',
            ':nacionalidad' => $data['nacionalidad'] ?? 'Ecuatoriana',
            ':username' => $username
        ]);

        $userId = $db->lastInsertId();

        // 2. Crear paciente
        $sqlPaciente = "INSERT INTO pacientes (
            id_usuario, telefono, fecha_nacimiento, tipo_sangre, 
            alergias, antecedentes_medicos, contacto_emergencia, 
            telefono_emergencia, numero_seguro
        ) VALUES (
            :id_usuario, :telefono, :fecha_nacimiento, :tipo_sangre,
            :alergias, :antecedentes_medicos, :contacto_emergencia,
            :telefono_emergencia, :numero_seguro
        )";

        $stmtPaciente = $db->prepare($sqlPaciente);
        $stmtPaciente->execute([
            ':id_usuario' => $userId,
            ':telefono' => $data['telefono'] ?? '',
            ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ':tipo_sangre' => $data['tipo_sangre'] ?? '',
            ':alergias' => $data['alergias'] ?? '',
            ':antecedentes_medicos' => $data['antecedentes_medicos'] ?? '',
            ':contacto_emergencia' => $data['contacto_emergencia'] ?? '',
            ':telefono_emergencia' => $data['telefono_emergencia'] ?? '',
            ':numero_seguro' => $data['numero_seguro'] ?? ''
        ]);

        $pacienteId = $db->lastInsertId();

        // 3. Crear historial clínico
        $sqlHistorial = "INSERT INTO historiales_clinicos (id_paciente, fecha_creacion, ultima_actualizacion) 
                        VALUES (:id_paciente, NOW(), NOW())";
        $stmtHistorial = $db->prepare($sqlHistorial);
        $stmtHistorial->execute([':id_paciente' => $pacienteId]);

        $db->commit();

        error_log("✅ Paciente creado exitosamente - ID: $pacienteId");

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Paciente registrado exitosamente',
            'data' => [
                'id_usuario' => (int) $userId,
                'id_paciente' => (int) $pacienteId,
                'cedula' => $data['cedula'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'nombre_completo' => $data['nombres'] . ' ' . $data['apellidos'],
                'username' => $username
            ]
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("❌ Error creando paciente: " . $e->getMessage());
        
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

        // ✅ LOGGING EXTENDIDO
        error_log("=== ASIGNANDO HORARIO ===");
        error_log("Datos recibidos: " . json_encode($data, JSON_PRETTY_PRINT));
        error_log("Tipo de data['id_medico']: " . gettype($data['id_medico'] ?? 'NO_EXISTE'));
        error_log("Valor de data['id_medico']: " . ($data['id_medico'] ?? 'NO_EXISTE'));

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
            error_log("❌ Errores de validación: " . json_encode($errores));
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Datos de validación fallidos',
                'errores' => $errores
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ✅ SIMPLIFICAR: Insertar directamente sin verificar conflictos primero
        error_log("✅ Validaciones pasadas, insertando horario...");
        
        $sql = "INSERT INTO doctor_horarios (id_doctor, id_sucursal, dia_semana, hora_inicio, hora_fin, duracion_cita, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        error_log("SQL a ejecutar: " . $sql);

        $stmt = $db->prepare($sql);
        
        // ✅ USAR PARÁMETROS POSICIONALES EN LUGAR DE NOMBRADOS
        $params = [
            (int)$data['id_medico'],
            (int)$data['id_sucursal'],
            (int)$data['dia_semana'],
            $data['hora_inicio'] . ':00',
            $data['hora_fin'] . ':00',
            (int)($data['duracion_cita'] ?? 30),
            1
        ];

        error_log("Parámetros a insertar: " . json_encode($params));

        $result = $stmt->execute($params);

        if ($result) {
            $idHorario = $db->lastInsertId();
            error_log("✅ Horario creado exitosamente con ID: " . $idHorario);
            
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
                    'duracion_cita' => (int)($data['duracion_cita'] ?? 30)
                ]
            ]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } else {
            error_log("❌ Error al ejecutar la consulta SQL");
            $errorInfo = $stmt->errorInfo();
            error_log("Error SQL Info: " . json_encode($errorInfo));
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al insertar el horario: ' . $errorInfo[2],
                'sql_error' => $errorInfo
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

    } catch (Exception $e) {
        error_log("❌ Exception en horarios: " . $e->getMessage());
        error_log("Exception file: " . $e->getFile() . " line: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
            'codigo_error' => 'HORARIO_ASSIGNMENT_ERROR',
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
// ============ ENDPOINT PARA CONSULTAR HORARIOS POR MÉDICO ============
$app->get('/api/horarios/medico/{id_medico}', function (Request $request, Response $response, $args) {
    try {
        $idMedico = $args['id_medico'];
        $db = App\Config\Database::getConnection();

        // 🔥 CORREGIDO: Usar doctor_horarios (no horarios_medicos)
        $stmt = $db->prepare("
            SELECT 
                hm.*,
                s.nombre_sucursal,
                s.direccion,
                CASE hm.dia_semana
                    WHEN 1 THEN 'Lunes'
                    WHEN 2 THEN 'Martes'
                    WHEN 3 THEN 'Miércoles'
                    WHEN 4 THEN 'Jueves'
                    WHEN 5 THEN 'Viernes'
                    WHEN 6 THEN 'Sábado'
                    WHEN 7 THEN 'Domingo'
                END as nombre_dia
            FROM doctor_horarios hm
            JOIN sucursales s ON hm.id_sucursal = s.id_sucursal
            WHERE hm.id_doctor = :id_medico 
            AND hm.activo = 1
            ORDER BY hm.dia_semana, hm.hora_inicio
        ");

        $stmt->bindParam(':id_medico', $idMedico);
        $stmt->execute();
        $horarios = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Horarios obtenidos exitosamente',
            'data' => $horarios,
            'total' => count($horarios)
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

// ============ ENDPOINT PARA HORARIOS DISPONIBLES (CORREGIDO) ============
$app->get('/api/horarios/medico/{id_medico}/disponibles', function (Request $request, Response $response, $args) {
    try {
        $idMedico = $args['id_medico'];
        $fecha = $request->getQueryParams()['fecha'] ?? date('Y-m-d');
        $idSucursal = $request->getQueryParams()['id_sucursal'] ?? null;

        error_log("=== BUSCANDO HORARIOS DISPONIBLES ===");
        error_log("ID Médico: " . $idMedico);
        error_log("Fecha: " . $fecha);
        error_log("ID Sucursal: " . ($idSucursal ?? 'no especificada'));

        $db = App\Config\Database::getConnection();

        // Obtener día de la semana (1=lunes, 7=domingo)
        $diaSemana = date('N', strtotime($fecha));
        error_log("Día de la semana: " . $diaSemana);

        // Buscar horarios del médico para ese día
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

        error_log("SQL: " . $sql);
        error_log("Parámetros: " . json_encode($params));

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $horarios = $stmt->fetchAll();

        error_log("Horarios encontrados: " . count($horarios));

        if (empty($horarios)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'El médico no tiene horarios asignados para este día',
                'codigo_error' => 'SIN_HORARIOS',
                'data' => [],
                'debug_info' => [
                    'medico_id' => $idMedico,
                    'fecha' => $fecha,
                    'dia_semana' => $diaSemana,
                    'sucursal_id' => $idSucursal
                ]
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $horariosDisponibles = [];

        foreach ($horarios as $horario) {
            $horaInicio = $horario['hora_inicio'];
            $horaFin = $horario['hora_fin'];
            $duracionCita = $horario['duracion_cita'] ?? 30;

            error_log("Procesando horario: {$horaInicio} - {$horaFin}");

            // Generar slots de tiempo
            $inicio = strtotime($fecha . ' ' . $horaInicio);
            $fin = strtotime($fecha . ' ' . $horaFin);

            while ($inicio < $fin) {
                $horarioSlot = [
                    'hora' => date('H:i', $inicio),
                    'fecha_hora' => $fecha . ' ' . date('H:i:s', $inicio),
                    'id_sucursal' => (int)$horario['id_sucursal'], // ✅ INCLUIR ID_SUCURSAL
                    'disponible' => true
                ];
                
                $horariosDisponibles[] = $horarioSlot;
                $inicio += ($duracionCita * 60); // Convertir minutos a segundos
            }
        }

        error_log("Total slots generados: " . count($horariosDisponibles));

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Horarios disponibles obtenidos exitosamente',
            'data' => $horariosDisponibles,
            'total_slots' => count($horariosDisponibles),
            'fecha' => $fecha,
            'medico_id' => $idMedico
        ]));

        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        error_log("❌ Error en horarios disponibles: " . $e->getMessage());
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
            'codigo_error' => 'ERROR_INTERNO'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
// Eliminar horario
$app->delete('/api/horarios/{id_horario}', function (Request $request, Response $response, $args) {
    try {
        $idHorario = $args['id_horario'];
        $db = App\Config\Database::getConnection();

        // Soft delete: marcar como inactivo en lugar de eliminar
        $stmt = $db->prepare("
            UPDATE doctor_horarios 
            SET activo = 0 
            WHERE id_horario = :id_horario
        ");

        $result = $stmt->execute([':id_horario' => $idHorario]);

        if ($result && $stmt->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Horario eliminado exitosamente'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Horario no encontrado'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA CREAR CITAS (COMPLETAMENTE CORREGIDO) ============
$app->post('/api/citas', function (Request $request, Response $response) {
    try {
        
        $data = json_decode($request->getBody()->getContents(), true);
        
        // Log para debug
        error_log("=== CREANDO CITA ===");
        error_log("Datos recibidos: " . json_encode($data, JSON_PRETTY_PRINT));

        // Validaciones básicas
        $camposRequeridos = ['id_paciente', 'id_doctor', 'id_sucursal', 'fecha_hora', 'motivo'];
        foreach ($camposRequeridos as $campo) {
            if (empty($data[$campo]) && $data[$campo] !== 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "El campo $campo es requerido",
                    'campo_faltante' => $campo
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        }

        $db = App\Config\Database::getConnection();
        
        // ✅ PRIMERA CORRECCIÓN: Verificar si ya existe una cita en esa fecha/hora para el médico
        $stmtVerificar = $db->prepare("
           SELECT id_cita 
           FROM citas 
           WHERE id_doctor = ? 
           AND DATE(fecha_hora) = DATE(?) 
           AND TIME(fecha_hora) = TIME(?)
           AND estado NOT IN ('Cancelada', 'Completada')
       ");

        $stmtVerificar->execute([$data['id_doctor'], $data['fecha_hora'], $data['fecha_hora']]);

        if ($stmtVerificar->rowCount() > 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe una cita programada para esa fecha y hora'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ✅ SEGUNDA CORRECCIÓN: SQL simplificado con parámetros posicionales
        $sqlInsertar = "
           INSERT INTO citas (
               id_paciente, id_doctor, id_sucursal, id_tipo_cita, 
               fecha_hora, motivo, tipo_cita, estado, notas, enlace_virtual, sala_virtual
           ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        // ✅ TERCERA CORRECCIÓN: Preparar valores con valores por defecto seguros
        $valores = [
            (int)$data['id_paciente'],
            (int)$data['id_doctor'], 
            (int)$data['id_sucursal'],
            (int)($data['id_tipo_cita'] ?? 1),
            $data['fecha_hora'],
            $data['motivo'],
            $data['tipo_cita'] ?? 'presencial',
            $data['estado'] ?? 'Pendiente',
            $data['notas'] ?? '',
            $data['enlace_virtual'] ?? null,
            $data['sala_virtual'] ?? null
        ];

        // Debug logs antes de ejecutar
        error_log("SQL: " . $sqlInsertar);
        error_log("Valores: " . json_encode($valores));
        error_log("Conteo parámetros SQL: " . substr_count($sqlInsertar, '?'));
        error_log("Conteo valores: " . count($valores));

        $stmtInsertar = $db->prepare($sqlInsertar);
        
        if ($stmtInsertar->execute($valores)) {
            
            $idCita = $db->lastInsertId();
            
            // Obtener datos completos de la cita creada
            $stmtDetalles = $db->prepare("
                SELECT 
                    c.*,
                    CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
                    up.cedula as cedula_paciente,
                    CONCAT(um.nombres, ' ', um.apellidos) as nombre_doctor,
                    e.nombre_especialidad,
                    s.nombre_sucursal
                FROM citas c
                JOIN pacientes p ON c.id_paciente = p.id_paciente
                JOIN usuarios up ON p.id_usuario = up.id_usuario
                JOIN doctores d ON c.id_doctor = d.id_doctor
                JOIN usuarios um ON d.id_usuario = um.id_usuario
                JOIN especialidades e ON d.id_especialidad = e.id_especialidad
                JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_cita = ?
            ");
            
            $stmtDetalles->execute([$idCita]);
            $citaCompleta = $stmtDetalles->fetch();

            error_log("✅ Cita creada exitosamente con ID: " . $idCita);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'data' => $citaCompleta
            ]));

            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } else {
            // Manejo de error si execute() falla
            $errorInfo = $stmtInsertar->errorInfo();
            error_log("❌ Error al ejecutar INSERT de cita: " . json_encode($errorInfo));
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error al crear la cita: ' . $errorInfo[2],
                'sql_error' => $errorInfo,
                'debug_info' => [
                    'sql_params_count' => substr_count($sqlInsertar, '?'),
                    'values_count' => count($valores),
                    'values' => $valores
                ]
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

    } catch (Exception $e) {
        error_log("❌ Error al crear cita: " . $e->getMessage());
        error_log("Error file: " . $e->getFile() . " line: " . $e->getLine());
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
            'codigo_error' => 'ERROR_INTERNO',
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINT PARA BUSCAR PACIENTE POR CÉDULA ============
$app->get('/api/pacientes/{cedula}', function (Request $request, Response $response, array $args) {
    try {
        $cedula = $args['cedula'];
        
        // Log para debug
        error_log("=== BUSCANDO PACIENTE ===");
        error_log("Cédula recibida: " . $cedula);
        
        $db = App\Config\Database::getConnection();

        // Buscar paciente con JOIN entre usuarios y pacientes
        $sql = "SELECT 
                    p.id_paciente,
                    p.id_usuario,
                    p.telefono,
                    p.fecha_nacimiento,
                    p.tipo_sangre,
                    p.alergias,
                    p.antecedentes_medicos,
                    p.contacto_emergencia,
                    p.telefono_emergencia,
                    p.numero_seguro,
                    u.nombres,
                    u.apellidos,
                    u.cedula,
                    u.correo,
                    u.sexo,
                    u.nacionalidad,
                    CONCAT(u.nombres, ' ', u.apellidos) as nombre_completo
                FROM pacientes p
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                WHERE u.cedula = :cedula 
                AND u.id_estado = 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':cedula' => $cedula]); // Buscar como string
        $paciente = $stmt->fetch();

        if ($paciente) {
            error_log("✅ Paciente encontrado: " . $paciente['nombre_completo']);
            
            // Convertir fecha de nacimiento a formato legible si existe
            if ($paciente['fecha_nacimiento']) {
                $fechaNac = new DateTime($paciente['fecha_nacimiento']);
                $hoy = new DateTime();
                $edad = $hoy->diff($fechaNac)->y;
                $paciente['edad'] = $edad;
                $paciente['fecha_nacimiento_formateada'] = $fechaNac->format('d/m/Y');
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Paciente encontrado',
                'data' => [
                    'id_paciente' => (int)$paciente['id_paciente'],
                    'id_usuario' => (int)$paciente['id_usuario'],
                    'cedula' => (string)$paciente['cedula'],
                    'nombres' => $paciente['nombres'],
                    'apellidos' => $paciente['apellidos'],
                    'nombre_completo' => $paciente['nombre_completo'],
                    'correo' => $paciente['correo'],
                    'telefono' => $paciente['telefono'],
                    'sexo' => $paciente['sexo'],
                    'nacionalidad' => $paciente['nacionalidad'],
                    'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                    'fecha_nacimiento_formateada' => $paciente['fecha_nacimiento_formateada'] ?? '',
                    'edad' => $paciente['edad'] ?? null,
                    'tipo_sangre' => $paciente['tipo_sangre'],
                    'alergias' => $paciente['alergias'],
                    'antecedentes_medicos' => $paciente['antecedentes_medicos'],
                    'contacto_emergencia' => $paciente['contacto_emergencia'],
                    'telefono_emergencia' => $paciente['telefono_emergencia'],
                    'numero_seguro' => $paciente['numero_seguro']
                ]
            ]));

            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        } else {
            error_log("❌ Paciente no encontrado con cédula: " . $cedula);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Paciente no encontrado',
                'codigo_error' => 'PACIENTE_NO_ENCONTRADO',
                'data' => null
            ]));

            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

    } catch (Exception $e) {
        error_log("❌ Error buscando paciente: " . $e->getMessage());
        
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage(),
            'codigo_error' => 'ERROR_INTERNO'
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
// ============ 1. TRIAJE - ENFERMERO (Lista Cotejo #1) ============

// Crear registro de triaje
$app->post('/api/triaje', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        // Validaciones básicas
        if (empty($data['id_cita']) || empty($data['id_enfermero'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de cita e ID de enfermero son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si ya existe triaje para esta cita
        $stmtCheck = $db->prepare("SELECT id_triage FROM triage WHERE id_cita = :id_cita");
        $stmtCheck->bindParam(':id_cita', $data['id_cita']);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe un triaje registrado para esta cita'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Calcular IMC si hay peso y talla
        $imc = null;
        if (!empty($data['peso']) && !empty($data['talla'])) {
            $tallaEnMetros = $data['talla'] / 100;
            $imc = round($data['peso'] / ($tallaEnMetros * $tallaEnMetros), 2);
        }

        $sql = "INSERT INTO triage (
            id_cita, id_enfermero, nivel_urgencia, estado_triaje,
            temperatura, presion_arterial, frecuencia_cardiaca, 
            frecuencia_respiratoria, saturacion_oxigeno, 
            peso, talla, imc, observaciones
        ) VALUES (
            :id_cita, :id_enfermero, :nivel_urgencia, :estado_triaje,
            :temperatura, :presion_arterial, :frecuencia_cardiaca,
            :frecuencia_respiratoria, :saturacion_oxigeno,
            :peso, :talla, :imc, :observaciones
        )";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_cita', $data['id_cita']);
        $stmt->bindParam(':id_enfermero', $data['id_enfermero']);
        $stmt->bindParam(':nivel_urgencia', $data['nivel_urgencia'] ?? 1);
        $stmt->bindParam(':estado_triaje', $data['estado_triaje'] ?? 'Completado');
        $stmt->bindParam(':temperatura', $data['temperatura']);
        $stmt->bindParam(':presion_arterial', $data['presion_arterial']);
        $stmt->bindParam(':frecuencia_cardiaca', $data['frecuencia_cardiaca']);
        $stmt->bindParam(':frecuencia_respiratoria', $data['frecuencia_respiratoria']);
        $stmt->bindParam(':saturacion_oxigeno', $data['saturacion_oxigeno']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':talla', $data['talla']);
        $stmt->bindParam(':imc', $imc);
        $stmt->bindParam(':observaciones', $data['observaciones']);

        $stmt->execute();
        $triageId = $db->lastInsertId();

        // Obtener el triaje creado
        $stmtGet = $db->prepare("SELECT * FROM triage WHERE id_triage = :id");
        $stmtGet->bindParam(':id', $triageId);
        $stmtGet->execute();
        $triaje = $stmtGet->fetch();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Triaje registrado exitosamente',
            'data' => $triaje
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al registrar triaje: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Obtener triaje por cita
$app->get('/api/triaje/cita/{id_cita}', function (Request $request, Response $response, $args) {
    try {
        $id_cita = $args['id_cita'];
        $db = App\Config\Database::getConnection();

        $sql = "SELECT t.*, 
                CONCAT(u.nombres, ' ', u.apellidos) as nombre_enfermero
                FROM triage t
                LEFT JOIN usuarios u ON t.id_enfermero = u.id_usuario
                WHERE t.id_cita = :id_cita";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->execute();
        $triaje = $stmt->fetch();

        if (!$triaje) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No se encontró triaje para esta cita'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Triaje obtenido exitosamente',
            'data' => $triaje
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener triaje: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ 2. CONSULTAS MÉDICAS - DIAGNÓSTICO (Lista Cotejo #2) ============

// Crear consulta médica con diagnóstico
$app->post('/api/consultas', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        // Validaciones básicas
        if (empty($data['id_cita']) || empty($data['diagnostico'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de cita y diagnóstico son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si ya existe consulta para esta cita
        $stmtCheck = $db->prepare("SELECT id_consulta FROM consultas_medicas WHERE id_cita = :id_cita");
        $stmtCheck->bindParam(':id_cita', $data['id_cita']);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Ya existe una consulta médica registrada para esta cita'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Obtener o crear historial clínico del paciente
        $stmtPaciente = $db->prepare("
            SELECT p.id_paciente FROM citas c 
            JOIN pacientes p ON c.id_paciente = p.id_paciente 
            WHERE c.id_cita = :id_cita
        ");
        $stmtPaciente->bindParam(':id_cita', $data['id_cita']);
        $stmtPaciente->execute();
        $paciente = $stmtPaciente->fetch();

        if (!$paciente) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No se encontró la cita especificada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Verificar si existe historial clínico
        $stmtHistorial = $db->prepare("SELECT id_historial FROM historiales_clinicos WHERE id_paciente = :id_paciente");
        $stmtHistorial->bindParam(':id_paciente', $paciente['id_paciente']);
        $stmtHistorial->execute();
        $historial = $stmtHistorial->fetch();

        $id_historial = null;
        if (!$historial) {
            // Crear historial clínico si no existe
            $stmtCreateHistorial = $db->prepare("INSERT INTO historiales_clinicos (id_paciente) VALUES (:id_paciente)");
            $stmtCreateHistorial->bindParam(':id_paciente', $paciente['id_paciente']);
            $stmtCreateHistorial->execute();
            $id_historial = $db->lastInsertId();
        } else {
            $id_historial = $historial['id_historial'];
        }

        $sql = "INSERT INTO consultas_medicas (
            id_cita, id_historial, motivo_consulta, sintomatologia,
            diagnostico, tratamiento, observaciones, fecha_seguimiento
        ) VALUES (
            :id_cita, :id_historial, :motivo_consulta, :sintomatologia,
            :diagnostico, :tratamiento, :observaciones, :fecha_seguimiento
        )";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_cita', $data['id_cita']);
        $stmt->bindParam(':id_historial', $id_historial);
        $stmt->bindParam(':motivo_consulta', $data['motivo_consulta'] ?? '');
        $stmt->bindParam(':sintomatologia', $data['sintomatologia']);
        $stmt->bindParam(':diagnostico', $data['diagnostico']);
        $stmt->bindParam(':tratamiento', $data['tratamiento']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':fecha_seguimiento', $data['fecha_seguimiento']);

        $stmt->execute();
        $consultaId = $db->lastInsertId();

        // Obtener la consulta creada
        $stmtGet = $db->prepare("SELECT * FROM consultas_medicas WHERE id_consulta = :id");
        $stmtGet->bindParam(':id', $consultaId);
        $stmtGet->execute();
        $consulta = $stmtGet->fetch();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Consulta médica registrada exitosamente',
            'data' => $consulta
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al registrar consulta médica: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Obtener consulta médica por cita
$app->get('/api/consultas/cita/{id_cita}', function (Request $request, Response $response, $args) {
    try {
        $id_cita = $args['id_cita'];
        $db = App\Config\Database::getConnection();

        $sql = "SELECT cm.* FROM consultas_medicas cm WHERE cm.id_cita = :id_cita";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->execute();
        $consulta = $stmt->fetch();

        if (!$consulta) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'No se encontró consulta médica para esta cita'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Consulta médica obtenida exitosamente',
            'data' => $consulta
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener consulta médica: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ 3. TRATAMIENTOS (Lista Cotejo #3) ============

// Crear tratamiento
$app->post('/api/tratamientos', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        // Validaciones básicas
        if (empty($data['id_consulta']) || empty($data['nombre_tratamiento'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de consulta y nombre del tratamiento son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sql = "INSERT INTO tratamientos (
            id_consulta, nombre_tratamiento, descripcion, fecha_inicio,
            fecha_fin, frecuencia, duracion_sesiones, numero_sesiones,
            observaciones, estado
        ) VALUES (
            :id_consulta, :nombre_tratamiento, :descripcion, :fecha_inicio,
            :fecha_fin, :frecuencia, :duracion_sesiones, :numero_sesiones,
            :observaciones, :estado
        )";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_consulta', $data['id_consulta']);
        $stmt->bindParam(':nombre_tratamiento', $data['nombre_tratamiento']);
        $stmt->bindParam(':descripcion', $data['descripcion']);
        $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
        $stmt->bindParam(':fecha_fin', $data['fecha_fin']);
        $stmt->bindParam(':frecuencia', $data['frecuencia']);
        $stmt->bindParam(':duracion_sesiones', $data['duracion_sesiones']);
        $stmt->bindParam(':numero_sesiones', $data['numero_sesiones']);
        $stmt->bindParam(':observaciones', $data['observaciones']);
        $stmt->bindParam(':estado', $data['estado'] ?? 'Activo');

        $stmt->execute();
        $tratamientoId = $db->lastInsertId();

        // Obtener el tratamiento creado
        $stmtGet = $db->prepare("SELECT * FROM tratamientos WHERE id_tratamiento = :id");
        $stmtGet->bindParam(':id', $tratamientoId);
        $stmtGet->execute();
        $tratamiento = $stmtGet->fetch();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Tratamiento registrado exitosamente',
            'data' => $tratamiento
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al registrar tratamiento: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Obtener tratamientos por consulta
$app->get('/api/tratamientos/consulta/{id_consulta}', function (Request $request, Response $response, $args) {
    try {
        $id_consulta = $args['id_consulta'];
        $db = App\Config\Database::getConnection();

        $sql = "SELECT * FROM tratamientos WHERE id_consulta = :id_consulta ORDER BY fecha_inicio DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_consulta', $id_consulta);
        $stmt->execute();
        $tratamientos = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Tratamientos obtenidos exitosamente',
            'data' => $tratamientos
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener tratamientos: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ 4. RECETAS MÉDICAS (Lista Cotejo #4) ============

// Crear receta médica
$app->post('/api/recetas', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        $db = App\Config\Database::getConnection();

        // Validaciones básicas
        if (empty($data['id_consulta']) || empty($data['medicamentos'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID de consulta y medicamentos son requeridos'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $sql = "INSERT INTO recetas_medicas (
            id_consulta, medicamentos, instrucciones, fecha_emision,
            fecha_vencimiento, observaciones
        ) VALUES (
            :id_consulta, :medicamentos, :instrucciones, :fecha_emision,
            :fecha_vencimiento, :observaciones
        )";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_consulta', $data['id_consulta']);
        $stmt->bindParam(':medicamentos', $data['medicamentos']);
        $stmt->bindParam(':instrucciones', $data['instrucciones']);
        $stmt->bindParam(':fecha_emision', $data['fecha_emision'] ?? date('Y-m-d'));
        $stmt->bindParam(':fecha_vencimiento', $data['fecha_vencimiento']);
        $stmt->bindParam(':observaciones', $data['observaciones']);

        $stmt->execute();
        $recetaId = $db->lastInsertId();

        // Obtener la receta creada
        $stmtGet = $db->prepare("SELECT * FROM recetas_medicas WHERE id_receta = :id");
        $stmtGet->bindParam(':id', $recetaId);
        $stmtGet->execute();
        $receta = $stmtGet->fetch();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Receta médica registrada exitosamente',
            'data' => $receta
        ]));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al registrar receta médica: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Obtener recetas por consulta
$app->get('/api/recetas/consulta/{id_consulta}', function (Request $request, Response $response, $args) {
    try {
        $id_consulta = $args['id_consulta'];
        $db = App\Config\Database::getConnection();

        $sql = "SELECT * FROM recetas_medicas WHERE id_consulta = :id_consulta ORDER BY fecha_emision DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id_consulta', $id_consulta);
        $stmt->execute();
        $recetas = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Recetas obtenidas exitosamente',
            'data' => $recetas
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener recetas: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ 5. CONSULTA COMPLETA DE CITA (Lista Cotejo #5) ============

// Obtener cita completa con todo el flujo
$app->get('/api/citas/{id_cita}/completa', function (Request $request, Response $response, $args) {
    try {
        $id_cita = $args['id_cita'];
        $db = App\Config\Database::getConnection();

        // Obtener información básica de la cita
        $sqlCita = "
            SELECT 
                c.*,
                CONCAT(up.nombres, ' ', up.apellidos) as nombre_paciente,
                up.cedula as cedula_paciente,
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
            WHERE c.id_cita = :id_cita
        ";

        $stmtCita = $db->prepare($sqlCita);
        $stmtCita->bindParam(':id_cita', $id_cita);
        $stmtCita->execute();
        $cita = $stmtCita->fetch();

        if (!$cita) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Cita no encontrada'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Obtener triaje
        $sqlTriaje = "
            SELECT t.*, CONCAT(u.nombres, ' ', u.apellidos) as nombre_enfermero
            FROM triage t
            LEFT JOIN usuarios u ON t.id_enfermero = u.id_usuario
            WHERE t.id_cita = :id_cita
        ";
        $stmtTriaje = $db->prepare($sqlTriaje);
        $stmtTriaje->bindParam(':id_cita', $id_cita);
        $stmtTriaje->execute();
        $triaje = $stmtTriaje->fetch();

        // Obtener consulta médica
        $sqlConsulta = "SELECT * FROM consultas_medicas WHERE id_cita = :id_cita";
        $stmtConsulta = $db->prepare($sqlConsulta);
        $stmtConsulta->bindParam(':id_cita', $id_cita);
        $stmtConsulta->execute();
        $consulta = $stmtConsulta->fetch();

        // Obtener tratamientos
        $tratamientos = [];
        if ($consulta) {
            $sqlTratamientos = "SELECT * FROM tratamientos WHERE id_consulta = :id_consulta";
            $stmtTratamientos = $db->prepare($sqlTratamientos);
            $stmtTratamientos->bindParam(':id_consulta', $consulta['id_consulta']);
            $stmtTratamientos->execute();
            $tratamientos = $stmtTratamientos->fetchAll();
        }

        // Obtener recetas
        $recetas = [];
        if ($consulta) {
            $sqlRecetas = "SELECT * FROM recetas_medicas WHERE id_consulta = :id_consulta";
            $stmtRecetas = $db->prepare($sqlRecetas);
            $stmtRecetas->bindParam(':id_consulta', $consulta['id_consulta']);
            $stmtRecetas->execute();
            $recetas = $stmtRecetas->fetchAll();
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Información completa de la cita obtenida exitosamente',
            'data' => [
                'cita' => $cita,
                'triaje' => $triaje ?: null,
                'consulta' => $consulta ?: null,
                'tratamientos' => $tratamientos,
                'recetas' => $recetas
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener información completa de la cita: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ ENDPOINTS ADICIONALES ÚTILES ============

// Obtener lista de enfermeros (para el triaje)
$app->get('/api/enfermeros', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();

        $sql = "SELECT u.id_usuario, u.nombres, u.apellidos, u.cedula, u.correo
                FROM usuarios u 
                JOIN roles_usuarios ru ON u.id_usuario = ru.id_usuario 
                WHERE ru.id_rol = 3 AND u.activo = 1
                ORDER BY u.nombres, u.apellidos";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $enfermeros = $stmt->fetchAll();

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Enfermeros obtenidos exitosamente',
            'data' => $enfermeros
        ]));

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error al obtener enfermeros: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});
// ============ RUTAS DE ROLES (requieren autenticación) ============
$app->group('/api/roles', function ($group) {
    $group->get('/{roleId}/menus', \App\Controllers\RoleController::class . ':getMenusByRole');
    $group->get('/{roleId}/permissions', \App\Controllers\RoleController::class . ':getRolePermissions');
})->add(new AuthMiddleware());

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