<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../orders/main_orders.php';

/**
 * Каркас обработки платежей для курсов.
 */
function courses_handle_payment_result($connection, $event)
{
    $order = main_orders_get_by_id($connection, $event['order_id']);
    if ($order === null) {
        logger_error('Курс: заказ не найден id=' . $event['order_id'], 'payments');
        return array('success' => false, 'error' => 'Заказ не найден');
    }

    // Дополняем историю платежей в полях pay_payment и payment_online.
    $history = main_orders_append_payment_history($order, 'pay_payment', $event);
    $updateFields = array(
        'pay_payment' => $history,
        'payment_online' => $history
    );

    // Обновление комментария для прозрачности.
    $comment = $order['comment'];
    $comment .= "\n" . date(DATE_FORMAT_FULL) . ' Провайдер: ' . $event['provider'] . ' статус: ' . $event['status'];
    $updateFields['comment'] = $comment;

    // Обновляем сумму оплачено.
    $paidTotal = isset($order['fakt']) ? intval($order['fakt']) : 0;
    $paidTotal += intval($event['amount']);
    $updateFields['fakt'] = $paidTotal;

    if (!main_orders_update_fields($connection, $event['order_id'], $updateFields)) {
        logger_error('Курс: не удалось обновить заказ', 'payments');
        return array('success' => false, 'error' => 'Ошибка обновления заказа');
    }

    if ($event['status'] === 'success') {
        courses_grant_access($connection, $order, $event);
    }

    return array('success' => true, 'error' => null);
}

/**
 * Заглушка выдачи доступа к курсу.
 * Здесь можно добавить запросы к LMS, отправку письма и т.п.
 */
function courses_grant_access($connection, $order, $event)
{
    logger_info('Выдача доступа к курсу ' . $order['idu'] . ' по заказу ' . $order['id'], 'payments');
    // Пример: добавить запись в таблицу доступа.
    // $sql = 'INSERT INTO course_access ...';
    // db_query_execute($connection, $sql, array(...));
    return true;
}
