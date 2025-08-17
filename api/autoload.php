<?php

// Cargar dependencias de Composer PRIMERO
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Mostrar error más detallado
    die('Error: Las dependencias de Composer no están instaladas. Ejecuta "composer install" desde la raíz del proyecto.');
}

// Autoloader manual para las clases del proyecto
spl_autoload_register(function ($className) {
    // Solo procesar clases que empiecen con App\
    if (strpos($className, 'App\\') !== 0) {
        return;
    }
    
    // Convertir namespace a ruta de archivo
    $className = str_replace('App\\', '', $className);
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    $file = __DIR__ . DIRECTORY_SEPARATOR . $className . '.php';
    
    // Debug: mostrar qué archivo está buscando
    error_log("Buscando clase en: " . $file);
    
    if (file_exists($file)) {
        require_once $file;
        error_log("Cargada clase: " . $file);
    } else {
        error_log("No encontrada clase: " . $file);
    }
});

// Cargar manualmente las clases principales para verificar
$requiredFiles = [
    __DIR__ . '/Config/Database.php',
    __DIR__ . '/Controllers/AuthController.php',
    __DIR__ . '/Controllers/HistorialController.php',
    __DIR__ . '/Controllers/CitaController.php',
    __DIR__ . '/Controllers/ConsultaController.php',
    __DIR__ . '/Middleware/AuthMiddleware.php',
    __DIR__ . '/Models/Usuario.php',
    __DIR__ . '/Models/Cita.php',
    __DIR__ . '/Models/HistorialClinico.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
        error_log("Cargado manualmente: " . $file);
    } else {
        error_log("FALTA ARCHIVO: " . $file);
    }
}
?>