<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Запись сообщения в лог.
 */
function logger_log($message, $channel)
{
    $date = date(DATE_FORMAT_FULL);
    $dir = LOG_DIR . '/' . $channel;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $file = $dir . '/' . date('Y-m-d') . '.log';
    $line = '[' . $date . '] ' . $message . "\n";
    file_put_contents($file, $line, FILE_APPEND);
}

function logger_debug($message, $channel)
{
    if (PAYMENT_LOG_LEVEL === 'debug') {
        logger_log('[DEBUG] ' . $message, $channel);
    }
}

function logger_info($message, $channel)
{
    if (PAYMENT_LOG_LEVEL === 'debug' || PAYMENT_LOG_LEVEL === 'info') {
        logger_log('[INFO] ' . $message, $channel);
    }
}

function logger_error($message, $channel)
{
    logger_log('[ERROR] ' . $message, $channel);
    logger_log('[ERROR] ' . $message, 'errors');
}
