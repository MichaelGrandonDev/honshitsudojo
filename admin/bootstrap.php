<?php
$config = require __DIR__ . '/config.php';
@date_default_timezone_set('America/Argentina/Buenos_Aires');
$root = dirname(__DIR__);
$qualityRules = array(
    'gallery' => array('dim' => 'w', 'min' => 1200, 'hint' => 'Recomendado: 1600 px de ancho o más (mínimo 1200 px).'),
    'collage' => array('dim' => 'h', 'min' => 900, 'hint' => 'Recomendado: foto vertical de 1200 px de alto o más (mínimo 900 px).'),
);
$galleryDir = $root . '/gallery';
$mediaDir = $galleryDir . '/media';
$galleryData = $galleryDir . '/data.json';
$blogDir = $root . '/blog';
$blogFiles = $blogDir . '/files';
$blogData = $blogDir . '/data.json';
$collageDir = $root . '/collage';
$collageMedia = $collageDir . '/media';
$collageData = $collageDir . '/data.json';
$collageSections = array(
    'karate' => '¿Qué es Karate Do?',
    'historia' => 'Un poco de historia',
);
$ubicacionDir = $root . '/ubicacion';
$ubicacionData = $ubicacionDir . '/data.json';
$ubicacionDefaults = array(
    'place_name' => 'La Biblioteca Alberdi',
    'location_label' => 'Punta Alta, Argentina',
    'address_lines' => array('Rivadavia 353, B8109', 'Punta Alta, Provincia de Buenos Aires'),
    'map_query' => 'Rivadavia 353, B8109 Punta Alta, Provincia de Buenos Aires, Argentina',
);

foreach (array($galleryDir, $mediaDir, $blogDir, $blogFiles, $collageDir, $collageMedia) as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
}

if (!is_file($galleryData)) {
    @file_put_contents($galleryData, "{\n  \"items\": []\n}\n");
}
if (!is_file($blogData)) {
    @file_put_contents($blogData, "{\n  \"items\": []\n}\n");
}
if (!is_file($collageData)) {
    @file_put_contents($collageData, "{\n  \"karate\": [],\n  \"historia\": []\n}\n");
}
foreach (array($collageDir => "Options -Indexes\n", $collageMedia => "Options -Indexes\n<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx)$\">\n  Require all denied\n</FilesMatch>\n") as $d => $rules) {
    if (is_dir($d) && !is_file($d . '/.htaccess')) {
        @file_put_contents($d . '/.htaccess', $rules);
    }
}
if (!is_dir($ubicacionDir)) {
    @mkdir($ubicacionDir, 0755, true);
}
if (is_dir($ubicacionDir)) {
    if (!is_file($ubicacionData)) {
        save_json($ubicacionData, $ubicacionDefaults);
    }
    if (!is_file($ubicacionDir . '/.htaccess')) {
        @file_put_contents($ubicacionDir . '/.htaccess', "Options -Indexes\n");
    }
}

function load_ubicacion($file, $defaults)
{
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($data)) {
        return $defaults;
    }
    $out = $defaults;
    foreach (array('place_name', 'location_label', 'map_query') as $key) {
        if (isset($data[$key]) && is_string($data[$key])) {
            $out[$key] = trim($data[$key]);
        }
    }
    if (isset($data['address_lines']) && is_array($data['address_lines'])) {
        $lines = array();
        foreach ($data['address_lines'] as $line) {
            if (is_string($line) && trim($line) !== '') {
                $lines[] = trim($line);
            }
        }
        $out['address_lines'] = $lines;
    }
    return $out;
}

function ubicacion_map_query($data)
{
    $query = isset($data['map_query']) ? trim((string)$data['map_query']) : '';
    if ($query !== '') {
        return $query;
    }
    $parts = array();
    $lines = isset($data['address_lines']) && is_array($data['address_lines']) ? $data['address_lines'] : array();
    $lines[] = isset($data['location_label']) ? $data['location_label'] : '';
    foreach ($lines as $p) {
        $p = trim((string)$p);
        if ($p !== '') {
            $parts[] = $p;
        }
    }
    return implode(', ', $parts);
}

function clean_line($value, $max)
{
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$value);
    if ($s === null) {
        return '';
    }
    $s = trim($s);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($s, 0, $max, 'UTF-8'));
    }
    return trim(substr($s, 0, $max));
}

function load_collage($file, $sections)
{
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($data)) {
        $data = array();
    }
    foreach ($sections as $key => $label) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $data[$key] = array();
        }
    }
    return $data;
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function load_json($file)
{
    if (!is_file($file)) {
        return array('items' => array());
    }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return array('items' => array());
    }
    return $data;
}

function save_json($file, $data)
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents($file, $json . "\n", LOCK_EX) !== false;
}

function start_admin_session($config)
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name($config['session_name']);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(0, '/', '', $secure, true);
    }
    session_start();
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        } else {
            $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
        }
    }
    return $_SESSION['csrf'];
}

function require_csrf()
{
    $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
    if ($token === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Token invalido.');
    }
}

function new_id()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(8));
    }
    return bin2hex(openssl_random_pseudo_bytes(8));
}

function detect_mime($path)
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if ($mime) {
            return $mime;
        }
    }
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($path);
        if ($mime) {
            return $mime;
        }
    }
    return '';
}

function admin_credentials_ok($config, $user, $pass)
{
    $expectedUser = (string)$config['auth_user'];
    $expectedPass = (string)$config['auth_pass'];
    if ($expectedUser === '' || $expectedPass === '') {
        return false;
    }
    return hash_equals($expectedUser, $user) && hash_equals($expectedPass, $pass);
}

function post_str($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

/** Lista de archivos de un input (simple o multiple) con la forma de $_FILES de un solo archivo. */
function uploaded_files($field)
{
    $out = array();
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || !isset($_FILES[$field]['name'])) {
        return $out;
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return array($f);
    }
    $count = count($f['name']);
    for ($i = 0; $i < $count; $i++) {
        $out[] = array(
            'name' => $f['name'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i],
            'size' => $f['size'][$i],
        );
    }
    return $out;
}

/** Valida y guarda una foto (o video si $allowVideo). Devuelve array(id, type, filename) o null. */
function accept_upload($file, $config, $destDir, $allowVideo)
{
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int)$file['size'] > $config['max_upload_bytes']) {
        return null;
    }
    $mime = detect_mime($file['tmp_name']);
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $type = null;
    if (in_array($mime, $config['allowed_image'], true) && in_array($ext, $config['ext_image'], true)) {
        $type = 'image';
    } elseif ($allowVideo && in_array($mime, $config['allowed_video'], true) && in_array($ext, $config['ext_video'], true)) {
        $type = 'video';
    }
    if (!$type) {
        return null;
    }
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $id = new_id();
    $filename = $id . '.' . $ext;
    $dest = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    @chmod($dest, 0644);
    return array('id' => $id, 'type' => $type, 'filename' => $filename);
}

function delete_media($root, $src, $prefix)
{
    $src = (string)$src;
    if ($src !== '' && strpos($src, $prefix) === 0 && strpos($src, '..') === false) {
        $path = $root . '/' . $src;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** ID de 11 caracteres de un link de YouTube (watch, youtu.be, shorts, embed, live), o ''. */
function youtube_id($url)
{
    $url = trim((string)$url);
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
        return $url;
    }
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i', $url, $m)) {
        return $m[1];
    }
    return '';
}

/** URL pública de un archivo del sitio o externa (miniatura de YouTube). */
function media_url($src)
{
    $src = (string)$src;
    return preg_match('~^https?://~i', $src) ? $src : '/' . $src;
}

/** Ancho y alto de una imagen del sitio, o null. */
function media_size($root, $src)
{
    $src = (string)$src;
    if ($src === '' || strpos($src, '..') !== false || preg_match('~^https?://~i', $src)) {
        return null;
    }
    $path = $root . '/' . $src;
    if (!is_file($path)) {
        return null;
    }
    $info = @getimagesize($path);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }
    return array('w' => (int)$info[0], 'h' => (int)$info[1]);
}

function is_low_quality($size, $rule)
{
    if (!$size) {
        return false;
    }
    return $size[$rule['dim']] < $rule['min'];
}

function find_index($items, $id)
{
    foreach ($items as $i => $item) {
        if (is_array($item) && isset($item['id']) && (string)$item['id'] === (string)$id) {
            return $i;
        }
    }
    return -1;
}

/** Mueve un elemento: 'left', 'right' o 'first'. */
function move_item($items, $id, $dir)
{
    $items = array_values($items);
    $i = find_index($items, $id);
    if ($i < 0) {
        return $items;
    }
    if ($dir === 'first') {
        $item = $items[$i];
        array_splice($items, $i, 1);
        array_unshift($items, $item);
        return $items;
    }
    $j = $dir === 'left' ? $i - 1 : ($dir === 'right' ? $i + 1 : $i);
    if ($j < 0 || $j >= count($items) || $j === $i) {
        return $items;
    }
    $tmp = $items[$i];
    $items[$i] = $items[$j];
    $items[$j] = $tmp;
    return $items;
}

function fmt_date($iso)
{
    $t = $iso ? strtotime((string)$iso) : false;
    if (!$t) {
        return '';
    }
    $months = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
    return date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

function cmp_created_desc($a, $b)
{
    return strcmp((string)$b['created'], (string)$a['created']);
}

function icon($name)
{
    $paths = array(
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
        'doc' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 13h7M9 17h5"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v6H4V5h6"/>',
        'logout' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'left' => '<path d="m15 5-7 7 7 7"/>',
        'right' => '<path d="m9 5 7 7-7 7"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
        'replace' => '<path d="M4 12a8 8 0 0 1 14-5.3L20 9"/><path d="M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-14 5.3L4 15"/><path d="M4 20v-5h5"/>',
        'expand' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'warn' => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17v.5"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'play' => '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>',
    );
    $p = isset($paths[$name]) ? $paths[$name] : '';
    return '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
