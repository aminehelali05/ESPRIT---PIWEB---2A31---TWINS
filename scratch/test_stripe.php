<?php
require 'vendor/autoload.php';
require 'config.php';
$secretKey = config::get('STRIPE_SECRET_KEY');
echo "Secret Key: " . ($secretKey ? "Found" : "Not Found") . "\n";
if (class_exists('\Stripe\Stripe')) {
    echo "Stripe Class: Found\n";
    \Stripe\Stripe::setApiKey($secretKey);
    try {
        $account = \Stripe\Account::retrieve();
        echo "Stripe Connection: Success (" . $account->id . ")\n";
    } catch (Exception $e) {
        echo "Stripe Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Stripe Class: Not Found\n";
}
