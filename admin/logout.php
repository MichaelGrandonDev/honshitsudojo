<?php
require __DIR__ . '/bootstrap.php';
start_admin_session($config);
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], isset($p['domain']) ? $p['domain'] : '', !empty($p['secure']), !empty($p['httponly']));
}
session_destroy();
header('Location: index.php');
exit;
