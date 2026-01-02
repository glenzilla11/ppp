<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

// ================================================================
// ENFORCE RATE LIMITING ON LOGIN
// ================================================================
enforceRateLimit('login');

try {
    $auth = new Auth();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        jsonResponse(['success' => false, 'message' => 'Invalid request format'], 400);
    }

    $email = $data['email'] ?? '';
    $pin = $data['pin'] ?? '';

    if (empty($email) || empty($pin)) {
        jsonResponse(['success' => false, 'message' => 'Email and PIN are required'], 400);
    }

    $result = $auth->login($email, $pin);
    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'data' => [
                'role' => $result['role'],
                'user_name' => $result['user_name']
            ]
        ]);
    } else {
        jsonResponse($result, 401);
    }
} catch (PDOException $e) {
    error_log("Database error in login: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error. Please try again.'], 500);
} catch (Exception $e) {
    error_log("Error in login: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
}