<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway Mercado Pago PIX
 *
 * Utilizes Mercado Pago Checkout API for PIX payments
 *
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
  $ui->assign('_title', 'Mercado Pago PIX - Payment Gateway - ' . $config['CompanyName']);
  $ui->display('mercadopago.tpl');
}


function mercadopago_save_config()
{
  global $admin, $_L;
  $access_token = _post('access_token');
  $pix_key = _post('pix_key');
  $sandbox_mode = _post('sandbox_mode');

  $checkAccessToken = ORM::for_table('tbl_appconfig')->where('setting', 'mercadopago_access_token')->find_one();
  if ($checkAccessToken) {
    $checkAccessToken->value = $access_token;
    $checkAccessToken->save();
  } else {
    $checkAccessToken = ORM::for_table('tbl_appconfig')->create();
    $checkAccessToken->setting = 'mercadopago_access_token';
    $checkAccessToken->value = $access_token;
    $checkAccessToken->save();
  }

  $checkPixKey = ORM::for_table('tbl_appconfig')->where('setting', 'mercadopago_pix_key')->find_one();
  if ($checkPixKey) {
    $checkPixKey->value = $pix_key;
    $checkPixKey->save();
  } else {
    $checkPixKey = ORM::for_table('tbl_appconfig')->create();
    $checkPixKey->setting = 'mercadopago_pix_key';
    $checkPixKey->value = $pix_key;
    $checkPixKey->save();
  }

  $checkSandboxMode = ORM::for_table('tbl_appconfig')->where('setting', 'mercadopago_sandbox_mode')->find_one();
  if ($checkSandboxMode) {
    $checkSandboxMode->value = $sandbox_mode;
    $checkSandboxMode->save();
  } else {
    $checkSandboxMode = ORM::for_table('tbl_appconfig')->create();
    $checkSandboxMode->setting = 'mercadopago_sandbox_mode';
    $checkSandboxMode->value = $sandbox_mode;
    $checkSandboxMode->save();
  }

  _log('[' . $admin['username'] . ']: Mercado Pago ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);

  r2(U . 'paymentgateway/mercadopago', 's', $_L['Settings_Saved_Successfully']);
}


function mercadopago_create_transaction($trx, $user)
{
  global $config, $ui;
  
  // Determine API URL based on sandbox mode
  $is_sandbox = ($config['mercadopago_sandbox_mode'] == '1');
  $api_url = 'https://api.mercadopago.com/v1/payments';
  
  // Generate a unique transaction ID
  $transaction_id = 'NUX' . time() . rand(100, 999);
  
  // Create payment data for PIX
  $payment_data = [
    'transaction_amount' => (float)$trx['price'],
    'description' => 'Internet Package: ' . $trx['plan_name'],
    'payment_method_id' => 'pix',
    'payer' => [
      'email' => 'cliente_' . $user['id'] . '@spaconett.com',
      'first_name' => $user['fullname'] ?: 'Cliente',
      'last_name' => ' '
    ],
    'notification_url' => U . 'callback/mercadopago',
    'external_reference' => $trx['id']
  ];
  
  // Add headers with authentication
  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $config['mercadopago_access_token']
  ];
  
  // Make API request to create payment
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payment_data),
    CURLOPT_HTTPHEADER => $headers
  ]);
  
  $response = curl_exec($curl);
  $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);
  $responseData = json_decode($response);
  
  // Log the request and response for debugging
  $logFile = "MercadoPagoPixRequest.json";
  $log = fopen($logFile, "a");
  fwrite($log, "Request: " . json_encode($payment_data) . "\n");
  fwrite($log, "Response: " . $response . "\n");
  fwrite($log, "HTTP Code: " . $http_code . "\n");
  fwrite($log, "-------------------\n");
  fclose($log);
  
  // Check if payment was created successfully
  if (isset($responseData->id) && isset($responseData->point_of_interaction->transaction_data->qr_code)) {
    // Extract PIX data
    $payment_id = $responseData->id;
    $qr_code = $responseData->point_of_interaction->transaction_data->qr_code;
    $qr_code_base64 = $responseData->point_of_interaction->transaction_data->qr_code_base64;
    $expiration_date = isset($responseData->date_of_expiration) ? 
                        date('Y-m-d H:i:s', strtotime($responseData->date_of_expiration)) : 
                        date('Y-m-d H:i:s', strtotime("+30 minutes"));
    
    // Update payment gateway record
    $d = ORM::for_table('tbl_payment_gateway')
      ->where('username', $user['username'])
      ->where('status', 1)
      ->find_one();
      
    $d->gateway_trx_id = $payment_id;
    $d->pg_request = json_encode($payment_data);
    $d->pg_paid_response = json_encode([
      'qr_code' => $qr_code,
      'qr_code_base64' => $qr_code_base64
    ]);
    $d->expired_date = $expiration_date;
    $d->save();
    
    // Display PIX payment page
    $ui->assign('_title', 'PIX Payment - ' . $config['CompanyName']);
    $ui->assign('trx', $trx);
    $ui->assign('user', $user);
    $ui->assign('payment_id', $payment_id);
    $ui->assign('qr_code', $qr_code);
    $ui->assign('qr_code_base64', $qr_code_base64);
    $ui->assign('expiration_date', $expiration_date);
    $ui->assign('trx_id', $d['id']);
    
    $ui->display('mercadopago_pix.tpl');
    exit;
  } else {
    // Handle error
    $error_message = isset($responseData->message) ? $responseData->message : "Unknown error";
    if (isset($responseData->error)) {
      $error_message .= " - " . $responseData->error;
    }
    
    sendTelegram("Mercado Pago PIX payment failed\n\n" . json_encode($responseData, JSON_PRETTY_PRINT));
    r2(U . 'order/package', 'e', Lang::T("Failed to create PIX payment. Error: " . $error_message));
  }
}


function mercadopago_payment_notification()
{
  global $config;
  
  // Get the payment data from Mercado Pago
  $data = file_get_contents('php://input');
  $notification = json_decode($data, true);
  
  // Log the notification
  $logFile = "MercadoPagoCallback.json";
  $log = fopen($logFile, "a");
  fwrite($log, $data . "\n");
  fclose($log);
  
  // Process the notification
  if (isset($notification['data']['id'])) {
    $payment_id = $notification['data']['id'];
    
    // Get payment details from Mercado Pago API
    $url = "https://api.mercadopago.com/v1/payments/{$payment_id}";
    $headers = [
      'Authorization: Bearer ' . $config['mercadopago_access_token']
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    $payment_data = json_decode($response, true);
    
    if (isset($payment_data['external_reference']) && isset($payment_data['status'])) {
      $trx_id = $payment_data['external_reference'];
      $status = $payment_data['status'];
      
      // Find the transaction
      $trx = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx_id)
        ->find_one();
        
      if (!$trx) {
        // Try to find by gateway_trx_id
        $trx = ORM::for_table('tbl_payment_gateway')
          ->where('gateway_trx_id', $payment_id)
          ->find_one();
          
        if (!$trx) {
          header("HTTP/1.1 200 OK");
          exit;
        }
      }
      
      // Process based on payment status
      if ($status == 'approved') {
        // Payment approved, activate the package
        $user = ORM::for_table('tbl_customers')
          ->where('username', $trx['username'])
          ->find_one();
          
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Mercado Pago PIX')) {
          _log("Mercado Pago PIX Payment Successful, But Failed to activate Package");
        }
        
        _log("Mercado Pago PIX Payment Successful");
        $trx->pg_paid_response = json_encode($payment_data);
        $trx->payment_method = 'Mercado Pago';
        $trx->payment_channel = 'PIX';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2;
        $trx->save();
      } else if ($status == 'pending' || $status == 'in_process') {
        // Payment pending, update status
        $trx->pg_paid_response = json_encode($payment_data);
        $trx->status = 1;
        $trx->save();
      } else {
        // Payment failed or rejected
        $trx->pg_paid_response = json_encode($payment_data);
        $trx->status = 3; // Failed status
        $trx->save();
      }
    }
  }
  
  // Always return 200 to acknowledge receipt
  header("HTTP/1.1 200 OK");
  exit;
}


function mercadopago_get_status($trx, $user)
{
  global $config, $ui;
  
  // Check if the transaction is already paid
  if ($trx['status'] == 2) {
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment has been confirmed and your package is active."));
    return;
  }
  
  // Check if the transaction has expired
  if (strtotime($trx['expired_date']) < time()) {
    r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Transaction has expired. Please create a new order."));
    return;
  }
  
  // Get payment details from Mercado Pago
  $payment_id = $trx['gateway_trx_id'];
  
  if (!empty($payment_id)) {
    $url = "https://api.mercadopago.com/v1/payments/{$payment_id}";
    $headers = [
      'Authorization: Bearer ' . $config['mercadopago_access_token']
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $headers
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    $payment_data = json_decode($response, true);
    
    // Update status if payment is approved
    if (isset($payment_data['status']) && $payment_data['status'] == 'approved' && $trx['status'] != 2) {
      // Payment approved, activate the package
      if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Mercado Pago PIX')) {
        _log("Mercado Pago PIX Payment Successful, But Failed to activate Package");
      }
      
      _log("Mercado Pago PIX Payment Successful");
      $trx->pg_paid_response = $response;
      $trx->payment_method = 'Mercado Pago';
      $trx->payment_channel = 'PIX';
      $trx->paid_date = date('Y-m-d H:i:s');
      $trx->status = 2;
      $trx->save();
      
      r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment has been confirmed and your package is active."));
      return;
    }
  }
  
  // Get PIX data from the transaction
  $pix_data = json_decode($trx['pg_paid_response'], true);
  $qr_code = isset($pix_data['qr_code']) ? $pix_data['qr_code'] : '';
  $qr_code_base64 = isset($pix_data['qr_code_base64']) ? $pix_data['qr_code_base64'] : '';
  
  // If we have PIX data, show the payment page again
  if (!empty($qr_code) && !empty($qr_code_base64)) {
    $ui->assign('_title', 'PIX Payment - ' . $config['CompanyName']);
    $ui->assign('trx', $trx);
    $ui->assign('user', $user);
    $ui->assign('payment_id', $trx['gateway_trx_id']);
    $ui->assign('qr_code', $qr_code);
    $ui->assign('qr_code_base64', $qr_code_base64);
    $ui->assign('expiration_date', $trx['expired_date']);
    $ui->assign('trx_id', $trx['id']);
    
    $ui->display('mercadopago_pix.tpl');
    exit;
  } else {
    // If we don't have PIX data, create a new transaction
    r2(U . "order/package", 'w', Lang::T("PIX payment information not found. Please create a new order."));
  }
}

