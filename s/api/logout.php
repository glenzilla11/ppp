<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = new Auth();
$result = $auth->logout();

jsonResponse($result);