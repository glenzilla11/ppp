<?php
ob_start();
require_once __DIR__ . '/../config.php';
ob_clean();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$checkoutRequestId = $_GET['checkout_request_id'] ?? $_GET['checkout_id'] ?? '';

if (empty($checkoutRequestId)) {
    jsonResponse(['success' => false, 'message' => 'Missing checkout request ID']);
}

// First, check local database
try {
    $stmt = db()->prepare("
        SELECT status, mpesa_receipt, result_code, result_desc 
        FROM mpesa_transactions 
        WHERE checkout_request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$checkoutRequestId]);
    $transaction = $stmt->fetch();
    
    if ($transaction) {
        if ($transaction['status'] === 'completed') {
            jsonResponse([
                'success' => true,
                'status' => 'completed',
                'mpesa_receipt' => $transaction['mpesa_receipt']
            ]);
        } elseif ($transaction['status'] === 'failed' || $transaction['status'] === 'cancelled') {
            jsonResponse([
                'success' => false,
                'status' => $transaction['status'],
                'message' => $transaction['result_desc'] ?? 'Payment not successful'
            ]);
        }
    }
} catch (Exception $e) {
    error_log("M-Pesa Status DB Error: " . $e->getMessage());
}

// If not in DB yet, query M-Pesa API
$result = MpesaAPI::checkStatus($checkoutRequestId);

// If successful from API, save to DB
if ($result['success'] && $result['status'] === 'completed') {
    try {
        $stmt = db()->prepare("
            UPDATE mpesa_transactions 
            SET status = ?, mpesa_receipt = ?, result_code = '0', result_desc = 'Success', updated_at = NOW()
            WHERE checkout_request_id = ?
        ");
        $stmt->execute(['completed', $result['mpesa_receipt'] ?? '', $checkoutRequestId]);
    } catch (Exception $e) {
        error_log("M-Pesa Status DB Update Error: " . $e->getMessage());
    }
}

jsonResponse($result);