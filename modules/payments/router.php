<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/provider_prodamus.php';
require_once __DIR__ . '/provider_tbank_acquiring.php';
require_once __DIR__ . '/provider_tbank_installment.php';
require_once __DIR__ . '/dispatcher.php';

/**
 * Роутер платежей. Принимает сегменты пути и направляет в нужный адаптер.
 */
function payments_route($segments, $method, $headers, $rawBody)
{
    if (count($segments) < 2) {
        response_error('Неизвестный платежный маршрут', 404);
    }

    $provider = $segments[1];
    $events = array();

    if ($provider === 'prodamus') {
        $events = provider_prodamus_handle($headers, $rawBody);
    } elseif ($provider === 'tbank') {
        if (isset($segments[2]) && $segments[2] === 'installment') {
            $events = provider_tbank_installment_handle($headers, $rawBody);
        } else {
            $events = provider_tbank_acquiring_handle($headers, $rawBody);
        }
    } else {
        response_error('Платежный провайдер не поддерживается', 404);
    }

    if ($events === false) {
        response_error('Ошибка обработки платежа', 400);
    }

    if (isset($events[0]) && is_array($events[0])) {
        $result = payments_dispatch_events($events);
    } else {
        $result = payments_dispatch_events(array($events));
    }

    // Для платежных систем чаще всего достаточно вернуть текст "OK".
    if ($result['success']) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }

    response_error('Ошибка обработки события: ' . $result['error'], 500);
}
