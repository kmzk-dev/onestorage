<?php
require_once __DIR__ . '/../path.php'; 
if (!defined('AUTH_CONFIG_PATH') || !file_exists(AUTH_CONFIG_PATH)) {
    redirect('setting.php');
}

function check_authentication() {
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return true;
    } else {
        redirect('login.php');
        exit; 
    }
}