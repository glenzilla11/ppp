<?php
/**
 * ================================================================
 * M-PESA PAYMENT CALLBACK HANDLER
 * ================================================================
 * This endpoint receives callbacks from M-Pesa when payments are made
 * Handler for both: STK Push & Query responses
 * 
 * URL: http://13.57.193.106/api/mpesa_callback.php
 * ================================================================
 */

require_once dirname(__DIR__) . '/config.php';

// Allow callback from M-Pesa without authentication
header('Content-Type: application/json');

// Log all incoming requests
$input = file_get_contents("php://input");
error_log("M-Pesa Callback Received: " . $input);

// Decode JSON payload
$data = json_decode($input, true);

if (!$data) {
    http_response_code(200); // Always return 200 to M-Pesa
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Verify it's a valid callback (basic security)
if (empty($data['Body'])) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Invalid callback structure']);
    exit;
}
}

// Extract the actual result
$body = $data['Body']['stkCallback'] ?? $data['Body'];
$resultCode = $body['ResultCode'] ?? null;
$resultDesc = $body['ResultDesc'] ?? 'Unknown error';

// Extract checkout request ID and merchant request ID
$checkoutRequestID = $body['CheckoutRequestID'] ?? null;
$merchantRequestID = $body['MerchantRequestID'] ?? null;

// Log callback details
error_log("M-Pesa Callback - Result Code: {$resultCode}, Description: {$resultDesc}");

// ================================================================
// HANDLE DIFFERENT RESPONSE CODES
// ================================================================

switch ($resultCode) {
    
    // ============ SUCCESS (0) ============
    case '0':
    case 0:
        handlePaymentSuccess($body);
        break;
    
    // ============ CANCELLED (1032) ============
    case '1032':
    case 1032:
        error_log("M-Pesa Transaction Cancelled: {$checkoutRequestID}");
        // Update transaction status in database if needed
        updateTransactionStatus($checkoutRequestID, 'cancelled');
        break;
    
    // ============ TIMEOUT (1037) ============
    case '1037':
    case 1037:
        error_log("M-Pesa Transaction Timeout: {$checkoutRequestID}");
        updateTransactionStatus($checkoutRequestID, 'timeout');
        break;
    
    // ============ INSUFFICIENT FUNDS (1001) ============
    case '1001':
    case 1001:
        error_log("M-Pesa Insufficient Funds: {$checkoutRequestID}");
        updateTransactionStatus($checkoutRequestID, 'insufficient_funds');
        break;
    
    // ============ OTHER FAILURES ============
    default:
        error_log("M-Pesa Payment Failed ({$resultCode}): {$checkoutRequestID} - {$resultDesc}");
        updateTransactionStatus($checkoutRequestID, 'failed', $resultDesc);
}

// Return success response to M-Pesa (must always return 200 OK)
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Callback received']);
exit;

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Handle successful payment
 */
function handlePaymentSuccess($body) {
    $checkoutRequestID = $body['CheckoutRequestID'] ?? null;
    $merchantRequestID = $body['MerchantRequestID'] ?? null;
    
    // Extract payment details from CallbackMetadata
    $callbackMetadata = $body['CallbackMetadata']['Item'] ?? [];
    $mpesaReceiptNumber = '';
    $transactionDate = '';
    $phoneNumber = '';
    $amount = '';
    
    // Parse callback metadata
    foreach ($callbackMetadata as $item) {
        $name = $item['Name'] ?? '';
        $value = $item['Value'] ?? '';
        
        switch ($name) {
            case 'MpesaReceiptNumber':
                $mpesaReceiptNumber = $value;
                break;
            case 'TransactionDate':
                $transactionDate = $value;
                break;
            case 'PhoneNumber':
                $phoneNumber = $value;
                break;
            case 'Amount':
                $amount = $value;
                break;
        }
    }
    
    error_log("M-Pesa Payment Success - Receipt: {$mpesaReceiptNumber}, Amount: {$amount}, Phone: {$phoneNumber}");
    
    try {
        // Update the mpesa_transactions table with payment confirmation
        $stmt = db()->prepare("
            UPDATE mpesa_transactions 
            SET status = ?, 
                mpesa_receipt = ?,
                transaction_date = NOW(),
                result_code = '0',
                result_desc = 'Success'
            WHERE checkout_request_id = ?
        ");
        $stmt->execute(['completed', $mpesaReceiptNumber, $checkoutRequestID]);
        
        error_log("M-Pesa Transaction confirmed: {$checkoutRequestID} - Receipt: {$mpesaReceiptNumber}");
        
    } catch (Exception $e) {
        error_log("Error processing M-Pesa callback: " . $e->getMessage());
    }
}

/**
 * Update transaction status
 */
function updateTransactionStatus($checkoutRequestID, $status, $reason = '') {
    try {
        $stmt = db()->prepare("
            UPDATE mpesa_payments 
            SET status = ?, reason = ?, updated_at = NOW()
            WHERE checkout_request_id = ?
        ");
        $stmt->execute([$status, $reason, $checkoutRequestID]);
        
        error_log("Transaction status updated: {$checkoutRequestID} -> {$status}");
        
    } catch (Exception $e) {
        error_log("Error updating transaction status: " . $e->getMessage());
    }
}
