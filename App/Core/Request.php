<?php

namespace App\Core;

use App\Utils\JwtUtils;
use Exception;

Routes::post('/auth/login', 'AuthController::store');
Routes::get('/users', 'UserController::index');
Routes::post('/users', 'UserController::store');
Routes::get('/courses', 'CourseController::index', true);

class Request
{
    public static function handler($server)
    {
        $endpoint = $server['REQUEST_URI'];
        $httpMethod = $server['REQUEST_METHOD'];

        if ($endpoint[strlen($endpoint) - 1] === '/') {
            $endpoint = substr($endpoint, 0, strlen($endpoint) - 1);
        }

        $parsed_url = parse_url($endpoint);
        $endpoint = $parsed_url['path'];

        $route = Routes::get_route($httpMethod, $endpoint);

        if (!$route) {
            http_response_code(404);
            $data = [
                "status" => 404,
                "messsage" => "Router not found"
            ];

            echo json_encode($data);
            return;
        }

        try {
            if ($route->isNeedAuth) {
                $header = getallheaders();

                if (!isset($header['Authorization'])) {
                    throw new Exception("Is need Bearer token.");
                }

                $token = explode(' ', $header['Authorization'])[1];

                if (!JwtUtils::is_valid_token($token)) {
                    return throw new Exception('Invalid token.');
                }
            }

            $className = $route->controllerName;
            $class = "App\Controllers\\{$className}";
            $classInstance = new $class();

            if ($route->httpMethod === "POST") {
                $server['body'] = json_decode(file_get_contents('php://input'));
            }

            $server['query'] = $parsed_url['query'] ?? '';

            echo call_user_func([$classInstance, $route->controllerMethod], $server);
        } catch (Exception $except) {
            http_response_code(400);
            $data = [
                "status" => 400,
                "message" => $except->getMessage(),
            ];

            echo json_encode($data);
        }
    }
}
