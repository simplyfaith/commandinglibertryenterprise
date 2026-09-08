<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/CustomerAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') \Response::error('Method not allowed', 405);
\Response::success(['customer' => \CustomerAuth::requireAuth()]);
