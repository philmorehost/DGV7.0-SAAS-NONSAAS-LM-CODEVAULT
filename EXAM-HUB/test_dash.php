<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
session_start();
$_SESSION['user_id'] = 1;
require_once __DIR__ . '/user/dashboard.php';
