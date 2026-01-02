<?php
/**
 * Product Class - Handle product operations
 */

class Product {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Get all active products for a company
     */
    public function getAll($companyId) {
        $stmt = $this->db->prepare("
            SELECT id, barcode, product_name, description, unit, 
                   buying_price, selling_price, stock_quantity, 
                   is_active, created_at
            FROM products
            WHERE company_id = ? AND is_active = 1
            ORDER BY product_name ASC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get product by barcode
     */
    public function getByBarcode($barcode, $companyId) {
        $stmt = $this->db->prepare("
            SELECT id, barcode, product_name, description, unit,
                   buying_price, selling_price, stock_quantity,
                   is_active
            FROM products
            WHERE barcode = ? AND company_id = ? AND is_active = 1
        ");
        $stmt->execute([$barcode, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Get product by ID
     */
    public function getById($productId, $companyId) {
        $stmt = $this->db->prepare("
            SELECT id, barcode, product_name, description, unit,
                   buying_price, selling_price, stock_quantity,
                   is_active
            FROM products
            WHERE id = ? AND company_id = ? AND is_active = 1
        ");
        $stmt->execute([$productId, $companyId]);
        return $stmt->fetch();
    }
    
    /**
     * Search products by name or barcode
     */
    public function search($keyword, $companyId) {
        $search = "%$keyword%";
        $stmt = $this->db->prepare("
            SELECT id, barcode, product_name, description, unit,
                   selling_price, stock_quantity, is_active
            FROM products
            WHERE company_id = ? AND is_active = 1
            AND (product_name LIKE ? OR barcode LIKE ?)
            ORDER BY product_name ASC
            LIMIT 20
        ");
        $stmt->execute([$companyId, $search, $search]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get low stock products
     */
    public function getLowStock($companyId, $threshold = 5) {
        $stmt = $this->db->prepare("
            SELECT id, product_name, barcode, stock_quantity
            FROM products
            WHERE company_id = ? AND is_active = 1
            AND stock_quantity <= ?
            ORDER BY stock_quantity ASC
        ");
        $stmt->execute([$companyId, $threshold]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update stock quantity
     */
    public function updateStock($productId, $newQuantity, $companyId) {
        $stmt = $this->db->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$newQuantity, $productId, $companyId]);
    }
    
    /**
     * Decrease stock (used during sale)
     */
    public function decreaseStock($productId, $quantity, $companyId) {
        $stmt = $this->db->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE id = ? AND company_id = ? AND stock_quantity >= ?
        ");
        return $stmt->execute([$quantity, $productId, $companyId, $quantity]);
    }
    
    /**
     * Create new product
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                company_id, barcode, product_name, description,
                unit, buying_price, selling_price, stock_quantity,
                is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        return $stmt->execute([
            $data['company_id'],
            $data['barcode'],
            $data['product_name'],
            $data['description'] ?? null,
            $data['unit'] ?? 'piece',
            $data['buying_price'],
            $data['selling_price'],
            $data['stock_quantity'] ?? 0
        ]);
    }
    
    /**
     * Update product
     */
    public function update($productId, $data, $companyId) {
        $stmt = $this->db->prepare("
            UPDATE products
            SET product_name = ?, description = ?, unit = ?,
                buying_price = ?, selling_price = ?, stock_quantity = ?
            WHERE id = ? AND company_id = ?
        ");
        
        return $stmt->execute([
            $data['product_name'],
            $data['description'] ?? null,
            $data['unit'] ?? 'piece',
            $data['buying_price'],
            $data['selling_price'],
            $data['stock_quantity'] ?? 0,
            $productId,
            $companyId
        ]);
    }
    
    /**
     * Delete product (soft delete)
     */
    public function delete($productId, $companyId) {
        $stmt = $this->db->prepare("
            UPDATE products
            SET is_active = 0
            WHERE id = ? AND company_id = ?
        ");
        return $stmt->execute([$productId, $companyId]);
    }
    
    /**
     * Get product count for company
     */
    public function getCount($companyId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM products
            WHERE company_id = ? AND is_active = 1
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetch()['total'];
    }
    
    /**
     * Get total stock value
     */
    public function getStockValue($companyId) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(stock_quantity * buying_price), 0) as total_value
            FROM products
            WHERE company_id = ? AND is_active = 1
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetch()['total_value'];
    }
}
?>
