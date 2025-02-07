<?php

/**
 * Mercado Pago Payment Gateway Integration
 * Adaptado do UMSPay para Mercado Pago
 **/

function mercadopago_validate_config()
{
    global $config;
    if (empty($config['mercadopago_access_token'])) {
        sendTelegram("Mercado Pago payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup Mercado Pago payment gateway, please tell admin"));
    }
}

function mercadopago_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'Mercado Pago - Payment Gateway - ' . $config['CompanyName']);
    $ui->display('mercadopago.tpl');
}

function mercadopago_save_config()
{
    global $admin, $_L;
    $access_token = _post('access_token');

    $checkAccessToken = ORM::for_table('tbl_appconfig')->where('setting', 'mercadopago_access_token')->find_one();
    if ($checkAccessToken) {
        $checkAccessToken->value = $access_token;
        $checkAccessToken->save();
    } else {
        $newSetting = ORM::for_table('tbl_appconfig')->create();
        $newSetting->setting = 'mercadopago_access_token';
        $newSetting->value = $access_token;
        $newSetting->save();
    }

    r2(U . 'settings/payment_gateways', 's', $_L['Settings Saved Successfully']);
}

function mercadopago_process_payment($invoice_id, $amount)
{
    global $config;
    $access_token = $config['mercadopago_access_token'];

    $url = "https://api.mercadopago.com/v1/payments";
    
    $headers = [
        "Authorization: Bearer " . $access_token,
        "Content-Type: application/json"
    ];

    $data = [
        "transaction_amount" => (float) $amount,
        "description" => "Invoice #$invoice_id",
        "payment_method_id" => "pix",
        "payer" => [
            "email" => "cliente@example.com"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
