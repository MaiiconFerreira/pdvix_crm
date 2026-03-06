<?php
require __DIR__ . '/vendor/autoload.php';

// Conexão Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Simulação de dados vindo do seu App/Banco
$payload = [
    "tokens" => [
        "ExponentPushToken[XXXXXXXXXXXXX]", // Coloque um token válido aqui
        "ExponentPushToken[YYYYYYYYYYYYY]"  // Você pode testar o chunking enviando vários
    ],
    "title" => "🔔 Teste de Sistema",
    "body"  => "Enviado via script de teste às " . date('H:i:s'),
    "data"  => [
        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
        "id_referencia" => 123
    ]
];

// Envia para a fila que o worker está escutando
$jsonPayload = json_encode($payload);
$result = $redis->lpush('expo_push_queue', $jsonPayload);

if ($result) {
    echo " [OK] Payload enviado para a fila 'expo_push_queue' (" . strlen($jsonPayload) . " bytes)\n";
} else {
    echo " [ERRO] Falha ao inserir no Redis\n";
}