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

// Controladores
use App\Controllers\AuthController;
use App\Controllers\HistorialController;
use App\Controllers\CitaController;
use App\Controllers\ConsultaController;
use App\Controllers\MedicoController;
use App\Controllers\PacienteController;
use App\Controllers\HorarioController;

// Middleware
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
            'medicos' => [
                'POST /api/medicos' => 'Crear médico',
                'GET /api/medicos/{cedula}' => 'Buscar médico por cédula'
            ],
            'pacientes' => [
                'POST /api/pacientes' => 'Crear paciente',
                'GET /api/pacientes/{cedula}' => 'Buscar paciente por cédula'
            ],
            'horarios' => [
                'POST /api/horarios' => 'Asignar horario a médico',
                'PUT /api/horarios/{id_horario}' => 'Actualizar horario',
                'GET /api/horarios/medico/{id_medico}' => 'Consultar horarios por médico',
                'GET /api/horarios/medico/{id_medico}/disponibles' => 'Obtener horarios disponibles'
            ],
            'citas' => [
                'POST /api/citas' => 'Crear cita',
                'GET /api/citas/{id_cita}' => 'Consultar cita por número',
                'PUT /api/citas/{id_cita}/estado' => 'Cambiar estado de cita',
                'POST /api/citas/consultar' => 'Consultar citas por especialidad/médico',
                'POST /api/citas/rango-fechas' => 'Consultar citas por rango de fechas'
            ],
            'consultas' => [
                'GET /api/especialidades' => 'Obtener especialidades',
                'GET /api/medicos' => 'Obtener médicos'
            ]
        ],
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'XAMPP',
            'port' => $_SERVER['SERVER_PORT'] ?? '8081',
            'base_path' => $basePath ?: 'No configurado'
        ]
    ], JSON_PRETTY_PRINT));
    
    return $response->withHeader('Content-Type', 'application/json');
});

// ============ RUTAS DE MÉDICOS ============

// 1. API para crear médico (Lista de cotejo #1)
$app->post('/api/medicos', [MedicoController::class, 'crear']);

// Buscar médico por cédula
$app->get('/api/medicos/{cedula}', [MedicoController::class, 'buscarPorCedula']);

// ============ RUTAS DE HORARIOS ============

// 2. API para asignar horarios a médico (Lista de cotejo #2)
$app->post('/api/horarios', [HorarioController::class, 'asignar']);

// Actualizar horario (PUT para cumplir con POST/PUT del cotejo)
$app->put('/api/horarios/{id_horario}', [HorarioController::class, 'actualizar']);

// 3. API para consultar horarios por médico (Lista de cotejo #3)
$app->get('/api/horarios/medico/{id_medico}', [HorarioController::class, 'consultarPorMedico']);

// Obtener horarios disponibles para una fecha específica
$app->get('/api/horarios/medico/{id_medico}/disponibles', [HorarioController::class, 'obtenerDisponibles']);

// ============ RUTAS DE PACIENTES ============

// 5. API para buscar paciente por cédula (Lista de cotejo #5)
$app->get('/api/pacientes/{cedula}', [PacienteController::class, 'buscarPorCedula']);

// 6. API para crear paciente (Lista de cotejo #6)
$app->post('/api/pacientes', [PacienteController::class, 'crear']);

// ============ RUTAS DE CITAS ============

// 4. API de creación de cita (Lista de cotejo #4)
$app->post('/api/citas', [CitaController::class, 'crear']);

// 8. API para consultar cita por número (Lista de cotejo #8)
$app->get('/api/citas/{id_cita}', [CitaController::class, 'obtenerPorId']);

// 9. API para cambio de estado de citas (Lista de cotejo #9)
$app->put('/api/citas/{id_cita}/estado', [CitaController::class, 'cambiarEstado']);

// Consultar citas por especialidad y médico
$app->post('/api/citas/consultar', [CitaController::class, 'consultarPorEspecialidadYMedico']);

// Consultar citas por rango de fechas
$app->post('/api/citas/rango-fechas', [CitaController::class, 'consultarPorRangoFechas']);

// ============ RUTAS DE CONSULTAS GENERALES ============

// Obtener especialidades
$app->get('/api/especialidades', [ConsultaController::class, 'obtenerEspecialidades']);

// Obtener médicos
$app->get('/api/medicos', [ConsultaController::class, 'obtenerMedicos']);

// Obtener sucursales
$app->get('/api/sucursales', [ConsultaController::class, 'obtenerSucursales']);

// ============ RUTAS CON AUTENTICACIÓN ============

// Grupo de rutas protegidas (si necesitas autenticación)
$app->group('/api/auth', function ($group) {
    $group->post('/citas/avanzadas', [CitaController::class, 'consultarPorEspecialidadYMedico']);
    $group->get('/historial/{id_paciente}', [HistorialController::class, 'obtenerPorPaciente']);
}); // ->add(new AuthMiddleware()); // Descomenta si tienes autenticación

// ============ RUTAS DE PRUEBA Y VALIDACIÓN ============

// Endpoint para probar validaciones (Lista de cotejo #10)
$app->post('/api/test/validaciones', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    
    $casos_prueba = [
        'cedula_inexistente' => [
            'descripcion' => 'Probar búsqueda con cédula que no existe',
            'ejemplo' => 'GET /api/pacientes/9999999999'
        ],
        'horario_ocupado' => [
            'descripcion' => 'Intentar crear cita en horario ya ocupado',
            'ejemplo' => 'POST /api/citas con fecha_hora ya reservada'
        ],
        'datos_invalidos' => [
            'descripcion' => 'Enviar datos inválidos para cualquier endpoint',
            'ejemplo' => 'POST /api/medicos sin campos requeridos'
        ],
        'formato_fecha_incorrecto' => [
            'descripción' => 'Enviar fecha en formato incorrecto',
            'ejemplo' => 'POST /api/citas con fecha_hora: "2025/13/45 25:99:99"'
        ]
    ];
    
    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'Casos de prueba para validación de errores',
        'casos_prueba' => $casos_prueba,
        'instrucciones' => 'Use estos casos en Postman para probar el manejo de errores'
    ], JSON_PRETTY_PRINT));
    
    return $response->withHeader('Content-Type', 'application/json');
});

// Endpoint de salud del sistema
$app->get('/api/health', function (Request $request, Response $response) {
    try {
        // Probar conexión a base de datos
        $db = \App\Config\Database::getConnection();
        $stmt = $db->query("SELECT 1");
        $db_status = $stmt ? 'OK' : 'ERROR';
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Sistema funcionando correctamente',
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => [
                'api' => 'OK',
                'database' => $db_status,
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error en el sistema',
            'error' => $e->getMessage()
        ]));
        
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// ============ MANEJO DE RUTAS NO ENCONTRADAS ============

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    $method = $request->getMethod();
    $uri = $request->getUri()->getPath();
    
    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado',
        'error_code' => 'ENDPOINT_NOT_FOUND',
        'details' => [
            'method' => $method,
            'path' => $uri,
            'available_endpoints' => [
                'GET /' => 'Información del API',
                'GET /api/health' => 'Estado del sistema',
                'POST /api/medicos' => 'Crear médico',
                'POST /api/pacientes' => 'Crear paciente',
                'POST /api/citas' => 'Crear cita',
                'GET /api/especialidades' => 'Listar especialidades'
            ]
        ]
    ]));
    
    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});

// Iniciar la aplicación
$app->run();
?>