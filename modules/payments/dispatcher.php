<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../certs/certificates.php';
require_once __DIR__ . '/../courses/access.php';
require_once __DIR__ . '/../fiscal/atol.php';
require_once __DIR__ . '/../orders/cert_orders.php';
require_once __DIR__ . '/../orders/main_orders.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/atol.php';

/**
 * Обработка одного или нескольких событий платежей.
 */
function payments_dispatch_events($events)
{
    $connection = db_get_connection();
    $atolConfig = require __DIR__ . '/../../config/atol.php';
    foreach ($events as $event) {
        $result = payments_handle_single_event($connection, $event, $atolConfig);
        if (!$result['success']) {
            return $result;
        }
    }
    return array('success' => true, 'error' => null);
}

function payments_handle_single_event($connection, $event, $atolConfig)
{
    $orderType = isset($event['order_type']) ? $event['order_type'] : '';

    if ($orderType === 'certificate') {
        return payments_process_certificate($connection, $event, $atolConfig);
    } elseif ($orderType === 'course') {
        return payments_process_course($connection, $event, $atolConfig);
    } elseif ($orderType === 'jshopping') {
        logger_info('Событие для jshopping: ' . json_encode($event), 'payments');
        return array('success' => true, 'error' => null);
    }

    logger_error('Неизвестный тип заказа: ' . $orderType, 'payments');
    return array('success' => false, 'error' => 'Неизвестный тип заказа');
}

function payments_process_certificate($connection, $event, $atolConfig)
{
    if (!db_begin_transaction($connection)) {
        return array('success' => false, 'error' => 'Ошибка транзакции');
    }

    $certResult = certs_handle_payment_result($event, $connection);

    if (!$certResult['success']) {
        db_rollback($connection);
        return $certResult;
    }

    if ($event['status'] === 'success' && isset($atolConfig['enabled']) && $atolConfig['enabled']) {
        atol_issue_receipt(array('type' => 'certificate', 'order_id' => $event['order_id']), $event, $connection);
    }

    db_commit($connection);
    return array('success' => true, 'error' => null);
}

function payments_process_course($connection, $event, $atolConfig)
{
    if (!db_begin_transaction($connection)) {
        return array('success' => false, 'error' => 'Ошибка транзакции');
    }

    $courseResult = courses_handle_payment_result($connection, $event);
    if (!$courseResult['success']) {
        db_rollback($connection);
        return $courseResult;
    }

    if ($event['status'] === 'success' && isset($atolConfig['enabled']) && $atolConfig['enabled']) {
        atol_issue_receipt(array('type' => 'course', 'order_id' => $event['order_id']), $event, $connection);
    }

    db_commit($connection);
    return array('success' => true, 'error' => null);
}
