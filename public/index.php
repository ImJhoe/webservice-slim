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
use App\Controllers\CitaController;
use App\Controllers\ConsultaController;
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
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'XAMPP',
            'port' => $_SERVER['SERVER_PORT'] ?? '8081',
            'base_path' => $basePath ?: '(root)'
        ],
        'endpoints' => [
            'GET /' => 'Información de la API',
            'GET /test-db' => 'Test de conexión a base de datos',
            'POST /auth/login' => 'Autenticación de usuarios',
            'POST /auth/cambiar-password' => 'Cambio de contraseña',
            'POST /historial/buscar-cedula' => 'Buscar historial por cédula (requiere token)',
            'POST /citas/consultar-especialidad-medico' => 'Consultar citas por especialidad/médico (requiere token)',
            'POST /citas/consultar-rango-fechas' => 'Consultar citas por rango de fechas (requiere token)',
            'GET /consultas/especialidades' => 'Obtener especialidades (requiere token)',
            'GET /consultas/medicos' => 'Obtener médicos (requiere token)'
        ],
        'test_urls' => [
            'API Info' => $baseUrl . '/',
            'Test DB' => $baseUrl . '/test-db',
            'Login' => $baseUrl . '/auth/login'
        ],
        'status' => 'online'
    ], JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Test de conexión a base de datos
$app->get('/test-db', function (Request $request, Response $response) {
    try {
        $db = App\Config\Database::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
        $result = $stmt->fetch();
        
        // También obtener algunas especialidades para verificar
        $stmt2 = $db->query("SELECT COUNT(*) as total_especialidades FROM especialidades");
        $result2 = $stmt2->fetch();
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Conexión a base de datos exitosa',
            'data' => [
                'total_usuarios' => $result['total'],
                'total_especialidades' => $result2['total_especialidades'],
                'database' => 'menudinamico',
                'connection_test' => 'OK',
                'server_info' => $db->getAttribute(\PDO::ATTR_SERVER_INFO)
            ]
        ], JSON_PRETTY_PRINT));
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => 'Error de conexión a base de datos',
            'error' => $e->getMessage(),
            'code' => 'DATABASE_ERROR'
        ], JSON_PRETTY_PRINT));
    }
    return $response->withHeader('Content-Type', 'application/json');
});

// ============ RUTAS DE AUTENTICACIÓN ============

$app->group('/auth', function ($group) {
    $group->post('/login', [AuthController::class, 'login']);
    $group->post('/cambiar-password', [AuthController::class, 'cambiarPassword']);
});

// ============ RUTAS PROTEGIDAS (requieren autenticación) ============

$app->group('/historial', function ($group) {
    $group->post('/buscar-cedula', [HistorialController::class, 'buscarPorCedula']);
})->add(new AuthMiddleware());

$app->group('/citas', function ($group) {
    $group->post('/consultar-especialidad-medico', [CitaController::class, 'consultarPorEspecialidadYMedico']);
    $group->post('/consultar-rango-fechas', [CitaController::class, 'consultarPorRangoFechas']);
})->add(new AuthMiddleware());

$app->group('/consultas', function ($group) {
    $group->get('/especialidades', [ConsultaController::class, 'obtenerEspecialidades']);
    $group->get('/medicos', [ConsultaController::class, 'obtenerMedicos']);
})->add(new AuthMiddleware());

// Manejo de rutas no encontradas
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) use ($basePath) {
    $baseUrl = 'http://localhost:8081' . $basePath;
    
    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado',
        'code' => 'ENDPOINT_NOT_FOUND',
        'requested_path' => $request->getUri()->getPath(),
        'method' => $request->getMethod(),
        'base_path' => $basePath,
        'available_endpoints' => [
            'GET ' . $baseUrl . '/',
            'GET ' . $baseUrl . '/test-db', 
            'POST ' . $baseUrl . '/auth/login',
            'POST ' . $baseUrl . '/auth/cambiar-password',
            'POST ' . $baseUrl . '/historial/buscar-cedula',
            'POST ' . $baseUrl . '/citas/consultar-especialidad-medico',
            'POST ' . $baseUrl . '/citas/consultar-rango-fechas',
            'GET ' . $baseUrl . '/consultas/especialidades',
            'GET ' . $baseUrl . '/consultas/medicos'
        ]
    ], JSON_PRETTY_PRINT));
    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});

try {
    $app->run();
} catch (\Exception $e) {
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