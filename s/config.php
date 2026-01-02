<?php
/**
 * ================================================================
 * CHAIRMAN POS - Configuration File
 * ================================================================
 * Developer: Glen
 * Contact: +254735065427
 * ================================================================
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); 

// Timezone
date_default_timezone_set('Africa/Nairobi');

// ================================================================
// DATABASE CONFIGURATION
// ================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'chairman_pos');
define('DB_USER', 'glen');
define('DB_PASS', 'Glen@2025');
define('DB_CHARSET', 'utf8mb4');

// ================================================================
// APPLICATION CONFIGURATION
// ================================================================

define('APP_NAME', 'Chairman POS');
define('APP_VERSION', '1.0.0');
define('DEVELOPER_NAME', 'Glen');
define('DEVELOPER_PHONE', '+254735065427');
define('COMPANY_NAME', 'BARAKA TELE');
define('COMPANY_PHONE', '+254700000000');
define('CURRENCY', 'KES');
define('CURRENCY_SYMBOL', 'KES');

// Session & Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Receipt Settings
define('RECEIPT_WIDTH', 48); // 80mm thermal printer
define('PAPER_CUT_ENABLED', true);

// ================================================================
// M-PESA CONFIGURATION (Daraja API - Sandbox)
// ================================================================

define('MPESA_ENV', 'sandbox');
define('MPESA_CONSUMER_KEY', 'fc3S6LRQIvtAXMtDOkULAAJdYCSrOLMjaG7IISlhXZe60iYs');
define('MPESA_CONSUMER_SECRET', 'hib6HfvrtHsGpzQx7Ijk33wc1QpUdsgp24HhthgXhviQOKL37Id9LqsOATj70mIk');
define('MPESA_SHORTCODE', '174379'); // Sandbox test shortcode
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Sandbox passkey

// M-Pesa API URLs
if (MPESA_ENV === 'sandbox') {
    define('MPESA_AUTH_URL', 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    define('MPESA_QUERY_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
} else {
    define('MPESA_AUTH_URL', 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
    define('MPESA_STK_URL', 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
    define('MPESA_QUERY_URL', 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query');
}

// Callback URL - Update this to your actual domain
// For VPS: http://13.57.193.106/api/mpesa_callback.php
// For production: https://yourdomain.com/api/mpesa_callback.php
define('MPESA_CALLBACK_URL', 'http://13.57.193.106/api/mpesa_callback.php');

// ================================================================
// RATE LIMITING CONFIGURATION
// ================================================================
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_LOGIN_ATTEMPTS', 5);           // Max login attempts
define('RATE_LIMIT_LOGIN_WINDOW', 900);            // 15 minutes window
define('RATE_LIMIT_API_CALLS', 100);               // Max API calls per window
define('RATE_LIMIT_API_WINDOW', 60);               // 1 minute window
define('RATE_LIMIT_MPESA_ATTEMPTS', 10);           // Max M-Pesa attempts per window
define('RATE_LIMIT_MPESA_WINDOW', 300);            // 5 minutes window
// ================================================================
// DATABASE CONNECTION CLASS
// ================================================================

class Database {
    private static $instance = null;
    private $pdo;
    private $retryCount = 0;
    private $maxRetries = 3;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_TIMEOUT => 5
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->retryCount = 0; // Reset retry count on successful connection
        } catch (PDOException $e) {
            // Try to reconnect if we haven't exceeded max retries
            if ($this->retryCount < $this->maxRetries) {
                $this->retryCount++;
                sleep(1); // Wait 1 second before retrying
                $this->connect();
            } else {
                error_log("Database Connection Failed: " . $e->getMessage());
                die("Database Connection Failed: " . $e->getMessage());
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        } else {
            // Check if connection is still alive
            try {
                self::$instance->pdo->query('SELECT 1');
            } catch (PDOException $e) {
                // Connection lost, reconnect
                self::$instance->connect();
            }
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Get database connection
 */
function db() {
    return Database::getInstance()->getConnection();
}

/**
 * Sanitize input
 */
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

/**
 * Get current user info
 */
function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'company_id' => $_SESSION['company_id'],
        'company_name' => $_SESSION['company_name']
    ];
}

/**
 * Redirect function
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    // Clear any previous output that might corrupt JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Format currency
 */
function formatMoney($amount) {
    return number_format((float)$amount, 2);
}

/**
 * Generate sale number
 */
function generateSaleNumber($companyId) {
    $prefix = date('Ymd');
    $stmt = db()->prepare("SELECT COUNT(*) + 1 as num FROM sales WHERE company_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    return $prefix . str_pad($row['num'], 4, '0', STR_PAD_LEFT);
}

// ================================================================
// RATE LIMITING FUNCTIONS
// ================================================================

/**
 * Check if request exceeds rate limit
 */
function checkRateLimit($identifier, $limit, $window) {
    if (!RATE_LIMIT_ENABLED) return true;
    
    $cacheKey = 'rate_limit_' . md5($identifier);
    
    // Use session for simplicity (can be upgraded to Redis for production)
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    
    // Clean old entries
    if (isset($_SESSION['rate_limits'][$cacheKey])) {
        $_SESSION['rate_limits'][$cacheKey] = array_filter(
            $_SESSION['rate_limits'][$cacheKey],
            function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            }
        );
    }
    
    // Initialize if needed
    if (!isset($_SESSION['rate_limits'][$cacheKey])) {
        $_SESSION['rate_limits'][$cacheKey] = [];
    }
    
    $count = count($_SESSION['rate_limits'][$cacheKey]);
    
    if ($count >= $limit) {
        return false; // Rate limit exceeded
    }
    
    // Record this request
    $_SESSION['rate_limits'][$cacheKey][] = $now;
    return true; // Rate limit OK
}

/**
 * Get remaining attempts for rate limit
 */
function getRateLimitRemaining($identifier, $limit, $window) {
    if (!RATE_LIMIT_ENABLED) return $limit;
    
    $cacheKey = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION['rate_limits'][$cacheKey])) {
        return $limit;
    }
    
    $now = time();
    $remaining = array_filter(
        $_SESSION['rate_limits'][$cacheKey],
        function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        }
    );
    
    return max(0, $limit - count($remaining));
}

/**
 * Get reset time for rate limit
 */
function getRateLimitResetTime($identifier, $window) {
    $cacheKey = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION['rate_limits'][$cacheKey]) || empty($_SESSION['rate_limits'][$cacheKey])) {
        return time(); // No limit active
    }
    
    $oldest = min($_SESSION['rate_limits'][$cacheKey]);
    $resetTime = $oldest + $window;
    
    return max(time(), $resetTime);
}

/**
 * Middleware to check API rate limiting
 */
function enforceRateLimit($limitType = 'api') {
    $clientIp = getClientIp();
    
    switch ($limitType) {
        case 'login':
            $limit = RATE_LIMIT_LOGIN_ATTEMPTS;
            $window = RATE_LIMIT_LOGIN_WINDOW;
            break;
        case 'api':
            $limit = RATE_LIMIT_API_CALLS;
            $window = RATE_LIMIT_API_WINDOW;
            break;
        case 'mpesa':
            $limit = RATE_LIMIT_MPESA_ATTEMPTS;
            $window = RATE_LIMIT_MPESA_WINDOW;
            break;
        default:
            return true;
    }
    
    $identifier = $limitType . '_' . $clientIp;
    
    if (!checkRateLimit($identifier, $limit, $window)) {
        $resetTime = getRateLimitResetTime($identifier, $window);
        $wait = $resetTime - time();
        
        jsonResponse([
            'success' => false,
            'message' => "Too many attempts. Please try again in {$wait} seconds.",
            'retry_after' => $wait
        ], 429);
    }
    
    return true;
}

/**
 * Get client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// ================================================================
// M-PESA API HELPER CLASS
// ================================================================

class MpesaAPI {
    
    /**
     * Get OAuth access token
     */
    public static function getAccessToken() {
        $auth = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
        
        $ch = curl_init(MPESA_AUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("M-Pesa Auth cURL Error: " . $error);
            return null;
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode !== 200 || !isset($result['access_token'])) {
            error_log("M-Pesa Auth Failed: " . $response);
            return null;
        }
        
        return $result['access_token'];
    }
    
    /**
     * Normalize phone number to 254 format
     */
    private static function normalizePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Send STK Push Request
     */
    public static function stkPush($phone, $amount) {
        $token = self::getAccessToken();
        
        if (!$token) {
            return ['success' => false, 'message' => 'Failed to get M-Pesa access token'];
        }
        
        $phone = self::normalizePhone($phone);
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) ceil($amount), // Round up, must be integer
            'PartyA' => $phone,
            'PartyB' => MPESA_SHORTCODE,
            'PhoneNumber' => $phone,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => 'ChairmanPOS',
            'TransactionDesc' => 'Payment'
        ];
        
        $ch = curl_init(MPESA_STK_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("M-Pesa STK cURL Error: " . $error);
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }
        
        error_log("M-Pesa STK Response: " . $response);
        
        $result = json_decode($response, true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            return [
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID'],
                'merchant_request_id' => $result['MerchantRequestID'],
                'response_description' => $result['ResponseDescription'] ?? 'Success'
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'STK push failed',
            'response_code' => $result['ResponseCode'] ?? $result['errorCode'] ?? 'ERROR'
        ];
    }
    
    /**
     * Check STK Push Status (Query)
     */
    public static function checkStatus($checkoutRequestId) {
        $token = self::getAccessToken();
        
        if (!$token) {
            return ['success' => false, 'status' => 'error', 'message' => 'Failed to get access token'];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];
        
        $ch = curl_init(MPESA_QUERY_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("M-Pesa Query cURL Error: " . $error);
            return ['success' => false, 'status' => 'error', 'message' => 'Connection error'];
        }
        
        error_log("M-Pesa Query Response: " . $response);
        
        $result = json_decode($response, true);
        
        // Check ResultCode
        if (isset($result['ResultCode'])) {
            $resultCode = (string) $result['ResultCode'];
            
            if ($resultCode === '0') {
                // Success - payment completed
                return [
                    'success' => true,
                    'status' => 'completed',
                    'mpesa_receipt' => $result['MpesaReceiptNumber'] ?? 'N/A',
                    'message' => 'Payment successful'
                ];
            } elseif ($resultCode === '1032') {
                // User cancelled
                return [
                    'success' => false,
                    'status' => 'cancelled',
                    'message' => 'Transaction cancelled by user'
                ];
            } elseif ($resultCode === '1037') {
                // Timeout
                return [
                    'success' => false,
                    'status' => 'timeout',
                    'message' => 'Transaction timed out'
                ];
            } else {
                // Other failure
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $result['ResultDesc'] ?? 'Payment failed'
                ];
            }
        }
        
        // Still processing
        return [
            'success' => true,
            'status' => 'pending',
            'message' => 'Waiting for payment confirmation'
        ];
    }
}
