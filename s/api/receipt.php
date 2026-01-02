<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Receipt.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$saleId = $_GET['sale_id'] ?? '';

if (empty($saleId)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request']);
}

$receipt = new Receipt();
$result = $receipt->getHTML($saleId);

jsonResponse($result);