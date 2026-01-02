<?php
/**
 * Sale Class - Handle sales operations
 */

class Sale {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Create a new sale
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO sales (
                company_id, sale_number, user_id, subtotal, total,
                amount_paid, change_amount, payment_method, mpesa_phone,
                mpesa_receipt, sale_date, sale_time, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $data['company_id'],
            $data['sale_number'],
            $data['user_id'],
            $data['subtotal'],
            $data['total'],
            $data['amount_paid'],
            $data['change_amount'],
            $data['payment_method'],
            $data['mpesa_phone'] ?? null,
            $data['mpesa_receipt'] ?? null,
            date('Y-m-d'),
            date('H:i:s')
        ]);
    }
    
    /**
     * Get sale by ID
     */
    public function getById($saleId) {
        $stmt = $this->db->prepare("
            SELECT * FROM sales WHERE id = ?
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch();
    }
    
    /**
     * Get last insert ID
     */
    public function getLastId() {
        return $this->db->lastInsertId();
    }
    
    /**
     * Add item to sale
     */
    public function addItem($saleId, $productId, $quantity, $unitPrice, $subtotal) {
        $stmt = $this->db->prepare("
            INSERT INTO sale_items (
                sale_id, product_id, quantity, unit_price, subtotal
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $saleId,
            $productId,
            $quantity,
            $unitPrice,
            $subtotal
        ]);
    }
    
    /**
     * Get sale items
     */
    public function getItems($saleId) {
        $stmt = $this->db->prepare("
            SELECT si.*, p.product_name, p.barcode
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
            ORDER BY si.id
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get daily sales summary
     */
    public function getDailySales($companyId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_sales, SUM(total) as total_amount
            FROM sales
            WHERE company_id = ? AND DATE(sale_date) = CURDATE()
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetch();
    }
}