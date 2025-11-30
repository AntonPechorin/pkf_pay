<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../config/atol.php';

/**
 * Заглушка формирования и отправки чека в Атол.
 */
function atol_issue_receipt($orderData, $paymentEvent, $connection)
{
    $config = require __DIR__ . '/../../config/atol.php';
    if (!isset($config['enabled']) || !$config['enabled']) {
        logger_info('Атол выключен, чек не отправляем', 'payments');
        return true;
    }

    $payload = array(
        'timestamp' => date(DATE_FORMAT_FULL),
        'service' => array(
            'inn' => $config['inn'],
            'payment_address' => $config['payment_address']
        ),
        'receipt' => array(
            'client' => array(
                'email' => isset($paymentEvent['metadata']['email']) ? $paymentEvent['metadata']['email'] : '',
                'phone' => isset($paymentEvent['metadata']['phone']) ? $paymentEvent['metadata']['phone'] : ''
            ),
            'company' => array(
                'email' => 'info@perexodvtak.ru',
                'sno' => $config['sno'],
                'inn' => $config['inn'],
                'payment_address' => $config['payment_address']
            ),
            'items' => array(
                array(
                    'name' => 'Оплата заказа ' . $orderData['order_id'],
                    'price' => $paymentEvent['amount'],
                    'quantity' => 1,
                    'sum' => $paymentEvent['amount'],
                    'tax' => $config['vat']
                )
            ),
            'payments' => array(
                array('type' => 1, 'sum' => $paymentEvent['amount'])
            ),
            'total' => $paymentEvent['amount']
        )
    );

    logger_info('Подготовка чека Атол: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'payments');
    // Здесь мог бы быть curl-запрос к Атолу.
    return true;
}
