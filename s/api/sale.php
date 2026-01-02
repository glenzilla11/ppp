<?php
// Start output buffering to catch any unexpected output
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Sale.php';
require_once __DIR__ . '/../includes/Product.php';
require_once __DIR__ . '/../includes/Receipt.php';

// Clear any output from includes and set JSON header
ob_clean();
header('Content-Type: application/json');

try {
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);
    }
    
    $user = currentUser();

    $saleData = [
        'company_id' => $user['company_id'],
        'user_id' => $user['id'],
        'sale_number' => generateSaleNumber($user['company_id']),
        'subtotal' => $data['subtotal'] ?? 0,
        'total' => $data['total'] ?? 0,
        'amount_paid' => $data['amount_paid'] ?? 0,
        'change_amount' => $data['change_amount'] ?? 0,
        'payment_method' => $data['payment_method'] ?? 'cash',
        'mpesa_phone' => $data['mpesa_phone'] ?? null,
        'mpesa_receipt' => $data['mpesa_receipt'] ?? null,
    ];

    $sale = new Sale();
    $product = new Product();

    // Start transaction
    db()->beginTransaction();
    
    // Create sale
    $sale->create($saleData);
    $saleId = $sale->getLastId();
    
    // Add items and deduct stock
    if (!is_array($data['items']) || empty($data['items'])) {
        throw new Exception('No items in sale');
    }
    
    foreach ($data['items'] as $item) {
        $itemQuantity = $item['quantity'] ?? 0;
        $itemPrice = $item['price'] ?? 0;
        $itemSubtotal = $itemQuantity * $itemPrice;
        $productId = $item['id'] ?? $item['product_id'] ?? null;
        
        if (!$productId || $itemQuantity <= 0 || $itemPrice <= 0) {
            throw new Exception('Invalid item data');
        }
        
        $sale->addItem(
            $saleId,
            $productId,
            $itemQuantity,
            $itemPrice,
            $itemSubtotal
        );
        
        // Deduct stock
        if (!$product->decreaseStock($productId, $itemQuantity, $user['company_id'])) {
            throw new Exception('Failed to deduct stock for product ID ' . $productId);
        }
    }
    
    db()->commit();
    
    // Generate receipt
    $receipt = new Receipt();
    $receiptHtml = $receipt->generateHTML($saleId);
    
    jsonResponse([
        'success' => true,
        'sale_id' => $saleId,
        'sale_number' => $saleData['sale_number'],
        'receipt_html' => $receiptHtml,
        'data' => [
            'sale_number' => $saleData['sale_number']
        ],
        'message' => 'Sale completed successfully'
    ]);
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}