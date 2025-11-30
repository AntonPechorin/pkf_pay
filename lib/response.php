<?php
/**
 * Отправка JSON-ответа и завершение выполнения.
 */
function send_json_response($data, $statusCode)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function response_success($data)
{
    send_json_response(array(
        'success' => true,
        'error' => null,
        'data' => $data
    ), 200);
}

function response_error($message, $statusCode)
{
    send_json_response(array(
        'success' => false,
        'error' => $message,
        'data' => null
    ), $statusCode);
}
