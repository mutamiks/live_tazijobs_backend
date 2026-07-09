<?php

return [
    'enabled' => filter_var(env('SMS_ENABLED', env('SEND_SMS', false)), FILTER_VALIDATE_BOOL),
    'username' => env('SMS_USERNAME'),
    'password' => env('SMS_PASSWORD'),
    'sender' => env('SMS_SENDER', 'TaziJobs'),
    'gateway_url' => env('SMS_URL'),
    'topup_url' => env('SMS_TOPUP_URL'),
    'reseller_username' => env('SMS_RESELLER_USERNAME', env('SMS_MUTALINK_USERNAME')),
    'reseller_password' => env('SMS_RESELLER_PASSWORD', env('SMS_MUTALINK_PASSWORD')),
    'reseller_phone' => env('SMS_RESELLER_PHONE', env('SMS_MUTALINK_PHONE')),
    'rate' => (float) env('SMS_RATE', 35),
    'message_length' => (int) env('SMS_MSG_LENGTH', 160),
    'payment_url' => env('SMS_PAYMENT_URL', env('PAYMENTS_URL_yo')),
    'payment_username' => env('SMS_PAYMENT_USERNAME', env('PAYMENTS_USERNAME_yo')),
    'payment_password' => env('SMS_PAYMENT_PASSWORD', env('PAYMENTS_PASSWORD_yo')),
    'payment_method' => env('SMS_PAYMENT_METHOD', env('PAYMENTS_SEND_MONEY_yo', 'acdepositfunds')),
    'payment_status_method' => env('SMS_PAYMENT_STATUS_METHOD', env('PAYMENTS_PAYMENT_STATUS_yo', 'actransactioncheckstatus')),
    'payment_currency' => env('SMS_PAYMENT_CURRENCY', env('PAYMENTS_CURRENCY_yo', 'UGX')),
    'max_payment_attempts' => (int) env('SMS_PAYMENT_MAX_ATTEMPTS', 24),
];
