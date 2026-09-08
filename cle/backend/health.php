<?php
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok']);
