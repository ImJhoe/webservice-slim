<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Controllers\AuthController;
use App\Controllers\HistorialController;
use App\Controllers\CitaController;
use App\Middleware\AuthMiddleware;

require __DIR__ . '/../api/autoload.php';
require __DIR__ . '/../api/config/Database.php';

// Crear la aplicación Slim
$app = AppFactory::create();

// Middleware para parsing JSON
$app->addBodyParsingMiddleware();

// Middleware de manejo de errores
$app->addErrorMiddleware(true, true, true);

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
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'success' => true,
        'message' => 'API de Citas Médicas - Sistema funcionando correctamente',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /auth/login' => 'Autenticación de usuarios',
            'POST /auth/cambiar-password' => 'Cambio de contraseña',
            'POST /historial/buscar-cedula' => 'Buscar historial por cédula',
            'POST /citas/consultar-especialidad-medico' => 'Consultar citas por especialidad/médico',
            'POST /citas/consultar-rango-fechas' => 'Consultar citas por rango de fechas'
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ============ RUTAS DE AUTENTICACIÓN ============

$app->group('/auth', function ($group) {
    $group->post('/login', AuthController::class . ':login');
    $group->post('/cambiar-password', AuthController::class . ':cambiarPassword');
});

// ============ RUTAS PROTEGIDAS (requieren autenticación) ============

$app->group('/historial', function ($group) {
    $group->post('/buscar-cedula', HistorialController::class . ':buscarPorCedula');
})->add(new AuthMiddleware());

$app->group('/citas', function ($group) {
    $group->post('/consultar-especialidad-medico', CitaController::class . ':consultarPorEspecialidadYMedico');
    $group->post('/consultar-rango-fechas', CitaController::class . ':consultarPorRangoFechas');
})->add(new AuthMiddleware());

// Manejo de rutas no encontradas
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado'
    ]));
    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
});
$app->group('/consultas', function ($group) {
    $group->get('/especialidades', ConsultaController::class . ':obtenerEspecialidades');
    $group->get('/medicos', ConsultaController::class . ':obtenerMedicos');
})->add(new AuthMiddleware());


$app->run();
?>