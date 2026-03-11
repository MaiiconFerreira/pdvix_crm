<?php

/**
 * TESTE DIRETO API PAGAR.ME v5
 * Testa endpoints reais suportados pela API
 */

define('API_KEY', 'sk_7b77b2c61c0a443ea83bca26744ed8cb');
define('CHARGE_ID', 'ch_ezv7R4vDFGhQ3QLK');
define('ORDER_ID', 'or_GVxeWx5UZ3uXwlB2');

$baseUrl = "https://api.pagar.me/core/v5";

function request($method, $path, $body = null)
{
    global $baseUrl;

    $url = $baseUrl . $path;

    $headers = [
        'Authorization: Basic ' . base64_encode(API_KEY . ':'),
        'Accept: application/json'
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30
    ]);

    if ($body !== null) {

        $bodyJson = json_encode($body);

        $headers[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        echo "CURL ERROR: " . curl_error($ch) . "\n";
    }

    curl_close($ch);

    echo "\n=============================\n";
    echo "REQUEST: $method $url\n";
    echo "HTTP: $http\n";

    if ($body) {
        echo "BODY:\n" . json_encode($body, JSON_PRETTY_PRINT) . "\n";
    }

    $decoded = json_decode($response, true);

    if ($decoded) {
        echo "RESPONSE:\n" . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "RESPONSE RAW:\n$response\n";
    }

    return $decoded;
}

echo "\n\n===== TESTE PAGARME =====\n";

/*
====================================
1 - CONSULTAR CHARGE
====================================
*/

echo "\n\n[1] CONSULTANDO CHARGE\n";

request(
    'GET',
    "/charges/" . CHARGE_ID
);

/*
====================================
2 - CANCELAR CHARGE
====================================
*/

echo "\n\n[2] DELETE /charges/{charge_id}\n";

request(
    'DELETE',
    "/charges/" . CHARGE_ID
);

/*
====================================
3 - CONSULTAR ORDER
====================================
*/

echo "\n\n[3] CONSULTANDO ORDER\n";

request(
    'GET',
    "/orders/" . ORDER_ID
);

/*
====================================
4 - FECHAR ORDER (cancelando)
====================================
*/

echo "\n\n[4] PATCH /orders/{order_id}/closed\n";

request(
    'PATCH',
    "/orders/" . ORDER_ID . "/closed",
    [
        "status" => "canceled"
    ]
);

echo "\n\n===== FIM TESTE =====\n";