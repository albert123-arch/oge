<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/auth.php';

logout_user();
header('Location: ' . SITE_URL . '/authentication/login.php');
exit();
