<?php
// Health check endpoint for Docker
// This file bypasses Laravel for faster health checks
http_response_code(200);
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode(['status' => 'ok', 'timestamp' => time()]);
exit;
