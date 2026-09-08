<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/CustomerAuth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') \Response::error('Method not allowed', 405);
\CustomerAuth::logout();
