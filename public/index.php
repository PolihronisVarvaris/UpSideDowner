<?php  

session_start();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

require_once __DIR__ . '/../routes/web.php';