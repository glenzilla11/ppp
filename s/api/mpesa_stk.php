<?php
ob_start();
require_once __DIR__ . '/../config.php';
ob_clean();
header('Content-Type: application/json');

// ================================================================
// ENFORCE RATE LIMITING ON MPESA REQUESTS
// ================================================================
enforceRateLimit('mpesa');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$phone = $data['phone'] ?? '';
$amount = $data['amount'] ?? 0;

// Clean phone number
$phone = preg_replace('/[^0-9]/', '', $phone);

// Validate phone
if (!preg_match('/^(254|0)(7|1)\d{8}$/', $phone)) {
    jsonResponse(['success' => false, 'message' => 'Invalid phone number. Use format: 07XXXXXXXX']);
}

// Validate amount
if ($amount < 1) {
    jsonResponse(['success' => false, 'message' => 'Amount must be at least KES 1']);
}

// Send STK Push
$result = MpesaAPI::stkPush($phone, $amount);

if (!$result['success']) {
    jsonResponse([
        'success' => false, 
        'message' => $result['message'] ?? $result['response_description'] ?? 'Failed to send STK push'
    ]);
}

// Store in database for tracking
try {
    $stmt = db()->prepare("
        INSERT INTO mpesa_transactions (
            company_id, checkout_request_id, phone_number, amount, status, created_at
        ) VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        currentUser()['company_id'],
        $result['checkout_request_id'],
        $phone,
        $amount
    ]);
} catch (Exception $e) {
    error_log("M-Pesa DB Error: " . $e->getMessage());
}

jsonResponse([
    'success' => true,
    'checkout_request_id' => $result['checkout_request_id'],
    'message' => 'STK push sent. Check your phone.'
]);