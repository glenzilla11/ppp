<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Product.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$product = new Product();
$barcode = $_GET['code'] ?? '';
$result = $product->getByBarcode($barcode, currentUser()['company_id']);

if (!$result) {
    jsonResponse(['success' => false, 'message' => 'Product not found']);
}

jsonResponse(['success' => true, 'product' => $result]);