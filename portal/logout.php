<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../SSO/csrf.php';
csrf_logout_gate('index.php');
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
