<?php
// config.php - System Configuration for BakerEase Payment Module

// ToyyibPay API Settings
define('TOYYIBPAY_SECRET_KEY', 'iw0wmu9l-h4yx-o53t-azmd-wn4a0lip1xo9');
define('TOYYIBPAY_CATEGORY_CODE', 'lx01dwlu');

// Staging (Sandbox) endpoints:
define('TOYYIBPAY_API_URL', 'https://dev.toyyibpay.com/index.php/api/');
define('TOYYIBPAY_PAY_URL', 'https://dev.toyyibpay.com/');

// To switch to Production in the future:
// define('TOYYIBPAY_API_URL', 'https://toyyibpay.com/index.php/api/');
// define('TOYYIBPAY_PAY_URL', 'https://toyyibpay.com/');
?>
