<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Product.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$product = new Product();
$query = $_GET['q'] ?? '';
$results = [];

if (strlen($query) >= 2) {
    $results = $product->search($query, currentUser()['company_id']);
}

jsonResponse(['success' => true, 'results' => $results]);