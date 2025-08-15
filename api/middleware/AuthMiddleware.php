<?php
namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware {
    private $secretKey = "tu_clave_secreta_super_segura_2025";

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $response = new \Slim\Psr7\Response();
        
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token de autorización requerido'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            $request = $request->withAttribute('user', $decoded);
            return $handler->handle($request);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token inválido: ' . $e->getMessage()
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>