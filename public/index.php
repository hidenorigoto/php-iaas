<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use VmManagement\Test;

// Simple test endpoint
$test = new Test();
echo json_encode([
    'message' => $test->hello(),
    'status' => 'VM Management PHP is running!',
    'timestamp' => date('Y-m-d H:i:s')
]);