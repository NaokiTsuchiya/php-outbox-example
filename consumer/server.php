<?php

/**
 * モックコンシューマ
 * PHP ビルトインサーバーで動作（Swoole 不要）
 */

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$uri    = $_SERVER['REQUEST_URI'] ?? '';

header('Content-Type: application/json');

if ($method === 'POST' && $uri === '/events') {
    $body = json_decode(file_get_contents('php://input'), true);
    $timestamp = date('Y-m-d H:i:s');
    error_log(sprintf("[%s] Received event:\n  type:    %s\n  payload: %s",
        $timestamp,
        $body['type'] ?? '(unknown)',
        json_encode($body['payload'] ?? [])
    ));
    echo json_encode(['status' => 'accepted']);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);
