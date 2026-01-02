-- ================================================================
-- CHAIRMAN POS - MYSQL DATABASE SCHEMA
-- Database: zilla
-- ================================================================

-- Users table (Admin and Cashier)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100),
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product/Items table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2),
    quantity_in_stock INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    unit VARCHAR(20),
    barcode VARCHAR(100),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sales/Transactions table
CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(50) UNIQUE,
    cashier_id INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) DEFAULT 0,
    discount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    paid_amount DECIMAL(10, 2),
    change_amount DECIMAL(10, 2),
    payment_method ENUM('cash', 'mpesa', 'card', 'cheque', 'credit') DEFAULT 'cash',
    transaction_status ENUM('completed', 'pending', 'cancelled', 'refunded', 'mpesa_pending', 'failed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- Sale Items (Line items in each sale)
CREATE TABLE IF NOT EXISTS sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- M-PESA Transactions
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL UNIQUE,
    merchant_request_id VARCHAR(100),
    checkout_request_id VARCHAR(100) UNIQUE,
    phone_number VARCHAR(15) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    mpesa_receipt_number VARCHAR(50),
    status ENUM('pending', 'success', 'failed', 'timeout', 'cancelled') DEFAULT 'pending',
    response_code VARCHAR(10),
    response_description TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);

-- Stock Adjustments
CREATE TABLE IF NOT EXISTS stock_adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    adjustment_type ENUM('purchase', 'damage', 'theft', 'return', 'inventory') DEFAULT 'inventory',
    quantity_adjusted INT NOT NULL,
    reason TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Printer Configuration
CREATE TABLE IF NOT EXISTS printers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    printer_name VARCHAR(150) NOT NULL UNIQUE,
    printer_model VARCHAR(100),
    is_default BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Daily Reports
CREATE TABLE IF NOT EXISTS daily_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_date DATE NOT NULL UNIQUE,
    cashier_id INT,
    total_sales DECIMAL(10, 2),
    total_cash DECIMAL(10, 2),
    total_mpesa DECIMAL(10, 2),
    total_card DECIMAL(10, 2),
    total_discount DECIMAL(10, 2),
    opening_balance DECIMAL(10, 2),
    closing_balance DECIMAL(10, 2),
    total_transactions INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);

-- System Logs
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ================================================================
-- INDEXES FOR PERFORMANCE
-- ================================================================

CREATE INDEX idx_sales_cashier ON sales(cashier_id);
CREATE INDEX idx_sales_date ON sales(created_at);
CREATE INDEX idx_sales_payment ON sales(payment_method);
CREATE INDEX idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX idx_sale_items_product ON sale_items(product_id);
CREATE INDEX idx_mpesa_sale ON mpesa_transactions(sale_id);
CREATE INDEX idx_mpesa_status ON mpesa_transactions(status);
CREATE INDEX idx_mpesa_phone ON mpesa_transactions(phone_number);
CREATE INDEX idx_products_active ON products(is_active);
CREATE INDEX idx_users_active ON users(is_active);
CREATE INDEX idx_users_role ON users(role);

-- ================================================================
-- INITIAL DATA
-- ================================================================

-- Insert default admin user (password: admin123 - MD5 hashed, replace with proper hash in production)
INSERT INTO users (username, password, email, full_name, role, phone, is_active) 
VALUES ('admin', 'admin123', 'admin@chairman.local', 'Administrator', 'admin', '0700000000', 1);

-- Insert sample cashier user
INSERT INTO users (username, password, email, full_name, role, phone, is_active) 
VALUES ('rapid', 'cashier123', 'rapid@chairman.local', 'Rapid Cashier', 'cashier', '0701234567', 1);

-- Insert sample products
INSERT INTO products (code, name, description, category, price, cost_price, quantity_in_stock, unit, is_active) 
VALUES 
('SKU001', 'Water Bottle 500ml', 'Drinking water bottle', 'Beverages', 50.00, 30.00, 100, 'piece', 1),
('SKU002', 'Soft Drink 330ml', 'Coca Cola/Fanta/Sprite', 'Beverages', 80.00, 50.00, 75, 'piece', 1),
('SKU003', 'Bread Loaf', 'White/Brown bread', 'Food', 120.00, 70.00, 50, 'piece', 1),
('SKU004', 'Eggs Dozen', 'Fresh chicken eggs', 'Food', 250.00, 180.00, 30, 'dozen', 1),
('SKU005', 'Cooking Oil 1L', 'Pure cooking oil', 'Food', 200.00, 140.00, 40, 'liter', 1);
