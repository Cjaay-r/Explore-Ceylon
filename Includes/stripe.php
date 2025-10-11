<?php
if (!defined('STRIPE_SECRET_KEY')) {
  define('STRIPE_SECRET_KEY', 'sk stripe'); // <-- change me
}


if (!defined('STRIPE_CURRENCY')) {
  define('STRIPE_CURRENCY', 'usd');
}

$__stripe_autoload_ok = false;


$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
  require_once $vendorAutoload;
  $__stripe_autoload_ok = true;
} else {

  $zipInit = __DIR__ . '/../stripe-php/init.php';
  if (file_exists($zipInit)) {
    require_once $zipInit;
    $__stripe_autoload_ok = true;
  }
}

if ($__stripe_autoload_ok) {
  \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
}

function stripe_is_zero_decimal($currency) {
  $zero = ['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf'];
  return in_array(strtolower($currency), $zero, true);
}

function stripe_amount_to_unit($amountMajor, $currency) {
  return stripe_is_zero_decimal($currency) ? (int)round($amountMajor) : (int)round($amountMajor * 100);
}


function stripe_create_checkout_session($bookingId, $amountMajorUnits, $customerEmail, $description, $successUrl, $cancelUrl) {
  if (!class_exists(\Stripe\Checkout\Session::class)) {
    throw new \RuntimeException('Stripe library not loaded.');
  }

  foreach ([$successUrl, $cancelUrl] as $u) {
    if (!is_string($u) || !preg_match('~^https?://~i', $u)) {
      throw new \InvalidArgumentException('Not a valid URL');
    }
  }

  $cur = STRIPE_CURRENCY;
  $unitAmount = stripe_amount_to_unit($amountMajorUnits, $cur);

  return \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'customer_email' => $customerEmail ?: null,
    'line_items' => [[
      'price_data' => [
        'currency' => $cur,
        'product_data' => [
          'name' => "Booking #{$bookingId}",
          'description' => $description ?: 'Travel booking',
        ],
        'unit_amount' => $unitAmount,
      ],
      'quantity' => 1,
    ]],
    'metadata' => [
      'booking_id' => (string)$bookingId,
    ],
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
  ]);
}
