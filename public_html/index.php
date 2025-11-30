<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/response.php';
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../modules/payments/router.php';
require_once __DIR__ . '/../modules/orders/api.php';

$segments = get_path_segments();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$headers = function_exists('getallheaders') ? getallheaders() : array();
$rawBody = get_raw_input();

if (count($segments) === 0) {
    response_success(array('message' => 'API perexodvtak'));
}

if ($segments[0] === 'ping') {
    response_success(array(
        'service' => 'api.perexodvtak',
        'time' => date(DATE_FORMAT_FULL)
    ));
}

if ($segments[0] === 'pay') {
    payments_route($segments, $method, $headers, $rawBody);
}

if ($segments[0] === 'api') {
    if ($method === 'GET' && isset($segments[1]) && $segments[1] === 'orders' && isset($segments[2])) {
        api_get_order($segments[2]);
    }
    if ($method === 'POST' && isset($segments[1]) && $segments[1] === 'orders') {
        $body = get_json_body();
        if ($body === null) {
            response_error('Некорректный JSON', 400);
        }
        api_post_order($body);
    }
    response_error('API метод не найден', 404);
}

response_error('Маршрут не найден', 404);
