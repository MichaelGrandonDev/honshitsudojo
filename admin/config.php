<?php
/**
 * Configuración del panel. El usuario y la contraseña viven en credentials.php
 * (no se versiona; ver credentials.example.php). Sin ese archivo el ingreso queda bloqueado.
 */
$credentials = is_file(__DIR__ . '/credentials.php') ? require __DIR__ . '/credentials.php' : array();
if (!is_array($credentials)) {
    $credentials = array();
}

return array(
    'session_name' => 'honshitsu_admin',
    'auth_user' => isset($credentials['user']) ? (string)$credentials['user'] : '',
    'auth_pass' => isset($credentials['pass']) ? (string)$credentials['pass'] : '',
    'max_upload_bytes' => 80 * 1024 * 1024,
    'allowed_image' => array('image/jpeg', 'image/png', 'image/webp', 'image/gif'),
    'allowed_video' => array('video/mp4', 'video/webm', 'video/quicktime'),
    'allowed_pdf' => array('application/pdf'),
    'ext_image' => array('jpg', 'jpeg', 'png', 'webp', 'gif'),
    'ext_video' => array('mp4', 'webm', 'mov'),
    'ext_pdf' => array('pdf'),
);
