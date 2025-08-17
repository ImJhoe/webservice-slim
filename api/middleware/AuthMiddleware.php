<?php
namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware {
    private $secretKey = "tu_clave_secreta_super_segura_2025";

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $response = new SlimResponse();
        
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token de autorización requerido',
                'code' => 'MISSING_TOKEN'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Formato de token inválido. Use: Bearer <token>',
                'code' => 'INVALID_TOKEN_FORMAT'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            // Verificar que el token tenga los campos necesarios
            if (!isset($decoded->user_id) || !isset($decoded->username) || !isset($decoded->rol)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Token inválido: faltan datos requeridos',
                    'code' => 'INVALID_TOKEN_DATA'
                ]));
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
            }

            // Agregar información del usuario al request
            $request = $request->withAttribute('user', $decoded);
            return $handler->handle($request);

        } catch (ExpiredException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token expirado',
                'code' => 'TOKEN_EXPIRED'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        } catch (SignatureInvalidException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Firma del token inválida',
                'code' => 'INVALID_SIGNATURE'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Token inválido',
                'error' => $e->getMessage(),
                'code' => 'INVALID_TOKEN'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
}
?>