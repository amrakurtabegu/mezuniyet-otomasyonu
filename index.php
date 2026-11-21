<?php
require_once __DIR__ . '/includes/auth.php';
$u = current_user();
redirect($u ? rol_anasayfa($u['rol']) : '/login.php');
