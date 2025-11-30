<?php
/**
 * Получить сырое тело запроса.
 */
function get_raw_input()
{
    return file_get_contents('php://input');
}

/**
 * Нормализация URI в массив сегментов.
 */
function get_path_segments()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $uri = parse_url($uri, PHP_URL_PATH);
    $uri = trim($uri, '/');
    if ($uri === '') {
        return array();
    }
    return explode('/', $uri);
}

/**
 * Получить данные JSON из тела запроса.
 */
function get_json_body()
{
    $raw = get_raw_input();
    $decoded = json_decode($raw, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    return $decoded;
}

/**
 * Простейшая фильтрация строки.
 */
function sanitize_string($value)
{
    return trim(strip_tags($value));
}

/**
 * Проверка, является ли строка JSON.
 */
function is_valid_json($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Получить заголовок Authorization: Bearer.
 */
function get_bearer_token()
{
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        }
    }
    if (strpos($header, 'Bearer ') === 0) {
        return substr($header, 7);
    }
    return '';
}
