<?php
// Упрощённая реализация библиотеки Hmac от Prodamus для проверки подписи webhook.
// Использует алгоритм HMAC-SHA256, сортирует параметры по ключам и сериализует в JSON.

class Hmac
{
    public static function sign($data, $secret)
    {
        if (!is_array($data)) {
            return '';
        }
        ksort($data);
        $prepared = self::prepare_value($data);
        $json = json_encode($prepared, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $json, $secret);
    }

    public static function verify($data, $secret, $signature)
    {
        if ($signature === '') {
            return false;
        }
        $calculated = self::sign($data, $secret);
        return hash_equals($calculated, $signature);
    }

    protected static function prepare_value($value)
    {
        if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $result[$key] = self::prepare_value($item);
                } else {
                    $result[$key] = strval($item);
                }
            }
            ksort($result);
            return $result;
        }
        return strval($value);
    }
}
