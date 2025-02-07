<?php

require __DIR__ . '/vendor/autoload.php';

use MercadoPago\SDK;

// Configuração do Mercado Pago
define('MERCADO_PAGO_ACCESS_TOKEN', 'SUA_ACCESS_TOKEN_AQUI');
SDK::setAccessToken(MERCADO_PAGO_ACCESS_TOKEN);

// Captura os dados enviados via POST
$payment_data = [
    "transaction_amount" => $_POST['amount'] ?? 0.00, // Valor do pagamento
    "token" => $_POST['token'] ?? '', // Token gerado pelo Mercado Pago
    "description" => "Pagamento PHPNuxBill",
    "payment_method_id" => $_POST['payment_method_id'] ?? '',
    "payer" => [
        "email" => $_POST['email'] ?? 'email@exemplo.com'
    ]
];

// Converte os dados para JSON
$payment_json = json_encode($payment_data);

// Envia os dados via cURL para a API do Mercado Pago
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL            => "https://api.mercadopago.com/v1/payments",
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => $payment_json,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . MERCADO_PAGO_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

// Decodifica a resposta
$response_data = json_decode($response, true);

// Verifica se o pagamento foi aprovado
if ($http_code == 200 && isset($response_data['status']) && $response_data['status'] == 'approved') {
    $order_id = $response_data['external_reference'] ?? 'N/A';
    $amount = $response_data['transaction_amount'];
    $payer_email = $response_data['payer']['email'];

    // Registrar o pagamento no PHPNuxBill
    include_once 'system/paymentgateway/functions.php';
    process_payment($order_id, $amount, $payer_email);

    file_put_contents('payments.log', "Pagamento aprovado: Ordem $order_id, Valor $amount, Email $payer_email" . PHP_EOL, FILE_APPEND);
    
    echo json_encode(["status" => "success", "message" => "Pagamento aprovado!"]);
} else {
    echo json_encode(["status" => "error", "message" => $response_data['message'] ?? "Erro ao processar pagamento"]);
}
