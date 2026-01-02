<?php
/**
 * Chairman POS - Main Router
 * Routes requests to views or API handlers
 */

require_once 'config.php';

// Get current page/action
$page = isset($_GET['page']) ? clean($_GET['page']) : 'login';
$action = isset($_GET['action']) ? clean($_GET['action']) : '';

// Handle API actions
if ($action) {
    handleAction($action);
}

// Handle page routing
switch ($page) {
    case 'login':
        require_once 'views/login.php';
        break;
    
    case 'cashier':
        if (!isLoggedIn()) {
            redirect('index.php');
        }
        require_once 'views/cashier.php';
        break;
    
    case 'admin':
        if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            redirect('index.php');
        }
        require_once 'views/admin.php';
        break;
    
    default:
        if (isLoggedIn()) {
            redirect('index.php?page=cashier');
        } else {
            redirect('index.php?page=login');
        }
}

/**
 * Handle AJAX actions
 */
function handleAction($action) {
    // Check if admin/manager is trying to access admin actions
    if (in_array($action, ['dashboard_stats', 'recent_sales', 'get_products', 'get_product', 'add_product', 'update_product', 'delete_product', 'get_sales', 'get_sale_details', 'get_users', 'add_user', 'update_user', 'delete_user', 'export_sales', 'bulk_update_prices', 'adjust_stock'])) {
        if (!isLoggedIn() || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        // Let admin.php handle these actions
        require_once 'views/admin.php';
        exit();
    }
    
    switch ($action) {
        case 'login':
            require_once 'api/login.php';
            break;
        case 'logout':
            require_once 'api/logout.php';
            break;
        case 'search':
            require_once 'api/search.php';
            break;
        case 'barcode':
            require_once 'api/barcode.php';
            break;
        case 'sale':
            require_once 'api/sale.php';
            break;
        case 'mpesa_stk':
            require_once 'api/mpesa_stk.php';
            break;
        case 'mpesa_status':
            require_once 'api/mpesa_status.php';
            break;
        case 'receipt':
            require_once 'api/receipt.php';
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 404);
    }
}
