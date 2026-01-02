<?php
/**
 * ================================================================
 * CHAIRMAN POS - Admin Dashboard (Complete Working Version)
 * ================================================================
 * Developer: Glen
 * Contact: +254735065427
 * ================================================================
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and functions from config.php
require_once dirname(__DIR__) . '/config.php';

// Check authentication
if (!isLoggedIn()) {
    redirect('index.php');
}

// Check role
if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'superadmin'])) {
    redirect('index.php?page=cashier');
}

$companyId = $_SESSION['company_id'];

// ================================================================
// AJAX ACTION HANDLERS
// ================================================================
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        
        // ============ DASHBOARD STATS ============
        case 'dashboard_stats':
            $today = date('Y-m-d');
            
            // Today's sales
            $stmt = db()->prepare("
                SELECT COUNT(*) as total_sales, COALESCE(SUM(total), 0) as total_amount,
                       COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total,
                       COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa_total
                FROM sales WHERE company_id = ? AND sale_date = ? AND status = 'completed'
            ");
            $stmt->execute([$companyId, $today]);
            $salesStats = $stmt->fetch();
            
            // Products count
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND is_active = 1");
            $stmt->execute([$companyId]);
            $productsCount = $stmt->fetch()['total'];
            
            // Low stock count
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM products WHERE company_id = ? AND is_active = 1 AND stock_quantity <= 5");
            $stmt->execute([$companyId]);
            $lowStockCount = $stmt->fetch()['total'];
            
            // Users count
            $stmt = db()->prepare("SELECT COUNT(*) as total FROM users WHERE company_id = ? AND is_active = 1");
            $stmt->execute([$companyId]);
            $usersCount = $stmt->fetch()['total'];
            
            // This month sales
            $monthStart = date('Y-m-01');
            $stmt = db()->prepare("
                SELECT COUNT(*) as total_sales, COALESCE(SUM(total), 0) as total_amount
                FROM sales WHERE company_id = ? AND sale_date >= ? AND status = 'completed'
            ");
            $stmt->execute([$companyId, $monthStart]);
            $monthStats = $stmt->fetch();
            
            jsonResponse([
                'today_sales' => $salesStats['total_sales'],
                'today_amount' => $salesStats['total_amount'],
                'cash_total' => $salesStats['cash_total'],
                'mpesa_total' => $salesStats['mpesa_total'],
                'products_count' => $productsCount,
                'low_stock_count' => $lowStockCount,
                'users_count' => $usersCount,
                'month_sales' => $monthStats['total_sales'],
                'month_amount' => $monthStats['total_amount']
            ]);
            break;
        
        // ============ RECENT SALES ============
        case 'recent_sales':
            $stmt = db()->prepare("
                SELECT s.*, u.full_name as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.company_id = ?
                ORDER BY s.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$companyId]);
            jsonResponse($stmt->fetchAll());
            break;
        
        // ============ GET ALL PRODUCTS ============
        case 'get_products':
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? 'all';
            
            $sql = "SELECT * FROM products WHERE company_id = ?";
            $params = [$companyId];
            
            if ($search) {
                $sql .= " AND (product_name LIKE ? OR barcode LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($status === 'active') {
                $sql .= " AND is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            } elseif ($status === 'low_stock') {
                $sql .= " AND stock_quantity <= 5 AND is_active = 1";
            }
            
            $sql .= " ORDER BY product_name ASC";
            
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            jsonResponse($stmt->fetchAll());
            break;
        
        // ============ GET SINGLE PRODUCT ============
        case 'get_product':
            $id = $_GET['id'] ?? 0;
            $stmt = db()->prepare("SELECT * FROM products WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $companyId]);
            $product = $stmt->fetch();
            
            if ($product) {
                jsonResponse(['success' => true, 'product' => $product]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Product not found']);
            }
            break;
        
        // ============ ADD PRODUCT ============
        case 'add_product':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $name = trim($input['product_name'] ?? '');
            $barcode = trim($input['barcode'] ?? '');
            $unit = trim($input['unit'] ?? 'pc');
            $buyingPrice = floatval($input['buying_price'] ?? 0);
            $sellingPrice = floatval($input['selling_price'] ?? 0);
            $stockQty = floatval($input['stock_quantity'] ?? 0);
            
            if (empty($name)) {
                jsonResponse(['success' => false, 'message' => 'Product name is required']);
            }
            
            // Check if barcode exists
            if ($barcode) {
                $stmt = db()->prepare("SELECT id FROM products WHERE barcode = ? AND company_id = ?");
                $stmt->execute([$barcode, $companyId]);
                if ($stmt->fetch()) {
                    jsonResponse(['success' => false, 'message' => 'Barcode already exists']);
                }
            }
            
            try {
                $stmt = db()->prepare("
                    INSERT INTO products (company_id, barcode, product_name, unit, buying_price, selling_price, stock_quantity, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$companyId, $barcode, $name, $unit, $buyingPrice, $sellingPrice, $stockQty]);
                
                jsonResponse(['success' => true, 'message' => 'Product added successfully', 'id' => db()->lastInsertId()]);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error adding product: ' . $e->getMessage()]);
            }
            break;
        
        // ============ UPDATE PRODUCT ============
        case 'update_product':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $id = intval($input['id'] ?? 0);
            $name = trim($input['product_name'] ?? '');
            $barcode = trim($input['barcode'] ?? '');
            $unit = trim($input['unit'] ?? 'pc');
            $buyingPrice = floatval($input['buying_price'] ?? 0);
            $sellingPrice = floatval($input['selling_price'] ?? 0);
            $stockQty = floatval($input['stock_quantity'] ?? 0);
            $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;
            
            if (!$id || empty($name)) {
                jsonResponse(['success' => false, 'message' => 'Product ID and name are required']);
            }
            
            // Check if barcode exists for another product
            if ($barcode) {
                $stmt = db()->prepare("SELECT id FROM products WHERE barcode = ? AND company_id = ? AND id != ?");
                $stmt->execute([$barcode, $companyId, $id]);
                if ($stmt->fetch()) {
                    jsonResponse(['success' => false, 'message' => 'Barcode already exists for another product']);
                }
            }
            
            try {
                $stmt = db()->prepare("
                    UPDATE products 
                    SET product_name = ?, barcode = ?, unit = ?, buying_price = ?, selling_price = ?, stock_quantity = ?, is_active = ?
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$name, $barcode, $unit, $buyingPrice, $sellingPrice, $stockQty, $isActive, $id, $companyId]);
                
                jsonResponse(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error updating product: ' . $e->getMessage()]);
            }
            break;
        
        // ============ DELETE PRODUCT ============
        case 'delete_product':
            $id = $_GET['id'] ?? 0;
            
            try {
                // Soft delete (set inactive)
                $stmt = db()->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND company_id = ?");
                $stmt->execute([$id, $companyId]);
                
                jsonResponse(['success' => true, 'message' => 'Product deleted successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error deleting product: ' . $e->getMessage()]);
            }
            break;
        
        // ============ BULK UPDATE PRICES ============
        case 'bulk_update_prices':
            $input = json_decode(file_get_contents('php://input'), true);
            $products = $input['products'] ?? [];
            
            if (empty($products)) {
                jsonResponse(['success' => false, 'message' => 'No products to update']);
            }
            
            try {
                db()->beginTransaction();
                
                $stmt = db()->prepare("UPDATE products SET buying_price = ?, selling_price = ? WHERE id = ? AND company_id = ?");
                
                foreach ($products as $p) {
                    $stmt->execute([
                        floatval($p['buying_price']),
                        floatval($p['selling_price']),
                        intval($p['id']),
                        $companyId
                    ]);
                }
                
                db()->commit();
                jsonResponse(['success' => true, 'message' => 'Prices updated successfully']);
            } catch (Exception $e) {
                db()->rollBack();
                jsonResponse(['success' => false, 'message' => 'Error updating prices: ' . $e->getMessage()]);
            }
            break;
        
        // ============ ADJUST STOCK ============
        case 'adjust_stock':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $productId = intval($input['product_id'] ?? 0);
            $adjustment = floatval($input['adjustment'] ?? 0);
            $reason = trim($input['reason'] ?? '');
            
            if (!$productId) {
                jsonResponse(['success' => false, 'message' => 'Product ID is required']);
            }
            
            try {
                $stmt = db()->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$adjustment, $productId, $companyId]);
                
                // Log the adjustment (you can create a stock_adjustments table for this)
                
                jsonResponse(['success' => true, 'message' => 'Stock adjusted successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error adjusting stock: ' . $e->getMessage()]);
            }
            break;
        
        // ============ GET SALES REPORT ============
        case 'get_sales':
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $paymentMethod = $_GET['payment_method'] ?? 'all';
            
            $sql = "
                SELECT s.*, u.full_name as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.company_id = ? AND s.sale_date BETWEEN ? AND ?
            ";
            $params = [$companyId, $dateFrom, $dateTo];
            
            if ($paymentMethod !== 'all') {
                $sql .= " AND s.payment_method = ?";
                $params[] = $paymentMethod;
            }
            
            $sql .= " ORDER BY s.created_at DESC";
            
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $sales = $stmt->fetchAll();
            
            // Calculate totals
            $totalAmount = 0;
            $totalCash = 0;
            $totalMpesa = 0;
            
            foreach ($sales as $sale) {
                $totalAmount += $sale['total'];
                if ($sale['payment_method'] === 'cash') $totalCash += $sale['total'];
                if ($sale['payment_method'] === 'mpesa') $totalMpesa += $sale['total'];
            }
            
            jsonResponse([
                'sales' => $sales,
                'summary' => [
                    'total_sales' => count($sales),
                    'total_amount' => $totalAmount,
                    'cash_total' => $totalCash,
                    'mpesa_total' => $totalMpesa
                ]
            ]);
            break;
        
        // ============ GET SALE DETAILS ============
        case 'get_sale_details':
            $saleId = $_GET['id'] ?? 0;
            
            $stmt = db()->prepare("
                SELECT s.*, u.full_name as cashier_name, c.company_name
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN companies c ON s.company_id = c.id
                WHERE s.id = ? AND s.company_id = ?
            ");
            $stmt->execute([$saleId, $companyId]);
            $sale = $stmt->fetch();
            
            if (!$sale) {
                jsonResponse(['success' => false, 'message' => 'Sale not found']);
            }
            
            $stmt = db()->prepare("
                SELECT si.*, p.product_name, p.barcode
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ?
            ");
            $stmt->execute([$saleId]);
            $items = $stmt->fetchAll();
            
            jsonResponse(['success' => true, 'sale' => $sale, 'items' => $items]);
            break;
        
        // ============ GET USERS ============
        case 'get_users':
            $stmt = db()->prepare("SELECT id, email, full_name, role, is_active, last_login, created_at FROM users WHERE company_id = ? ORDER BY full_name");
            $stmt->execute([$companyId]);
            jsonResponse($stmt->fetchAll());
            break;
        
        // ============ ADD USER ============
        case 'add_user':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $email = trim($input['email'] ?? '');
            $fullName = trim($input['full_name'] ?? '');
            $role = trim($input['role'] ?? 'cashier');
            $pin = trim($input['pin'] ?? '');
            
            if (empty($email) || empty($fullName) || empty($pin)) {
                jsonResponse(['success' => false, 'message' => 'Email, name, and PIN are required']);
            }
            
            // Validate role
            if (!in_array($role, ['admin', 'manager', 'cashier'])) {
                $role = 'cashier';
            }
            
            try {
                $stmt = db()->prepare("
                    INSERT INTO users (company_id, email, full_name, role, pin, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$companyId, $email, $fullName, $role, $pin]);
                
                jsonResponse(['success' => true, 'message' => 'User added successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error adding user: ' . $e->getMessage()]);
            }
            break;
        
        // ============ UPDATE USER ============
        case 'update_user':
            $input = json_decode(file_get_contents('php://input'), true);
            
            $id = intval($input['id'] ?? 0);
            $fullName = trim($input['full_name'] ?? '');
            $role = trim($input['role'] ?? 'cashier');
            $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;
            $newPin = trim($input['pin'] ?? '');
            
            if (!$id || empty($fullName)) {
                jsonResponse(['success' => false, 'message' => 'User ID and name are required']);
            }
            
            try {
                if ($newPin) {
                    $stmt = db()->prepare("UPDATE users SET full_name = ?, role = ?, is_active = ?, pin = ? WHERE id = ? AND company_id = ?");
                    $stmt->execute([$fullName, $role, $isActive, $newPin, $id, $companyId]);
                } else {
                    $stmt = db()->prepare("UPDATE users SET full_name = ?, role = ?, is_active = ? WHERE id = ? AND company_id = ?");
                    $stmt->execute([$fullName, $role, $isActive, $id, $companyId]);
                }
                
                jsonResponse(['success' => true, 'message' => 'User updated successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
            }
            break;
        
        // ============ DELETE USER ============
        case 'delete_user':
            $id = $_GET['id'] ?? 0;
            
            // Don't allow deleting yourself
            if ($id == $_SESSION['user_id']) {
                jsonResponse(['success' => false, 'message' => 'Cannot delete your own account']);
            }
            
            try {
                $stmt = db()->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND company_id = ?");
                $stmt->execute([$id, $companyId]);
                
                jsonResponse(['success' => true, 'message' => 'User deactivated successfully']);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;
        
        // ============ EXPORT SALES CSV ============
        case 'export_sales':
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = db()->prepare("
                SELECT s.sale_number, s.sale_date, s.sale_time, s.subtotal, s.total, 
                       s.amount_paid, s.change_amount, s.payment_method, u.full_name as cashier
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.company_id = ? AND s.sale_date BETWEEN ? AND ?
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$companyId, $dateFrom, $dateTo]);
            $sales = $stmt->fetchAll();
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="sales_' . $dateFrom . '_to_' . $dateTo . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Receipt #', 'Date', 'Time', 'Subtotal', 'Total', 'Paid', 'Change', 'Method', 'Cashier']);
            
            foreach ($sales as $sale) {
                fputcsv($output, [
                    $sale['sale_number'],
                    $sale['sale_date'],
                    $sale['sale_time'],
                    $sale['subtotal'],
                    $sale['total'],
                    $sale['amount_paid'],
                    $sale['change_amount'],
                    $sale['payment_method'],
                    $sale['cashier']
                ]);
            }
            
            fclose($output);
            exit();
            break;
        
        // ============ LOGOUT ============
        case 'logout':
            session_destroy();
            jsonResponse(['success' => true]);
            break;
        
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
    
    exit();
}

// Get initial data for the page
$companyName = $_SESSION['company_name'];
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Chairman POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-primary: #f0f4f8;
            --bg-secondary: #ffffff;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #cbd5e1;
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --header-bg: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #475569;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.3);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: var(--header-bg);
            color: white;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: var(--shadow-lg);
        }
        
        .header-left { display: flex; align-items: center; gap: 20px; }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
        }
        
        .logo i {
            font-size: 24px;
            color: #60a5fa;
        }
        
        .header-center h3 {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .theme-btn, .header-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .theme-btn:hover, .header-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .btn-logout {
            padding: 8px 16px;
            background: rgba(239,68,68,0.2);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 8px;
            color: #fca5a5;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-logout:hover {
            background: var(--danger);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-top: 60px;
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        
        /* Navigation Tabs */
        .admin-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: var(--bg-secondary);
            padding: 10px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
        }
        
        .nav-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }
        
        .nav-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-btn.pos-btn {
            background: var(--success);
            color: white;
            margin-left: auto;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .stat-icon.primary { background: rgba(59,130,246,0.15); color: var(--primary); }
        .stat-icon.success { background: rgba(16,185,129,0.15); color: var(--success); }
        .stat-icon.warning { background: rgba(245,158,11,0.15); color: var(--warning); }
        .stat-icon.danger { background: rgba(239,68,68,0.15); color: var(--danger); }
        .stat-icon.info { background: rgba(6,182,212,0.15); color: var(--info); }
        
        .stat-content h4 {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }
        
        /* Cards */
        .card {
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { opacity: 0.9; }
        
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { opacity: 0.9; }
        
        .btn-warning { background: var(--warning); color: white; }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: var(--bg-tertiary);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }
        
        .data-table tr:hover {
            background: var(--bg-tertiary);
        }
        
        .data-table td {
            font-size: 14px;
        }
        
        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success { background: rgba(16,185,129,0.15); color: var(--success); }
        .badge-danger { background: rgba(239,68,68,0.15); color: var(--danger); }
        .badge-warning { background: rgba(245,158,11,0.15); color: var(--warning); }
        .badge-info { background: rgba(6,182,212,0.15); color: var(--info); }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text-secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        /* Search and Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: var(--bg-secondary);
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalIn 0.3s ease;
        }
        
        .modal-lg { max-width: 800px; }
        
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Price input styling */
        .price-input {
            font-family: 'JetBrains Mono', monospace;
            text-align: right;
        }
        
        /* Action buttons in table */
        .action-btns {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .action-btn.edit {
            background: rgba(59,130,246,0.15);
            color: var(--primary);
        }
        
        .action-btn.delete {
            background: rgba(239,68,68,0.15);
            color: var(--danger);
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Loading state */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            min-width: 280px;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--danger); }
        .toast.warning { border-left: 4px solid var(--warning); }
        
        .toast i {
            font-size: 20px;
        }
        
        .toast.success i { color: var(--success); }
        .toast.error i { color: var(--danger); }
        .toast.warning i { color: var(--warning); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 0 15px;
            }
            
            .header-center {
                display: none;
            }
            
            .admin-nav {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-tertiary); }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <span>Admin Dashboard</span>
            </div>
        </div>
        
        <div class="header-center">
            <h3><?php echo htmlspecialchars($companyName); ?></h3>
        </div>
        
        <div class="header-right">
            <span class="user-info">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($userName); ?>
                <span style="opacity:0.7;">(<?php echo ucfirst($userRole); ?>)</span>
            </span>
            <button class="theme-btn" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fas fa-sun" id="themeIcon"></i>
            </button>
            <button class="btn-logout" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </button>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Navigation -->
        <div class="admin-nav">
            <button class="nav-btn active" onclick="showTab('dashboard', this)">
                <i class="fas fa-chart-line"></i> Dashboard
            </button>
            <button class="nav-btn" onclick="showTab('products', this)">
                <i class="fas fa-box"></i> Products
            </button>
            <button class="nav-btn" onclick="showTab('sales', this)">
                <i class="fas fa-receipt"></i> Sales
            </button>
            <button class="nav-btn" onclick="showTab('users', this)">
                <i class="fas fa-users"></i> Users
            </button>
            <button class="nav-btn pos-btn" onclick="goToPOS()">
                <i class="fas fa-cash-register"></i> Go to POS
            </button>
        </div>
        
        <!-- Dashboard Tab -->
        <div class="tab-content active" id="dashboardTab">
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-content">
                        <h4>Today's Sales</h4>
                        <p class="stat-value" id="statTodaySales">-</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-content">
                        <h4>Today's Revenue</h4>
                        <p class="stat-value" id="statTodayAmount">-</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info"><i class="fas fa-box"></i></div>
                    <div class="stat-content">
                        <h4>Total Products</h4>
                        <p class="stat-value" id="statProducts">-</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-content">
                        <h4>Low Stock Items</h4>
                        <p class="stat-value" id="statLowStock">-</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-coins"></i></div>
                    <div class="stat-content">
                        <h4>Cash Sales</h4>
                        <p class="stat-value" id="statCash">-</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-mobile-alt"></i></div>
                    <div class="stat-content">
                        <h4>M-Pesa Sales</h4>
                        <p class="stat-value" id="statMpesa">-</p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Sales</h3>
                    <button class="btn btn-secondary btn-sm" onclick="loadDashboard()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Cashier</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentSalesBody">
                            <tr><td colspan="6" class="loading"><div class="spinner"></div>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Products Tab -->
        <div class="tab-content" id="productsTab">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-box"></i> Products Management</h3>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-success" onclick="openAddProductModal()">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                        <button class="btn btn-secondary" onclick="loadProducts()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="filter-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="productSearch" placeholder="Search products..." oninput="filterProducts()">
                        </div>
                        <select class="form-control" style="width: auto;" id="productFilter" onchange="loadProducts()">
                            <option value="all">All Products</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive</option>
                            <option value="low_stock">Low Stock</option>
                        </select>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Barcode</th>
                                    <th>Unit</th>
                                    <th>Buying Price</th>
                                    <th>Selling Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productsBody">
                                <tr><td colspan="8" class="loading"><div class="spinner"></div>Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Tab -->
        <div class="tab-content" id="salesTab">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-receipt"></i> Sales Report</h3>
                    <button class="btn btn-success" onclick="exportSales()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                <div class="card-body">
                    <div class="filter-bar">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="salesDateFrom" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="salesDateTo" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Payment Method</label>
                            <select class="form-control" id="salesPaymentFilter">
                                <option value="all">All Methods</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                            <button class="btn btn-primary" onclick="loadSales()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    
                    <!-- Sales Summary -->
                    <div class="stats-grid" style="margin-bottom: 20px;">
                        <div class="stat-card">
                            <div class="stat-icon primary"><i class="fas fa-receipt"></i></div>
                            <div class="stat-content">
                                <h4>Total Sales</h4>
                                <p class="stat-value" id="salesSummaryCount">0</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon success"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="stat-content">
                                <h4>Total Amount</h4>
                                <p class="stat-value" id="salesSummaryAmount">KES 0</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon info"><i class="fas fa-coins"></i></div>
                            <div class="stat-content">
                                <h4>Cash</h4>
                                <p class="stat-value" id="salesSummaryCash">KES 0</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon warning"><i class="fas fa-mobile-alt"></i></div>
                            <div class="stat-content">
                                <h4>M-Pesa</h4>
                                <p class="stat-value" id="salesSummaryMpesa">KES 0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Receipt</th>
                                    <th>Cashier</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Change</th>
                                    <th>Method</th>
                                    <th>Date & Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesBody">
                                <tr><td colspan="9" class="loading"><div class="spinner"></div>Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Tab -->
        <div class="tab-content" id="usersTab">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Users Management</h3>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-success" onclick="openAddUserModal()">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                        <button class="btn btn-secondary" onclick="loadUsers()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersBody">
                            <tr><td colspan="6" class="loading"><div class="spinner"></div>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Product Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="productModalTitle">Add Product</h3>
                <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" id="productId">
                    
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" class="form-control" id="productName" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Barcode / Part Number</label>
                            <input type="text" class="form-control" id="productBarcode">
                        </div>
                        <div class="form-group">
                            <label>Unit</label>
                            <select class="form-control" id="productUnit">
                                <option value="pc">Piece (pc)</option>
                                <option value="kg">Kilogram (kg)</option>
                                <option value="g">Gram (g)</option>
                                <option value="l">Liter (l)</option>
                                <option value="ml">Milliliter (ml)</option>
                                <option value="box">Box</option>
                                <option value="pack">Pack</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Buying Price (KES)</label>
                            <input type="number" class="form-control price-input" id="productBuyingPrice" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Selling Price (KES) *</label>
                            <input type="number" class="form-control price-input" id="productSellingPrice" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Stock Quantity</label>
                            <input type="number" class="form-control" id="productStock" step="0.01" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="productStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>
    
    <!-- User Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="userModalTitle">Add User</h3>
                <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId">
                    
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" class="form-control" id="userFullName" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" class="form-control" id="userEmail" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role *</label>
                            <select class="form-control" id="userRole">
                                <option value="cashier">Cashier</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" id="userStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>PIN <span id="pinHint">(Required for new users)</span></label>
                        <input type="text" class="form-control" id="userPin" placeholder="Enter PIN">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveUser()">Save User</button>
            </div>
        </div>
    </div>
    
    <!-- Sale Details Modal -->
    <div class="modal-overlay" id="saleModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3>Sale Details</h3>
                <button class="modal-close" onclick="closeModal('saleModal')">&times;</button>
            </div>
            <div class="modal-body" id="saleDetailsBody">
                <!-- Sale details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('saleModal')">Close</button>
                <button class="btn btn-primary" onclick="printSaleReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // ================================================================
        // CHAIRMAN POS - Admin JavaScript
        // ================================================================
        
        let currentProducts = [];
        let currentSaleId = null;
        
        // ============ INITIALIZATION ============
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved theme
            const savedTheme = localStorage.getItem('adminTheme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon();
            
            // Load dashboard
            loadDashboard();
        });
        
        // ============ THEME ============
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('adminTheme', next);
            updateThemeIcon();
        }
        
        function updateThemeIcon() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.getElementById('themeIcon').className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }
        
        // ============ TABS ============
        function showTab(tabName, btn) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.add('active');
            if (btn) btn.classList.add('active');
            
            // Load data
            if (tabName === 'dashboard') loadDashboard();
            else if (tabName === 'products') loadProducts();
            else if (tabName === 'sales') loadSales();
            else if (tabName === 'users') loadUsers();
        }
        
        function goToPOS() {
            window.location.href = 'index.php?page=cashier';
        }
        
        // ============ DASHBOARD ============
        async function loadDashboard() {
            try {
                // Load stats
                const statsRes = await fetch('index.php?page=admin&action=dashboard_stats');
                if (!statsRes.ok) throw new Error('Failed to load stats: ' + statsRes.statusText);
                const stats = await statsRes.json();
                
                document.getElementById('statTodaySales').textContent = stats.today_sales;
                document.getElementById('statTodayAmount').textContent = 'KES ' + formatMoney(stats.today_amount);
                document.getElementById('statProducts').textContent = stats.products_count;
                document.getElementById('statLowStock').textContent = stats.low_stock_count;
                document.getElementById('statCash').textContent = 'KES ' + formatMoney(stats.cash_total);
                document.getElementById('statMpesa').textContent = 'KES ' + formatMoney(stats.mpesa_total);
                
                // Load recent sales
                const salesRes = await fetch('index.php?page=admin&action=recent_sales');
                if (!salesRes.ok) throw new Error('Failed to load sales: ' + salesRes.statusText);
                const sales = await salesRes.json();
                
                if (!Array.isArray(sales)) throw new Error('Invalid sales response format');
                
                const tbody = document.getElementById('recentSalesBody');
                
                if (sales.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-receipt"></i><p>No sales today</p></td></tr>';
                    return;
                }
                
                tbody.innerHTML = sales.map(sale => `
                    <tr>
                        <td><strong>${sale.sale_number}</strong></td>
                        <td>${sale.cashier_name || 'Unknown'}</td>
                        <td style="font-family: 'JetBrains Mono'; font-weight: 600;">KES ${formatMoney(sale.total)}</td>
                        <td><span class="badge ${sale.payment_method === 'mpesa' ? 'badge-success' : 'badge-info'}">${sale.payment_method.toUpperCase()}</span></td>
                        <td>${sale.sale_date} ${sale.sale_time}</td>
                        <td>
                            <button class="action-btn edit" onclick="viewSale(${sale.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                
            } catch (e) {
                console.error('Error loading dashboard:', e);
                showToast('Error loading dashboard', 'error');
            }
        }
        
        // ============ PRODUCTS ============
        async function loadProducts() {
            const filter = document.getElementById('productFilter').value;
            const search = document.getElementById('productSearch')?.value || '';
            
            try {
                const res = await fetch(`index.php?page=admin&action=get_products&status=${filter}&search=${encodeURIComponent(search)}`);
                if (!res.ok) throw new Error('Failed to load products: ' + res.statusText);
                const data = await res.json();
                
                if (!Array.isArray(data)) {
                    console.error('Invalid response format:', data);
                    throw new Error('Invalid products response format');
                }
                currentProducts = data;
                renderProducts();
            } catch (e) {
                console.error('Error loading products:', e);
                showToast('Error loading products', 'error');
            }
        }
        
        function renderProducts() {
            const tbody = document.getElementById('productsBody');
            
            if (currentProducts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-box-open"></i><p>No products found</p></td></tr>';
                return;
            }
            
            tbody.innerHTML = currentProducts.map(p => `
                <tr>
                    <td><strong>${escapeHtml(p.product_name)}</strong></td>
                    <td style="font-family: 'JetBrains Mono'; font-size: 12px;">${p.barcode || '-'}</td>
                    <td>${p.unit}</td>
                    <td style="font-family: 'JetBrains Mono';">${formatMoney(p.buying_price)}</td>
                    <td style="font-family: 'JetBrains Mono'; font-weight: 600; color: var(--success);">${formatMoney(p.selling_price)}</td>
                    <td>
                        <span class="${p.stock_quantity <= 5 ? 'badge badge-danger' : ''}">${p.stock_quantity}</span>
                    </td>
                    <td>
                        <span class="badge ${p.is_active ? 'badge-success' : 'badge-danger'}">${p.is_active ? 'Active' : 'Inactive'}</span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="action-btn edit" onclick="editProduct(${p.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete" onclick="deleteProduct(${p.id}, '${escapeHtml(p.product_name)}')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        function filterProducts() {
            const search = document.getElementById('productSearch').value.toLowerCase();
            const filtered = currentProducts.filter(p => 
                p.product_name.toLowerCase().includes(search) ||
                (p.barcode && p.barcode.toLowerCase().includes(search))
            );
            
            const tbody = document.getElementById('productsBody');
            
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><p>No matching products</p></td></tr>';
                return;
            }
            
            tbody.innerHTML = filtered.map(p => `
                <tr>
                    <td><strong>${escapeHtml(p.product_name)}</strong></td>
                    <td style="font-family: 'JetBrains Mono'; font-size: 12px;">${p.barcode || '-'}</td>
                    <td>${p.unit}</td>
                    <td style="font-family: 'JetBrains Mono';">${formatMoney(p.buying_price)}</td>
                    <td style="font-family: 'JetBrains Mono'; font-weight: 600; color: var(--success);">${formatMoney(p.selling_price)}</td>
                    <td><span class="${p.stock_quantity <= 5 ? 'badge badge-danger' : ''}">${p.stock_quantity}</span></td>
                    <td><span class="badge ${p.is_active ? 'badge-success' : 'badge-danger'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <div class="action-btns">
                            <button class="action-btn edit" onclick="editProduct(${p.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="action-btn delete" onclick="deleteProduct(${p.id}, '${escapeHtml(p.product_name)}')" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        function openAddProductModal() {
            document.getElementById('productModalTitle').textContent = 'Add Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productStatus').value = '1';
            openModal('productModal');
        }
        
        async function editProduct(id) {
            try {
                const res = await fetch(`index.php?page=admin&action=get_product&id=${id}`);
                const data = await res.json();
                
                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }
                
                const p = data.product;
                document.getElementById('productModalTitle').textContent = 'Edit Product';
                document.getElementById('productId').value = p.id;
                document.getElementById('productName').value = p.product_name;
                document.getElementById('productBarcode').value = p.barcode || '';
                document.getElementById('productUnit').value = p.unit || 'pc';
                document.getElementById('productBuyingPrice').value = p.buying_price;
                document.getElementById('productSellingPrice').value = p.selling_price;
                document.getElementById('productStock').value = p.stock_quantity;
                document.getElementById('productStatus').value = p.is_active ? '1' : '0';
                
                openModal('productModal');
            } catch (e) {
                showToast('Error loading product', 'error');
            }
        }
        
        async function saveProduct() {
            const id = document.getElementById('productId').value;
            const data = {
                id: id ? parseInt(id) : null,
                product_name: document.getElementById('productName').value.trim(),
                barcode: document.getElementById('productBarcode').value.trim(),
                unit: document.getElementById('productUnit').value,
                buying_price: parseFloat(document.getElementById('productBuyingPrice').value) || 0,
                selling_price: parseFloat(document.getElementById('productSellingPrice').value) || 0,
                stock_quantity: parseFloat(document.getElementById('productStock').value) || 0,
                is_active: document.getElementById('productStatus').value === '1'
            };
            
            if (!data.product_name) {
                showToast('Product name is required', 'error');
                return;
            }
            
            if (data.selling_price <= 0) {
                showToast('Selling price must be greater than 0', 'error');
                return;
            }
            
            try {
                const action = id ? 'update_product' : 'add_product';
                const res = await fetch(`index.php?page=admin&action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal('productModal');
                    loadProducts();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error saving product', 'error');
            }
        }
        
        async function deleteProduct(id, name) {
            if (!confirm(`Are you sure you want to delete "${name}"?`)) return;
            
            try {
                const res = await fetch(`index.php?page=admin&action=delete_product&id=${id}`);
                const result = await res.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    loadProducts();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error deleting product', 'error');
            }
        }
        
        // ============ SALES ============
        async function loadSales() {
            const dateFrom = document.getElementById('salesDateFrom').value;
            const dateTo = document.getElementById('salesDateTo').value;
            const method = document.getElementById('salesPaymentFilter').value;
            
            try {
                const res = await fetch(`index.php?page=admin&action=get_sales&date_from=${dateFrom}&date_to=${dateTo}&payment_method=${method}`);
                if (!res.ok) throw new Error('Failed to load sales: ' + res.statusText);
                const data = await res.json();
                
                if (!data.sales || !data.summary) {
                    console.error('Invalid sales response:', data);
                    throw new Error('Invalid sales response format');
                }
                
                // Update summary
                document.getElementById('salesSummaryCount').textContent = data.summary.total_sales;
                document.getElementById('salesSummaryAmount').textContent = 'KES ' + formatMoney(data.summary.total_amount);
                document.getElementById('salesSummaryCash').textContent = 'KES ' + formatMoney(data.summary.cash_total);
                document.getElementById('salesSummaryMpesa').textContent = 'KES ' + formatMoney(data.summary.mpesa_total);
                
                const tbody = document.getElementById('salesBody');
                
                if (data.sales.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="empty-state"><i class="fas fa-receipt"></i><p>No sales found</p></td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.sales.map((sale, idx) => `
                    <tr>
                        <td>${idx + 1}</td>
                        <td><strong>${sale.sale_number}</strong></td>
                        <td>${sale.cashier_name || 'Unknown'}</td>
                        <td style="font-family: 'JetBrains Mono'; font-weight: 600;">KES ${formatMoney(sale.total)}</td>
                        <td style="font-family: 'JetBrains Mono';">${formatMoney(sale.amount_paid)}</td>
                        <td style="font-family: 'JetBrains Mono';">${formatMoney(sale.change_amount)}</td>
                        <td><span class="badge ${sale.payment_method === 'mpesa' ? 'badge-success' : 'badge-info'}">${sale.payment_method.toUpperCase()}</span></td>
                        <td>${sale.sale_date} ${sale.sale_time}</td>
                        <td>
                            <button class="action-btn edit" onclick="viewSale(${sale.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                
            } catch (e) {
                console.error('Error loading sales:', e);
                showToast('Error loading sales', 'error');
            }
        }
        
        async function viewSale(id) {
            currentSaleId = id;
            
            try {
                const res = await fetch(`index.php?page=admin&action=get_sale_details&id=${id}`);
                const data = await res.json();
                
                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }
                
                const sale = data.sale;
                const items = data.items;
                
                let itemsHtml = items.map(item => `
                    <tr>
                        <td>${escapeHtml(item.product_name)}</td>
                        <td>${item.barcode || '-'}</td>
                        <td style="text-align: center;">${item.quantity}</td>
                        <td style="text-align: right;">${formatMoney(item.unit_price)}</td>
                        <td style="text-align: right; font-weight: 600;">${formatMoney(item.subtotal)}</td>
                    </tr>
                `).join('');
                
                document.getElementById('saleDetailsBody').innerHTML = `
                    <div style="margin-bottom: 20px;">
                        <div class="form-row">
                            <div><strong>Receipt:</strong> ${sale.sale_number}</div>
                            <div><strong>Date:</strong> ${sale.sale_date} ${sale.sale_time}</div>
                        </div>
                        <div class="form-row" style="margin-top: 10px;">
                            <div><strong>Cashier:</strong> ${sale.cashier_name}</div>
                            <div><strong>Payment:</strong> <span class="badge ${sale.payment_method === 'mpesa' ? 'badge-success' : 'badge-info'}">${sale.payment_method.toUpperCase()}</span></div>
                        </div>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Barcode</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td>
                                <td style="text-align: right;">${formatMoney(sale.subtotal)}</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="text-align: right; font-size: 18px;"><strong>TOTAL:</strong></td>
                                <td style="text-align: right; font-size: 18px; font-weight: 700; color: var(--success);">KES ${formatMoney(sale.total)}</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="text-align: right;">Paid:</td>
                                <td style="text-align: right;">${formatMoney(sale.amount_paid)}</td>
                            </tr>
                            <tr>
                                <td colspan="4" style="text-align: right;">Change:</td>
                                <td style="text-align: right;">${formatMoney(sale.change_amount)}</td>
                            </tr>
                        </tfoot>
                    </table>
                `;
                
                openModal('saleModal');
            } catch (e) {
                showToast('Error loading sale details', 'error');
            }
        }
        
        function printSaleReceipt() {
            // You can implement receipt printing here
            showToast('Print functionality - implement based on your printer setup', 'info');
        }
        
        function exportSales() {
            const dateFrom = document.getElementById('salesDateFrom').value;
            const dateTo = document.getElementById('salesDateTo').value;
            window.location.href = `index.php?page=admin&action=export_sales&date_from=${dateFrom}&date_to=${dateTo}`;
        }
        
        // ============ USERS ============
        async function loadUsers() {
            try {
                const res = await fetch('index.php?page=admin&action=get_users');
                if (!res.ok) throw new Error('Failed to load users: ' + res.statusText);
                const data = await res.json();
                
                if (!Array.isArray(data)) {
                    console.error('Invalid users response:', data);
                    throw new Error('Invalid users response format');
                }
                const users = data;
                
                const tbody = document.getElementById('usersBody');
                
                if (users.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="fas fa-users"></i><p>No users found</p></td></tr>';
                    return;
                }
                
                tbody.innerHTML = users.map(u => `
                    <tr>
                        <td><strong>${escapeHtml(u.full_name)}</strong></td>
                        <td>${escapeHtml(u.email)}</td>
                        <td><span class="badge badge-info">${u.role.toUpperCase()}</span></td>
                        <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-danger'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>${u.last_login || 'Never'}</td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn edit" onclick="editUser(${u.id}, '${escapeHtml(u.full_name)}', '${escapeHtml(u.email)}', '${u.role}', ${u.is_active})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="deleteUser(${u.id}, '${escapeHtml(u.full_name)}')" title="Deactivate">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                
            } catch (e) {
                showToast('Error loading users', 'error');
            }
        }
        
        function openAddUserModal() {
            document.getElementById('userModalTitle').textContent = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('userStatus').value = '1';
            document.getElementById('pinHint').textContent = '(Required for new users)';
            document.getElementById('userPin').required = true;
            document.getElementById('userEmail').readOnly = false;
            openModal('userModal');
        }
        
        function editUser(id, name, email, role, isActive) {
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = id;
            document.getElementById('userFullName').value = name;
            document.getElementById('userEmail').value = email;
            document.getElementById('userEmail').readOnly = true;
            document.getElementById('userRole').value = role;
            document.getElementById('userStatus').value = isActive ? '1' : '0';
            document.getElementById('userPin').value = '';
            document.getElementById('userPin').required = false;
            document.getElementById('pinHint').textContent = '(Leave blank to keep current)';
            openModal('userModal');
        }
        
        async function saveUser() {
            const id = document.getElementById('userId').value;
            const data = {
                id: id ? parseInt(id) : null,
                full_name: document.getElementById('userFullName').value.trim(),
                email: document.getElementById('userEmail').value.trim(),
                role: document.getElementById('userRole').value,
                is_active: document.getElementById('userStatus').value === '1',
                pin: document.getElementById('userPin').value.trim()
            };
            
            if (!data.full_name || !data.email) {
                showToast('Name and email are required', 'error');
                return;
            }
            
            if (!id && !data.pin) {
                showToast('PIN is required for new users', 'error');
                return;
            }
            
            try {
                const action = id ? 'update_user' : 'add_user';
                const res = await fetch(`index.php?page=admin&action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    closeModal('userModal');
                    loadUsers();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error saving user', 'error');
            }
        }
        
        async function deleteUser(id, name) {
            if (!confirm(`Are you sure you want to deactivate "${name}"?`)) return;
            
            try {
                const res = await fetch(`index.php?page=admin&action=delete_user&id=${id}`);
                const result = await res.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    loadUsers();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error deactivating user', 'error');
            }
        }
        
        // ============ MODALS ============
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        
        // ============ UTILITIES ============
        function formatMoney(amount) {
            return parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            toast.innerHTML = `
                <i class="fas ${icons[type] || icons.info}"></i>
                <span>${message}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('index.php?page=admin&action=logout')
                    .then(() => window.location.href = 'index.php')
                    .catch(() => window.location.href = 'index.php');
            }
        }
    </script>
</body>
</html>