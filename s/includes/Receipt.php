<?php
/**
 * Receipt Class - Handle receipt generation and printing
 */

class Receipt {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Generate receipt text
     */
    public function generate($saleId) {
        // Get sale details
        $stmt = $this->db->prepare("
            SELECT s.*, u.full_name, c.company_name, c.phone as company_phone
            FROM sales s
            JOIN users u ON s.user_id = u.id
            JOIN companies c ON s.company_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            return ['success' => false, 'message' => 'Sale not found'];
        }
        
        // Get sale items
        $stmt = $this->db->prepare("
            SELECT si.*, p.product_name, p.barcode
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        $items = $stmt->fetchAll();
        
        // Build receipt
        $width = RECEIPT_WIDTH;
        $receipt = '';
        $receipt .= str_repeat('=', $width) . "\n";
        $receipt .= $this->center($sale['company_name'], $width) . "\n";
        $receipt .= $this->center($sale['company_phone'], $width) . "\n";
        $receipt .= str_repeat('=', $width) . "\n\n";
        
        $receipt .= "Date: " . date('d/m/Y H:i:s', strtotime($sale['created_at'])) . "\n";
        $receipt .= "Receipt: " . $sale['sale_number'] . "\n";
        $receipt .= "Cashier: " . $sale['full_name'] . "\n";
        $receipt .= str_repeat('-', $width) . "\n\n";
        
        // Items
        $receipt .= $this->padBetween('Item', 'Total', $width - 4) . "\n";
        $receipt .= str_repeat('-', $width) . "\n";
        
        foreach ($items as $item) {
            $itemLine = substr($item['product_name'], 0, 20) . " x" . str_pad($item['quantity'], 3, ' ', STR_PAD_LEFT);
            $priceLine = "KES " . number_format((float)$item['subtotal'], 2);
            $receipt .= $this->padBetween($itemLine, $priceLine, $width - 2) . "\n";
        }
        
        $receipt .= str_repeat('-', $width) . "\n";
        
        // Totals
        $receipt .= $this->padBetween('Subtotal:', "KES " . number_format((float)$sale['subtotal'], 2), $width - 2) . "\n";
        $receipt .= $this->padBetween('Total:', "KES " . number_format((float)$sale['total'], 2), $width - 2) . "\n";
        $receipt .= $this->padBetween('Paid:', "KES " . number_format((float)$sale['amount_paid'], 2), $width - 2) . "\n";
        
        if ($sale['change_amount'] > 0) {
            $receipt .= $this->padBetween('Change:', "KES " . number_format((float)$sale['change_amount'], 2), $width - 2) . "\n";
        }
        
        // Payment method
        if ($sale['payment_method'] === 'mpesa') {
            $receipt .= "\nPayment: M-PESA\n";
            if (!empty($sale['mpesa_receipt'])) {
                $receipt .= "Ref: " . $sale['mpesa_receipt'] . "\n";
            }
        } else {
            $receipt .= "\nPayment: CASH\n";
        }
        
        $receipt .= "\n" . str_repeat('=', $width) . "\n";
        $receipt .= $this->center('Thank you for your purchase', $width) . "\n";
        $receipt .= str_repeat('=', $width) . "\n";
        
        return ['success' => true, 'receipt' => $receipt];
    }
    
    /**
     * Print receipt to thermal printer
     */
    public function print($saleId) {
        $receiptData = $this->generate($saleId);
        
        if (!$receiptData['success']) {
            return $receiptData;
        }
        
        // Print to default printer
        $receiptText = $receiptData['receipt'];
        
        // On Windows, use print command
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Use GDI print to default printer
            // This requires more complex handling
            // For now, just return the receipt text
            return [
                'success' => true,
                'message' => 'Receipt ready for printing',
                'receipt' => $receiptText
            ];
        } else {
            // On Linux/Mac, use lpr
            $tempFile = tempnam(sys_get_temp_dir(), 'receipt_');
            file_put_contents($tempFile, $receiptText);
            exec("lpr $tempFile");
            unlink($tempFile);
            
            return ['success' => true, 'message' => 'Receipt sent to printer'];
        }
    }
    
    /**
     * Generate receipt HTML (alias for getHTML)
     */
    public function generateHTML($saleId) {
        return $this->getHTML($saleId);
    }
    
    /**
     * Get receipt as HTML
     */
    public function getHTML($saleId) {
        $receiptData = $this->generate($saleId);
        
        if (!$receiptData['success']) {
            return $receiptData;
        }
        
        $receipt = $receiptData['receipt'];
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Receipt</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .receipt {
            background: white;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.4;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        @media print {
            body { background: white; }
            .receipt { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <div class='receipt'>$receipt</div>
    <script>
        window.print();
    </script>
</body>
</html>";
        
        return ['success' => true, 'html' => $html];
    }
    
    /**
     * Helper: Center text
     */
    private function center($text, $width) {
        $padding = floor(($width - strlen($text)) / 2);
        return str_repeat(' ', $padding) . $text;
    }
    
    /**
     * Helper: Pad text between left and right
     */
    private function padBetween($left, $right, $width) {
        $padding = $width - strlen($left) - strlen($right);
        return $left . str_repeat(' ', max(0, $padding)) . $right;
    }
    
    /**
     * Save receipt to database
     */
    public function save($saleId, $receiptText) {
        $stmt = $this->db->prepare("
            INSERT INTO receipts (sale_id, receipt_text, printed_at)
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$saleId, $receiptText]);
    }
    
    /**
     * Get receipt by sale ID
     */
    public function getBySaleId($saleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM receipts WHERE sale_id = ?
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch();
    }
}
?>
