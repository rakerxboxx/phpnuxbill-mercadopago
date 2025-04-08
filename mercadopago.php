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
  global $config, $_c;
  
  // Get the payment data from Mercado Pago
  $data = file_get_contents('php://input');
  $notification = json_decode($data, true);
  
  // Log the notification
  $logFile = "MercadoPagoCallback.json";
  $log = fopen($logFile, "a");
  fwrite($log, $data . "\n");
  fwrite($log, "Processing time: " . date('Y-m-d H:i:s') . "\n");
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
    
    // Log the payment data
    $log = fopen($logFile, "a");
    fwrite($log, "Payment data: " . $response . "\n");
    fclose($log);
    
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
          $log = fopen($logFile, "a");
          fwrite($log, "Transaction not found for payment_id: " . $payment_id . "\n");
          fclose($log);
          header("HTTP/1.1 200 OK");
          exit;
        }
      }
      
      // Process based on payment status
      if ($status == 'approved') {
        // Get user details
        $user = ORM::for_table('tbl_customers')
          ->where('username', $trx['username'])
          ->find_one();
          
        if (!$user) {
          $log = fopen($logFile, "a");
          fwrite($log, "User not found for username: " . $trx['username'] . "\n");
          fclose($log);
          header("HTTP/1.1 200 OK");
          exit;
        }
        
        // Get plan details
        $plan = ORM::for_table('tbl_plans')
          ->where('id', $trx['plan_id'])
          ->find_one();
          
        if (!$plan) {
          $log = fopen($logFile, "a");
          fwrite($log, "Plan not found for id: " . $trx['plan_id'] . "\n");
          fclose($log);
          header("HTTP/1.1 200 OK");
          exit;
        }
        
        // Update payment gateway record
        $trx->pg_paid_response = $response;
        $trx->payment_method = 'Mercado Pago';
        $trx->payment_channel = 'PIX';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2; // Set status to paid
        $trx->save();
        
        // Log the transaction update
        $log = fopen($logFile, "a");
        fwrite($log, "Transaction updated: " . $trx['id'] . " to status 2\n");
        fclose($log);
        
        // Check if user already has an active package
        $d = ORM::for_table('tbl_user_recharges')
          ->where('username', $user['username'])
          ->find_one();
          
        // Calculate expiration date based on plan
        $date_now = date("Y-m-d H:i:s");
        $expiration = date("Y-m-d H:i:s", strtotime($date_now . " +" . $plan['validity'] . " " . $plan['validity_unit']));
        
        // If user already has a package, update it
        if ($d) {
          // Check if it's an extension of current package
          if ($d['plan_id'] == $plan['id'] && strtotime($d['expiration']) > strtotime($date_now)) {
            $expiration = date("Y-m-d H:i:s", strtotime($d['expiration'] . " +" . $plan['validity'] . " " . $plan['validity_unit']));
          }
          
          // Update the user recharge record
          $d->plan_id = $plan['id'];
          $d->namebp = $plan['name_plan'];
          $d->recharged_on = $date_now;
          $d->expiration = $expiration;
          $d->time = $plan['validity'];
          $d->status = "on";
          $d->save();
          
          // Log the recharge update
          $log = fopen($logFile, "a");
          fwrite($log, "Updated existing recharge for user: " . $user['username'] . "\n");
          fclose($log);
        } else {
          // Create a new user recharge record
          $d = ORM::for_table('tbl_user_recharges')->create();
          $d->customer_id = $user['id'];
          $d->username = $user['username'];
          $d->plan_id = $plan['id'];
          $d->namebp = $plan['name_plan'];
          $d->recharged_on = $date_now;
          $d->expiration = $expiration;
          $d->time = $plan['validity'];
          $d->status = "on";
          $d->save();
          
          // Log the new recharge
          $log = fopen($logFile, "a");
          fwrite($log, "Created new recharge for user: " . $user['username'] . "\n");
          fclose($log);
        }
        
        // Update user's balance and status
        $user->balance = $user['balance'] + $plan['price'];
        $user->status = "on";
        $user->save();
        
        // Log the user update
        $log = fopen($logFile, "a");
        fwrite($log, "Updated user status and balance: " . $user['username'] . "\n");
        fclose($log);
        
        // Now try to activate the package using the Package class
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Mercado Pago PIX')) {
          _log("Mercado Pago PIX Payment Successful, But Failed to activate Package");
          $log = fopen($logFile, "a");
          fwrite($log, "Failed to activate package via Package::rechargeUser\n");
          fclose($log);
        } else {
          _log("Mercado Pago PIX Payment Successful");
          $log = fopen($logFile, "a");
          fwrite($log, "Successfully activated package via Package::rechargeUser\n");
          fclose($log);
        }
        
        // Send Telegram notification about successful payment
        $message = "💰 *Pagamento PIX Aprovado!*\n";
        $message .= "ID: `".$trx['id']."`\n";
        $message .= "Usuário: `".$trx['username']."`\n";
        $message .= "Plano: `".$trx['plan_name']."`\n";
        $message .= "Valor: `".$_c['currency_code']." ".$trx['price']."`\n";
        $message .= "Data: `".date('d/m/Y H:i:s')."`\n";
        $message .= "Método: Mercado Pago PIX\n";
        $message .= "ID Pagamento: `".$payment_id."`";
        sendTelegram($message);
        
      } else if ($status == 'pending' || $status == 'in_process') {
        // Payment pending, update status
        $trx->pg_paid_response = $response;
        $trx->status = 1;
        $trx->save();
        
        $log = fopen($logFile, "a");
        fwrite($log, "Transaction updated to pending status: " . $trx['id'] . "\n");
        fclose($log);
      } else {
        // Payment failed or rejected
        $trx->pg_paid_response = $response;
        $trx->status = 3; // Failed status
        $trx->save();
        
        $log = fopen($logFile, "a");
        fwrite($log, "Transaction updated to failed status: " . $trx['id'] . "\n");
        fclose($log);
      }
    }
  }
  
  // Always return 200 to acknowledge receipt
  header("HTTP/1.1 200 OK");
  exit;
}


function mercadopago_get_status($trx, $user)
{
  global $config, $ui, $_c;
  
  // First, check the current status in the database
  $current_trx = ORM::for_table('tbl_payment_gateway')
    ->where('id', $trx['id'])
    ->find_one();
    
  // If the transaction is already marked as paid in the database
  if ($current_trx['status'] == 2) {
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment has been confirmed and your package is active."));
    return;
  }
  
  // Check if the transaction has expired
  if (strtotime($trx['expired_date']) < time()) {
    r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Transaction has expired. Please create a new order."));
    return;
  }
  
  // Get payment details from Mercado Pago API to ensure we have the latest status
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
    
    // Log the API check for debugging
    $logFile = "MercadoPagoStatusCheck.json";
    $log = fopen($logFile, "a");
    fwrite($log, "Checking status for payment ID: " . $payment_id . "\n");
    fwrite($log, "Response: " . $response . "\n");
    fwrite($log, "Time: " . date('Y-m-d H:i:s') . "\n");
    fclose($log);
    
    // If payment is approved according to Mercado Pago API
    if (isset($payment_data['status']) && $payment_data['status'] == 'approved') {
      // Get plan details
      $plan = ORM::for_table('tbl_plans')
        ->where('id', $trx['plan_id'])
        ->find_one();
        
      // Update payment gateway record
      $trx->pg_paid_response = $response;
      $trx->payment_method = 'Mercado Pago';
      $trx->payment_channel = 'PIX';
      $trx->paid_date = date('Y-m-d H:i:s');
      $trx->status = 2; // Set status to paid
      $trx->save();
      
      // Calculate expiration date based on plan
      $date_now = date("Y-m-d H:i:s");
      $expiration = date("Y-m-d H:i:s", strtotime($date_now . " +" . $plan['validity'] . " " . $plan['validity_unit']));
      
      // Check if user already has an active package
      $d = ORM::for_table('tbl_user_recharges')
        ->where('username', $user['username'])
        ->find_one();
        
      // If user already has a package, update it
      if ($d) {
        // Check if it's an extension of current package
        if ($d['plan_id'] == $plan['id'] && strtotime($d['expiration']) > strtotime($date_now)) {
          $expiration = date("Y-m-d H:i:s", strtotime($d['expiration'] . " +" . $plan['validity'] . " " . $plan['validity_unit']));
        }
        
        // Update the user recharge record
        $d->plan_id = $plan['id'];
        $d->namebp = $plan['name_plan'];
        $d->recharged_on = $date_now;
        $d->expiration = $expiration;
        $d->time = $plan['validity'];
        $d->status = "on";
        $d->save();
      } else {
        // Create a new user recharge record
        $d = ORM::for_table('tbl_user_recharges')->create();
        $d->customer_id = $user['id'];
        $d->username = $user['username'];
        $d->plan_id = $plan['id'];
        $d->namebp = $plan['name_plan'];
        $d->recharged_on = $date_now;
        $d->expiration = $expiration;
        $d->time = $plan['validity'];
        $d->status = "on";
        $d->save();
      }
      
      // Update user's balance and status
      $user->balance = $user['balance'] + $plan['price'];
      $user->status = "on";
      $user->save();
      
      // Now try to activate the package using the Package class
      if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Mercado Pago PIX')) {
        _log("Mercado Pago PIX Payment Successful, But Failed to activate Package");
      } else {
        _log("Mercado Pago PIX Payment Successful");
      }
      
      // Send Telegram notification about successful payment
      $message = "💰 *Pagamento PIX Aprovado!*\n";
      $message .= "ID: `".$trx['id']."`\n";
      $message .= "Usuário: `".$trx['username']."`\n";
      $message .= "Plano: `".$trx['plan_name']."`\n";
      $message .= "Valor: `".$_c['currency_code']." ".$trx['price']."`\n";
      $message .= "Data: `".date('d/m/Y H:i:s')."`\n";
      $message .= "Método: Mercado Pago PIX\n";
      $message .= "ID Pagamento: `".$payment_id."`";
      sendTelegram($message);
      
      // Redirect to success page
      r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment has been confirmed and your package is active."));
      return;
    }
  }
  
  // If we reach here, the payment is still pending or we couldn't verify it
  // Let's check if we need to show the PIX page or a status page
  
  // Get PIX data from the transaction
  $pix_data = json_decode($trx['pg_paid_response'], true);
  $qr_code = isset($pix_data['qr_code']) ? $pix_data['qr_code'] : '';
  $qr_code_base64 = isset($pix_data['qr_code_base64']) ? $pix_data['qr_code_base64'] : '';
  
  // If we have PIX data, show the payment page with status information
  if (!empty($qr_code) && !empty($qr_code_base64)) {
    // Check if we have payment data in the response
    $payment_status = "pending";
    $payment_message = Lang::T("Your payment is being processed. Please wait or scan the QR code to pay.");
    
    if (isset($payment_data['status'])) {
      switch ($payment_data['status']) {
        case 'pending':
          $payment_status = "pending";
          $payment_message = Lang::T("Your payment is pending. Please complete the payment.");
          break;
        case 'in_process':
          $payment_status = "processing";
          $payment_message = Lang::T("Your payment is being processed. Please wait.");
          break;
        case 'rejected':
          $payment_status = "rejected";
          $payment_message = Lang::T("Your payment was rejected. Please try again.");
          break;
        default:
          $payment_status = "unknown";
          $payment_message = Lang::T("Payment status unknown. Please contact support if you've already paid.");
      }
    }
    
    // Display the PIX payment page with status information
    $ui->assign('_title', 'PIX Payment - ' . $config['CompanyName']);
    $ui->assign('trx', $trx);
    $ui->assign('user', $user);
    $ui->assign('payment_id', $trx['gateway_trx_id']);
    $ui->assign('qr_code', $qr_code);
    $ui->assign('qr_code_base64', $qr_code_base64);
    $ui->assign('expiration_date', $trx['expired_date']);
    $ui->assign('trx_id', $trx['id']);
    $ui->assign('payment_status', $payment_status);
    $ui->assign('payment_message', $payment_message);
    
    $ui->display('mercadopago_pix.tpl');
    exit;
  } else {
    // If we don't have PIX data, create a new transaction
    r2(U . "order/package", 'w', Lang::T("PIX payment information not found. Please create a new order."));
  }
}

