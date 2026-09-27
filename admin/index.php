<?php
require __DIR__ . '/bootstrap.php';
start_admin_session($config);

$error = '';
$notice = '';
$anchor = '';
$redirectExtra = '';
$ubicacionForm = null;
$tabs = array('resumen', 'galeria', 'secciones', 'blog', 'ubicacion');
$tab = (isset($_GET['tab']) && is_string($_GET['tab'])) ? $_GET['tab'] : 'resumen';
if (!in_array($tab, $tabs, true)) {
    $tab = 'resumen';
}

function clean_body($value, $max)
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', (string)$value);
    if ($s === null) {
        return '';
    }
    $s = trim(str_replace("\r\n", "\n", $s));
    if (function_exists('mb_substr')) {
        return trim(mb_substr($s, 0, $max, 'UTF-8'));
    }
    return trim(substr($s, 0, $max));
}

function accept_pdf($file, $config, $dir, $id)
{
    if (!is_array($file) || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $mime = detect_mime($file['tmp_name']);
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (($mime !== 'application/pdf' && $ext !== 'pdf') || (int)$file['size'] > $config['max_upload_bytes']) {
        return '';
    }
    $filename = $id . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return '';
    }
    @chmod($dir . '/' . $filename, 0644);
    return 'blog/files/' . $filename;
}

function has_file($files)
{
    return !empty($files) && (int)$files[0]['error'] !== UPLOAD_ERR_NO_FILE;
}

if (empty($_SESSION['admin_ok']) && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'login') {
    $user = trim(post_str('user'));
    $pass = post_str('password');
    if (admin_credentials_ok($config, $user, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}

if (!empty($_SESSION['admin_ok']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = '';
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
        $error = 'Los archivos superan el límite del servidor (' . ini_get('post_max_size') . '). Probá subiendo menos por vez.';
    } else {
        require_csrf();
        $action = post_str('action');
    }
    $id = post_str('id');
    $section = post_str('section');
    $mediaTypes = 'JPG, PNG, WEBP o GIF, hasta 80 MB';

    /* ---------- Galería ---------- */
    if ($action === 'gallery_upload') {
        $tab = 'galeria';
        $caption = clean_line(post_str('caption'), 160);
        $new = array();
        $skipped = 0;
        foreach (uploaded_files('media') as $file) {
            if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saved = accept_upload($file, $config, $mediaDir, true);
            if (!$saved) {
                $skipped++;
                continue;
            }
            $new[] = array(
                'id' => $saved['id'],
                'type' => $saved['type'],
                'src' => 'gallery/media/' . $saved['filename'],
                'caption' => $caption,
                'created' => gmdate('c'),
            );
        }
        if ($new) {
            $data = load_json($galleryData);
            $data['items'] = array_merge($new, $data['items']);
            save_json($galleryData, $data);
            $notice = count($new) === 1 ? 'Se publicó 1 archivo en la galería.' : 'Se publicaron ' . count($new) . ' archivos en la galería.';
            $anchor = 'photo-' . $new[0]['id'];
        }
        if ($skipped) {
            $error = ($skipped === 1 ? '1 archivo no se pudo subir' : $skipped . ' archivos no se pudieron subir') . ' (fotos JPG, PNG, WEBP, GIF o videos MP4, WEBM, MOV; hasta 80 MB).';
        } elseif (!$new) {
            $error = 'Elegí al menos una foto o video.';
        }
    }

    if ($action === 'gallery_youtube') {
        $tab = 'galeria';
        $yt = youtube_id(post_str('youtube_url'));
        if ($yt === '') {
            $error = 'El link no es de YouTube. Copiá la dirección del video (por ejemplo https://youtu.be/…).';
        } else {
            $data = load_json($galleryData);
            $newId = new_id();
            array_unshift($data['items'], array(
                'id' => $newId,
                'type' => 'youtube',
                'src' => 'https://img.youtube.com/vi/' . $yt . '/hqdefault.jpg',
                'youtube' => $yt,
                'url' => 'https://www.youtube.com/watch?v=' . $yt,
                'caption' => clean_line(post_str('caption'), 160),
                'created' => gmdate('c'),
            ));
            save_json($galleryData, $data);
            $notice = 'Video de YouTube agregado a la galería.';
            $anchor = 'photo-' . $newId;
        }
    }

    if ($action === 'gallery_caption') {
        $tab = 'galeria';
        $data = load_json($galleryData);
        $i = find_index($data['items'], $id);
        if ($i < 0) {
            $error = 'No se encontró la foto.';
        } else {
            $data['items'][$i]['caption'] = clean_line(post_str('caption'), 160);
            save_json($galleryData, $data);
            $notice = 'Epígrafe guardado.';
            $anchor = 'photo-' . $id;
        }
    }

    if ($action === 'gallery_move') {
        $tab = 'galeria';
        $dir = post_str('dir');
        $data = load_json($galleryData);
        if (find_index($data['items'], $id) < 0) {
            $error = 'No se encontró la foto.';
        } else {
            $data['items'] = move_item($data['items'], $id, $dir);
            save_json($galleryData, $data);
            $notice = $dir === 'first' ? 'Foto destacada: ahora es la primera de la galería.' : 'Orden actualizado.';
            $anchor = 'photo-' . $id;
        }
    }

    if ($action === 'gallery_replace') {
        $tab = 'galeria';
        $data = load_json($galleryData);
        $i = find_index($data['items'], $id);
        $files = uploaded_files('photo');
        if ($i < 0) {
            $error = 'No se encontró la foto.';
        } elseif (!has_file($files)) {
            $error = 'Elegí el archivo nuevo.';
        } else {
            $saved = accept_upload($files[0], $config, $mediaDir, true);
            if (!$saved) {
                $error = 'No se pudo reemplazar (fotos JPG, PNG, WEBP, GIF o videos MP4, WEBM, MOV; hasta 80 MB).';
            } else {
                delete_media($root, isset($data['items'][$i]['src']) ? $data['items'][$i]['src'] : '', 'gallery/media/');
                unset($data['items'][$i]['youtube'], $data['items'][$i]['url']);
                $data['items'][$i]['type'] = $saved['type'];
                $data['items'][$i]['src'] = 'gallery/media/' . $saved['filename'];
                $data['items'][$i]['created'] = gmdate('c');
                save_json($galleryData, $data);
                $notice = 'Foto reemplazada. Mantiene su lugar y su epígrafe.';
                $anchor = 'photo-' . $id;
            }
        }
    }

    if ($action === 'gallery_delete') {
        $tab = 'galeria';
        $data = load_json($galleryData);
        $i = find_index($data['items'], $id);
        if ($i < 0) {
            $error = 'No se encontró la foto.';
        } else {
            delete_media($root, isset($data['items'][$i]['src']) ? $data['items'][$i]['src'] : '', 'gallery/media/');
            array_splice($data['items'], $i, 1);
            save_json($galleryData, $data);
            $notice = 'Eliminado de la galería.';
        }
    }

    /* ---------- Fotos de secciones ---------- */
    if (strpos($action, 'collage_') === 0) {
        $tab = 'secciones';
        $anchor = 'sec-' . $section;
        if (!isset($collageSections[$section])) {
            $error = 'Sección inválida.';
            $action = '';
            $anchor = '';
        }
    }

    if ($action === 'collage_upload') {
        $data = load_collage($collageData, $collageSections);
        $added = 0;
        $skipped = 0;
        foreach (uploaded_files('photos') as $file) {
            if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $saved = accept_upload($file, $config, $collageMedia, false);
            if (!$saved) {
                $skipped++;
                continue;
            }
            $data[$section][] = array(
                'id' => $saved['id'],
                'src' => 'collage/media/' . $saved['filename'],
                'created' => gmdate('c'),
            );
            $added++;
        }
        if ($added) {
            save_json($collageData, $data);
            $notice = ($added === 1 ? '1 foto agregada' : $added . ' fotos agregadas') . ' a «' . $collageSections[$section] . '».';
        }
        if ($skipped) {
            $error = ($skipped === 1 ? '1 archivo no se pudo subir' : $skipped . ' archivos no se pudieron subir') . ' (' . $mediaTypes . ').';
        } elseif (!$added) {
            $error = 'Elegí al menos una foto.';
        }
    }

    if ($action === 'collage_move') {
        $data = load_collage($collageData, $collageSections);
        if (find_index($data[$section], $id) < 0) {
            $error = 'No se encontró la foto.';
        } else {
            $data[$section] = move_item($data[$section], $id, post_str('dir'));
            save_json($collageData, $data);
            $notice = 'Orden actualizado.';
            $anchor = 'photo-' . $id;
        }
    }

    if ($action === 'collage_replace') {
        $data = load_collage($collageData, $collageSections);
        $i = find_index($data[$section], $id);
        $files = uploaded_files('photo');
        if ($i < 0) {
            $error = 'No se encontró la foto.';
        } elseif (!has_file($files)) {
            $error = 'Elegí el archivo nuevo.';
        } else {
            $saved = accept_upload($files[0], $config, $collageMedia, false);
            if (!$saved) {
                $error = 'No se pudo reemplazar (' . $mediaTypes . ').';
            } else {
                delete_media($root, isset($data[$section][$i]['src']) ? $data[$section][$i]['src'] : '', 'collage/media/');
                $data[$section][$i]['src'] = 'collage/media/' . $saved['filename'];
                $data[$section][$i]['created'] = gmdate('c');
                save_json($collageData, $data);
                $notice = 'Foto reemplazada. Mantiene su lugar.';
                $anchor = 'photo-' . $id;
            }
        }
    }

    if ($action === 'collage_delete') {
        $data = load_collage($collageData, $collageSections);
        $i = find_index($data[$section], $id);
        if ($i < 0) {
            $error = 'No se encontró la foto.';
        } else {
            delete_media($root, isset($data[$section][$i]['src']) ? $data[$section][$i]['src'] : '', 'collage/media/');
            array_splice($data[$section], $i, 1);
            save_json($collageData, $data);
            $notice = 'Foto eliminada de «' . $collageSections[$section] . '».';
        }
    }

    /* ---------- Blog ---------- */
    if ($action === 'blog_create' || $action === 'blog_update') {
        $tab = 'blog';
        $kind = post_str('kind');
        if (!in_array($kind, array('nota', 'noticia', 'pdf'), true)) {
            $kind = 'nota';
        }
        $title = clean_line(post_str('title'), 160);
        $body = clean_body(post_str('body'), 8000);
        $files = uploaded_files('pdf');
        $data = load_json($blogData);

        if ($action === 'blog_create') {
            if ($title === '') {
                $error = 'El título es obligatorio.';
            } else {
                $newId = new_id();
                $entry = array(
                    'id' => $newId,
                    'type' => $kind,
                    'title' => $title,
                    'body' => $body,
                    'file' => null,
                    'created' => gmdate('c'),
                );
                if ($kind === 'pdf') {
                    $entry['file'] = has_file($files) ? accept_pdf($files[0], $config, $blogFiles, $newId) : '';
                    if ($entry['file'] === '') {
                        $error = has_file($files) ? 'El archivo debe ser un PDF de hasta 80 MB.' : 'Adjuntá un PDF.';
                    }
                }
                if ($error === '') {
                    array_unshift($data['items'], $entry);
                    save_json($blogData, $data);
                    $notice = 'Publicado en el blog.';
                    $anchor = 'post-' . $newId;
                }
            }
        } else {
            $i = find_index($data['items'], $id);
            $redirectExtra = '&edit=' . rawurlencode($id);
            if ($i < 0) {
                $error = 'No se encontró la publicación.';
                $redirectExtra = '';
            } elseif ($title === '') {
                $error = 'El título es obligatorio.';
            } else {
                $entry = $data['items'][$i];
                $oldFile = isset($entry['file']) ? (string)$entry['file'] : '';
                $newFile = '';
                if ($kind === 'pdf' && has_file($files)) {
                    $newFile = accept_pdf($files[0], $config, $blogFiles, new_id());
                    if ($newFile === '') {
                        $error = 'El archivo debe ser un PDF de hasta 80 MB.';
                    }
                } elseif ($kind === 'pdf' && $oldFile === '') {
                    $error = 'Adjuntá un PDF.';
                }
                if ($error === '') {
                    if ($newFile !== '') {
                        delete_media($root, $oldFile, 'blog/files/');
                        $entry['file'] = $newFile;
                    } elseif ($kind !== 'pdf' && $oldFile !== '') {
                        delete_media($root, $oldFile, 'blog/files/');
                        $entry['file'] = null;
                    }
                    $entry['type'] = $kind;
                    $entry['title'] = $title;
                    $entry['body'] = $body;
                    $data['items'][$i] = $entry;
                    save_json($blogData, $data);
                    $notice = 'Publicación actualizada.';
                    $anchor = 'post-' . $id;
                    $redirectExtra = '';
                }
            }
        }
    }

    if ($action === 'blog_delete') {
        $tab = 'blog';
        $data = load_json($blogData);
        $i = find_index($data['items'], $id);
        if ($i < 0) {
            $error = 'No se encontró la publicación.';
        } else {
            delete_media($root, isset($data['items'][$i]['file']) ? $data['items'][$i]['file'] : '', 'blog/files/');
            array_splice($data['items'], $i, 1);
            save_json($blogData, $data);
            $notice = 'Publicación eliminada.';
        }
    }

    /* ---------- Ubicación ---------- */
    if ($action === 'ubicacion_save') {
        $tab = 'ubicacion';
        $ubicacionForm = array(
            'place_name' => clean_line(post_str('place_name'), 120),
            'location_label' => clean_line(post_str('location_label'), 120),
            'address_lines' => array(
                clean_line(post_str('address_1'), 160),
                clean_line(post_str('address_2'), 160),
            ),
            'map_query' => clean_line(post_str('map_query'), 200),
        );
        if ($ubicacionForm['place_name'] === '') {
            $error = 'El nombre del lugar es obligatorio.';
        } elseif ($ubicacionForm['address_lines'][0] === '' && $ubicacionForm['address_lines'][1] === '') {
            $error = 'Completá al menos una línea de dirección.';
        } else {
            $ubicacionForm['address_lines'] = array_values(array_filter($ubicacionForm['address_lines'], 'strlen'));
            if (save_json($ubicacionData, $ubicacionForm)) {
                $notice = 'Ubicación guardada. Ya se ve en la portada.';
                $ubicacionForm = null;
            } else {
                $error = 'No se pudo guardar la ubicación.';
            }
        }
    }

    if (!($action === 'ubicacion_save' && $error !== '')) {
        $_SESSION['flash'] = array('notice' => $notice, 'error' => $error);
        header('Location: index.php?tab=' . rawurlencode($tab) . $redirectExtra . ($anchor !== '' ? '#' . $anchor : ''));
        exit;
    }
}

if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if ($notice === '' && !empty($flash['notice'])) {
        $notice = (string)$flash['notice'];
    }
    if ($error === '' && !empty($flash['error'])) {
        $error = (string)$flash['error'];
    }
}

$loggedIn = !empty($_SESSION['admin_ok']);
$csrf = $loggedIn ? csrf_token() : '';
$editId = (isset($_GET['edit']) && is_string($_GET['edit'])) ? $_GET['edit'] : '';

/* ---------- Datos para la vista ---------- */
$galleryView = array();
$collageView = array();
$blogItems = array();
$lowList = array();
$recent = array();
$collageTotal = 0;
if ($loggedIn) {
    $gallery = load_json($galleryData);
    $total = count($gallery['items']);
    $pos = 0;
    foreach ($gallery['items'] as $item) {
        if (!is_array($item) || empty($item['src'])) {
            continue;
        }
        $pos++;
        $type = (isset($item['type']) && in_array($item['type'], array('video', 'youtube'), true)) ? $item['type'] : 'image';
        $size = $type === 'image' ? media_size($root, $item['src']) : null;
        $galleryView[] = array(
            'id' => isset($item['id']) ? (string)$item['id'] : '',
            'kind' => 'gallery',
            'section' => '',
            'type' => $type,
            'src' => (string)$item['src'],
            'youtube' => ($type === 'youtube' && isset($item['youtube'])) ? (string)$item['youtube'] : '',
            'caption' => isset($item['caption']) ? (string)$item['caption'] : '',
            'created' => isset($item['created']) ? (string)$item['created'] : '',
            'size' => $size,
            'low' => is_low_quality($size, $qualityRules['gallery']),
            'pos' => $pos,
            'total' => $total,
            'hint' => $qualityRules['gallery']['hint'],
            'where' => 'Galería',
            'tab' => 'galeria',
        );
    }

    $collage = load_collage($collageData, $collageSections);
    foreach ($collageSections as $key => $label) {
        $collageView[$key] = array();
        $total = count($collage[$key]);
        $pos = 0;
        foreach ($collage[$key] as $item) {
            if (!is_array($item) || empty($item['src'])) {
                continue;
            }
            $pos++;
            $size = media_size($root, $item['src']);
            $collageView[$key][] = array(
                'id' => isset($item['id']) ? (string)$item['id'] : '',
                'kind' => 'collage',
                'section' => $key,
                'type' => 'image',
                'src' => (string)$item['src'],
                'youtube' => '',
                'caption' => '',
                'created' => isset($item['created']) ? (string)$item['created'] : '',
                'size' => $size,
                'low' => is_low_quality($size, $qualityRules['collage']),
                'pos' => $pos,
                'total' => $total,
                'hint' => $qualityRules['collage']['hint'],
                'where' => $label,
                'tab' => 'secciones',
            );
        }
        $collageTotal += count($collageView[$key]);
    }

    $all = $galleryView;
    foreach ($collageView as $list) {
        $all = array_merge($all, $list);
    }
    foreach ($all as $v) {
        if ($v['low']) {
            $lowList[] = $v;
        }
        if ($v['created'] !== '' && $v['type'] === 'image') {
            $recent[] = $v;
        }
    }
    usort($recent, 'cmp_created_desc');
    $recent = array_slice($recent, 0, 8);

    $blog = load_json($blogData);
    foreach ($blog['items'] as $item) {
        if (is_array($item) && isset($item['id'])) {
            $blogItems[] = $item;
        }
    }
}
$galleryLow = 0;
foreach ($galleryView as $v) {
    if ($v['low']) {
        $galleryLow++;
    }
}

$ubicacionSaved = load_ubicacion($ubicacionData, $ubicacionDefaults);
$ubicacion = !empty($ubicacionForm) ? $ubicacionForm : $ubicacionSaved;
$ubicacionLines = array_pad($ubicacion['address_lines'], 2, '');
$ubicacionMapQ = ubicacion_map_query($ubicacionSaved);

$editPost = null;
if ($editId !== '') {
    foreach ($blogItems as $item) {
        if ((string)$item['id'] === $editId) {
            $editPost = $item;
        }
    }
}

$navItems = array(
    'resumen' => array('Resumen', 'home', ''),
    'galeria' => array('Galería', 'image', count($galleryView)),
    'secciones' => array('Fotos secciones', 'layers', $collageTotal),
    'blog' => array('Blog', 'doc', count($blogItems)),
    'ubicacion' => array('Ubicación', 'pin', ''),
);
$blogTypes = array('nota' => 'Nota', 'noticia' => 'Noticia', 'pdf' => 'PDF');

function photo_res($v)
{
    if ($v['size']) {
        return $v['size']['w'] . ' × ' . $v['size']['h'];
    }
    if ($v['type'] === 'youtube') {
        return 'YouTube';
    }
    return $v['type'] === 'video' ? 'Video' : '';
}

function action_form($v, $act, $fields, $csrf, $iconName, $label, $cls, $disabled, $confirm)
{
    ?>
    <form method="post"<?php if ($confirm !== ''): ?> data-confirm="<?= h($confirm) ?>"<?php endif; ?>>
      <input type="hidden" name="action" value="<?= h($v['kind'] . '_' . $act) ?>" />
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
      <input type="hidden" name="id" value="<?= h($v['id']) ?>" />
      <?php if ($v['section'] !== ''): ?><input type="hidden" name="section" value="<?= h($v['section']) ?>" /><?php endif; ?>
      <?php foreach ($fields as $k => $val): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($val) ?>" /><?php endforeach; ?>
      <button type="submit" class="icon-btn<?= $cls !== '' ? ' ' . h($cls) : '' ?>" title="<?= h($label) ?>" aria-label="<?= h($label) ?>"<?php if ($disabled): ?> disabled<?php endif; ?>><?= icon($iconName) ?></button>
    </form>
    <?php
}

function photo_card($v, $csrf)
{
    $isGallery = $v['kind'] === 'gallery';
    $res = photo_res($v);
    $date = fmt_date($v['created']);
    ?>
    <li class="photo<?= $v['low'] ? ' is-low' : '' ?>" id="photo-<?= h($v['id']) ?>" data-photo
      data-id="<?= h($v['id']) ?>" data-kind="<?= h($v['kind']) ?>" data-section="<?= h($v['section']) ?>"
      data-type="<?= h($v['type']) ?>" data-src="<?= h(media_url($v['src'])) ?>" data-youtube="<?= h($v['youtube']) ?>" data-caption="<?= h($v['caption']) ?>"
      data-res="<?= h($res) ?>" data-date="<?= h($date) ?>" data-low="<?= $v['low'] ? '1' : '' ?>"
      data-hint="<?= h($v['hint']) ?>" data-where="<?= h($v['where']) ?>" data-pos="<?= (int)$v['pos'] ?>" data-total="<?= (int)$v['total'] ?>">
      <button type="button" class="photo-thumb" data-open aria-label="Ver foto <?= (int)$v['pos'] ?>">
        <?php if ($v['type'] === 'video'): ?>
          <video src="/<?= h($v['src']) ?>#t=0.5" muted playsinline preload="metadata"></video>
          <span class="chip chip-dark chip-br">Video</span>
        <?php else: ?>
          <img src="<?= h(media_url($v['src'])) ?>" alt="" loading="lazy" />
          <?php if ($v['type'] === 'youtube'): ?><span class="chip chip-dark chip-br"><?= icon('play') ?>YouTube</span><?php endif; ?>
        <?php endif; ?>
        <span class="photo-pos"><?= (int)$v['pos'] ?></span>
        <?php if ($isGallery && $v['pos'] === 1): ?><span class="chip chip-accent">Destacada</span><?php endif; ?>
        <span class="photo-zoom"><?= icon('expand') ?></span>
      </button>
      <div class="photo-body">
        <div class="photo-meta">
          <span class="res"><?= h($res !== '' ? $res : '—') ?></span>
          <?php if ($v['low']): ?><span class="badge-warn" title="<?= h($v['hint']) ?>"><?= icon('warn') ?>Baja calidad</span><?php endif; ?>
        </div>
        <?php if ($isGallery): ?>
          <p class="photo-caption<?= $v['caption'] === '' ? ' is-empty' : '' ?>"><?= h($v['caption'] !== '' ? $v['caption'] : 'Sin epígrafe') ?></p>
        <?php endif; ?>
        <?php if ($date !== ''): ?><p class="photo-date">Subida el <?= h($date) ?></p><?php endif; ?>
      </div>
      <div class="photo-actions">
        <?php action_form($v, 'move', array('dir' => 'left'), $csrf, 'left', 'Mover a la izquierda', '', $v['pos'] <= 1, ''); ?>
        <?php action_form($v, 'move', array('dir' => 'right'), $csrf, 'right', 'Mover a la derecha', '', $v['pos'] >= $v['total'], ''); ?>
        <?php if ($isGallery): ?>
          <?php action_form($v, 'move', array('dir' => 'first'), $csrf, 'star', $v['pos'] === 1 ? 'Ya es la destacada' : 'Destacar (poner primera)', '', $v['pos'] === 1, ''); ?>
        <?php endif; ?>
        <button type="button" class="icon-btn" data-open data-focus="caption" title="Editar" aria-label="Editar"><?= icon('edit') ?></button>
        <span class="spacer"></span>
        <?php action_form($v, 'delete', array(), $csrf, 'trash', 'Eliminar', 'danger', false, $isGallery ? '¿Eliminar este archivo de la galería? No se puede deshacer.' : '¿Eliminar esta foto de la sección? No se puede deshacer.'); ?>
      </div>
    </li>
    <?php
}

function dropzone($name, $multiple, $accept, $title, $hint, $minW, $minH, $inputId)
{
    ?>
    <label class="dropzone" data-dropzone data-min-w="<?= (int)$minW ?>" data-min-h="<?= (int)$minH ?>">
      <input type="file" id="<?= h($inputId) ?>" name="<?= h($name) ?>"<?php if ($multiple): ?> multiple<?php endif; ?> accept="<?= h($accept) ?>" class="sr-only" data-dz-input />
      <span class="dz-icon"><?= icon('upload') ?></span>
      <span class="dz-title"><?= h($title) ?> <span class="dz-link">o elegí archivos</span></span>
      <span class="dz-hint"><?= h($hint) ?></span>
    </label>
    <ul class="dz-previews" data-dz-previews hidden></ul>
    <?php
}

function empty_state($title, $text, $pickId)
{
    ?>
    <div class="empty-state">
      <span class="empty-icon"><?= icon('image') ?></span>
      <p class="empty-title"><?= h($title) ?></p>
      <p class="empty-text"><?= h($text) ?></p>
      <?php if ($pickId !== ''): ?><button type="button" class="btn btn-secondary" data-pick="<?= h($pickId) ?>"><?= icon('upload') ?>Subir fotos</button><?php endif; ?>
    </div>
    <?php
}

$imageAccept = 'image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif';
$mediaAccept = $imageAccept . ',video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="theme-color" content="#0a0b0c" />
  <title>Administrar — Honshitsu Dojo</title>
  <link rel="stylesheet" href="style.css?v=11" />
</head>
<body class="<?= $loggedIn ? 'is-app' : 'is-login' ?>">
<?php if (!$loggedIn): ?>
  <main class="login">
    <div class="login-card">
      <div class="login-brand">
        <span class="hanko" aria-hidden="true">管理</span>
        <div>
          <p class="login-title">Honshitsu Dojo</p>
          <p class="login-sub">Administración del sitio</p>
        </div>
      </div>
      <?php if ($error): ?><p class="inline-error"><?= h($error) ?></p><?php endif; ?>
      <form method="post" action="index.php" autocomplete="on" class="stack">
        <input type="hidden" name="action" value="login" />
        <label class="field">
          <span class="field-label">Usuario</span>
          <input type="text" name="user" required autocomplete="username" autofocus />
        </label>
        <label class="field">
          <span class="field-label">Contraseña</span>
          <input type="password" name="password" required value="" autocomplete="current-password" />
        </label>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
      </form>
    </div>
    <a class="login-back" href="/">← Volver al sitio</a>
  </main>
<?php else: ?>
  <div class="shell">
    <aside class="sidebar">
      <a class="brand" href="?tab=resumen">
        <span class="hanko" aria-hidden="true">管理</span>
        <span class="brand-text"><strong>Honshitsu Dojo</strong><small>Administración</small></span>
      </a>
      <nav class="nav" aria-label="Secciones del panel">
        <p class="nav-label">Contenido</p>
        <?php foreach ($navItems as $key => $nav): ?>
          <a class="nav-item<?= $tab === $key ? ' on' : '' ?>" href="?tab=<?= h($key) ?>"<?php if ($tab === $key): ?> aria-current="page"<?php endif; ?>>
            <?= icon($nav[1]) ?><span class="nav-text"><?= h($nav[0]) ?></span>
            <?php if ($nav[2] !== ''): ?><span class="count"><?= (int)$nav[2] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-foot">
        <a class="nav-item" href="/" target="_blank" rel="noopener" title="Ver sitio"><?= icon('external') ?><span class="nav-text">Ver sitio</span></a>
        <a class="nav-item" href="logout.php" title="Salir"><?= icon('logout') ?><span class="nav-text">Salir</span></a>
      </div>
    </aside>

    <main class="main" id="main">
      <?php if ($tab === 'resumen'): ?>
        <header class="page-head">
          <div>
            <p class="eyebrow">Panel</p>
            <h1>Resumen</h1>
            <p class="page-sub">Todo el contenido del sitio de un vistazo.</p>
          </div>
          <div class="page-actions">
            <a class="btn btn-secondary" href="/" target="_blank" rel="noopener"><?= icon('external') ?>Ver sitio</a>
            <a class="btn btn-primary" href="?tab=galeria#upload"><?= icon('upload') ?>Subir fotos</a>
          </div>
        </header>

        <div class="stats">
          <a class="stat" href="?tab=galeria">
            <span class="stat-label"><?= icon('image') ?>Galería</span>
            <span class="stat-value"><?= count($galleryView) ?></span>
            <span class="stat-sub"><?= count($galleryView) === 1 ? 'archivo publicado' : 'archivos publicados' ?><?php if ($galleryLow): ?> · <em class="warn-text"><?= $galleryLow ?> en baja calidad</em><?php endif; ?></span>
          </a>
          <a class="stat" href="?tab=secciones">
            <span class="stat-label"><?= icon('layers') ?>Fotos secciones</span>
            <span class="stat-value"><?= $collageTotal ?></span>
            <span class="stat-sub"><?php $parts = array(); foreach ($collageSections as $key => $label) { $parts[] = ($key === 'karate' ? 'Karate' : ($key === 'historia' ? 'Historia' : $label)) . ' ' . count($collageView[$key]); } echo h(implode(' · ', $parts)); ?></span>
          </a>
          <a class="stat" href="?tab=blog">
            <span class="stat-label"><?= icon('doc') ?>Blog</span>
            <span class="stat-value"><?= count($blogItems) ?></span>
            <span class="stat-sub"><?= $blogItems ? 'Última: ' . h(isset($blogItems[0]['title']) ? $blogItems[0]['title'] : '') : 'Sin publicaciones todavía' ?></span>
          </a>
          <a class="stat" href="?tab=ubicacion">
            <span class="stat-label"><?= icon('pin') ?>Ubicación</span>
            <span class="stat-value stat-value-sm"><?= h($ubicacionSaved['place_name']) ?></span>
            <span class="stat-sub"><?= h(implode(' · ', $ubicacionSaved['address_lines'])) ?></span>
          </a>
        </div>

        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>Fotos con baja calidad <span class="count-pill<?= $lowList ? ' is-warn' : '' ?>"><?= count($lowList) ?></span></h2>
              <p class="panel-sub"><?= $lowList ? 'Se ven borrosas en pantallas grandes. Reemplazalas por versiones más grandes desde el visor de cada foto.' : 'Todas las fotos tienen buena resolución.' ?></p>
            </div>
          </div>
          <?php if ($lowList): ?>
            <ul class="rows">
              <?php foreach ($lowList as $v): ?>
                <li class="row">
                  <img class="row-thumb" src="<?= h(media_url($v['src'])) ?>" alt="" loading="lazy" />
                  <div class="row-main">
                    <p class="row-title"><?= h($v['where']) ?> · Foto <?= (int)$v['pos'] ?></p>
                    <p class="row-sub"><span class="badge-warn"><?= icon('warn') ?><?= h(photo_res($v)) ?></span> <?= h($v['hint']) ?></p>
                  </div>
                  <a class="btn btn-ghost btn-sm" href="?tab=<?= h($v['tab']) ?>#photo-<?= h($v['id']) ?>">Ver foto</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>Subidas recientes</h2>
              <p class="panel-sub">Las últimas fotos cargadas en la galería y en las secciones.</p>
            </div>
          </div>
          <?php if ($recent): ?>
            <ul class="strip">
              <?php foreach ($recent as $v): ?>
                <li>
                  <a href="?tab=<?= h($v['tab']) ?>#photo-<?= h($v['id']) ?>" title="<?= h($v['where']) ?>">
                    <img src="/<?= h($v['src']) ?>" alt="" loading="lazy" />
                    <span class="strip-label"><?= h($v['where']) ?></span>
                    <span class="strip-date"><?= h(fmt_date($v['created'])) ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="empty-inline">Todavía no subiste fotos desde el panel.</p>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'galeria'): ?>
        <header class="page-head">
          <div>
            <p class="eyebrow">Contenido</p>
            <h1>Galería</h1>
            <p class="page-sub">La primera foto es la destacada de la página Galería. Tocá una foto para verla grande, editar el epígrafe, reemplazarla o borrarla.</p>
          </div>
          <div class="page-actions">
            <button type="button" class="btn btn-primary" data-pick="dz-gallery"><?= icon('upload') ?>Subir fotos</button>
          </div>
        </header>

        <section class="panel" id="upload">
          <form method="post" enctype="multipart/form-data" data-upload-form>
            <input type="hidden" name="action" value="gallery_upload" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <?php dropzone('media[]', true, $mediaAccept, 'Arrastrá fotos o videos acá', 'Fotos JPG, PNG, WEBP · videos MP4, WEBM · hasta 80 MB. ' . $qualityRules['gallery']['hint'], 1200, 0, 'dz-gallery'); ?>
            <div class="upload-row">
              <label class="field grow">
                <span class="field-label">Epígrafe (opcional, se aplica a todas)</span>
                <input type="text" name="caption" maxlength="160" placeholder="Ej: Entrenamiento de los martes" />
              </label>
              <button type="submit" class="btn btn-primary" data-dz-submit disabled>Publicar</button>
            </div>
          </form>
          <form method="post" class="yt-form" id="youtube">
            <input type="hidden" name="action" value="gallery_youtube" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <p class="yt-title"><?= icon('play') ?>O agregá un video de YouTube</p>
            <div class="upload-row">
              <label class="field grow">
                <span class="field-label">Link del video</span>
                <input type="url" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=… o https://youtu.be/…" />
              </label>
              <label class="field grow">
                <span class="field-label">Epígrafe (opcional)</span>
                <input type="text" name="caption" maxlength="160" placeholder="Ej: Exhibición de kata" />
              </label>
              <button type="submit" class="btn btn-primary">Agregar video</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head">
            <div>
              <h2>En la galería <span class="count-pill"><?= count($galleryView) ?></span></h2>
              <p class="panel-sub">Se muestran en este orden en el sitio.<?php if ($galleryLow): ?> <span class="warn-text"><?= $galleryLow ?> con baja calidad.</span><?php endif; ?></p>
            </div>
          </div>
          <?php if ($galleryView): ?>
            <ul class="photo-grid" data-group="gallery">
              <?php foreach ($galleryView as $v) { photo_card($v, $csrf); } ?>
            </ul>
          <?php else: ?>
            <?php empty_state('La galería está vacía', 'Subí las primeras fotos o videos del dojo.', 'dz-gallery'); ?>
          <?php endif; ?>
        </section>

      <?php elseif ($tab === 'secciones'): ?>
        <header class="page-head">
          <div>
            <p class="eyebrow">Portada</p>
            <h1>Fotos secciones</h1>
            <p class="page-sub">Fotos al costado de los textos de la portada (solo en computadora). Se ven de a 3 y se recorren con flechas. Ideal: fotos verticales.</p>
          </div>
        </header>

        <?php foreach ($collageSections as $key => $label): ?>
          <section class="panel" id="sec-<?= h($key) ?>">
            <div class="panel-head">
              <div>
                <h2><?= h($label) ?> <span class="count-pill"><?= count($collageView[$key]) ?></span></h2>
                <p class="panel-sub"><?= h($qualityRules['collage']['hint']) ?></p>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" data-pick="dz-<?= h($key) ?>"><?= icon('upload') ?>Subir fotos</button>
            </div>
            <form method="post" enctype="multipart/form-data" data-upload-form class="upload-compact">
              <input type="hidden" name="action" value="collage_upload" />
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
              <input type="hidden" name="section" value="<?= h($key) ?>" />
              <?php dropzone('photos[]', true, $imageAccept, 'Arrastrá fotos acá', 'JPG, PNG, WEBP o GIF · hasta 80 MB', 0, 900, 'dz-' . $key); ?>
              <div class="upload-row" data-dz-row hidden>
                <button type="submit" class="btn btn-primary" data-dz-submit disabled>Subir a «<?= h($label) ?>»</button>
              </div>
            </form>
            <?php if ($collageView[$key]): ?>
              <ul class="photo-grid photo-grid-tall" data-group="<?= h($key) ?>">
                <?php foreach ($collageView[$key] as $v) { photo_card($v, $csrf); } ?>
              </ul>
            <?php else: ?>
              <?php empty_state('Sin fotos', 'La sección se muestra solo con el texto.', 'dz-' . $key); ?>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>

      <?php elseif ($tab === 'blog'): ?>
        <header class="page-head">
          <div>
            <p class="eyebrow">Contenido</p>
            <h1>Blog</h1>
            <p class="page-sub">Notas, noticias y documentos PDF. La más reciente aparece primero.</p>
          </div>
          <div class="page-actions">
            <a class="btn btn-primary" href="?tab=blog#blog-form" data-focus-title><?= icon('plus') ?>Nueva publicación</a>
          </div>
        </header>

        <?php
        $formPost = $editPost ? $editPost : array('id' => '', 'type' => 'nota', 'title' => '', 'body' => '', 'file' => null);
        $formType = isset($formPost['type']) && isset($blogTypes[$formPost['type']]) ? $formPost['type'] : 'nota';
        ?>
        <section class="panel<?= $editPost ? ' is-editing' : '' ?>" id="blog-form">
          <div class="panel-head">
            <div>
              <h2><?= $editPost ? 'Editar publicación' : 'Nueva publicación' ?></h2>
              <?php if ($editPost): ?><p class="panel-sub">Publicada el <?= h(fmt_date(isset($editPost['created']) ? $editPost['created'] : '')) ?></p><?php endif; ?>
            </div>
            <?php if ($editPost): ?><a class="btn btn-ghost btn-sm" href="?tab=blog">Cancelar</a><?php endif; ?>
          </div>
          <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="action" value="<?= $editPost ? 'blog_update' : 'blog_create' ?>" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <?php if ($editPost): ?><input type="hidden" name="id" value="<?= h($formPost['id']) ?>" /><?php endif; ?>
            <div class="field">
              <span class="field-label">Tipo</span>
              <div class="segmented" role="radiogroup">
                <?php foreach ($blogTypes as $val => $label): ?>
                  <label><input type="radio" name="kind" value="<?= h($val) ?>"<?php if ($formType === $val): ?> checked<?php endif; ?> /><span><?= h($label) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
            <label class="field">
              <span class="field-label">Título</span>
              <input type="text" name="title" required maxlength="160" value="<?= h(isset($formPost['title']) ? $formPost['title'] : '') ?>" data-title-input />
            </label>
            <label class="field">
              <span class="field-label">Texto <span class="muted">(opcional en PDF)</span></span>
              <textarea name="body" rows="6" maxlength="8000"><?= h(isset($formPost['body']) ? $formPost['body'] : '') ?></textarea>
            </label>
            <div class="field" data-pdf-field>
              <span class="field-label">Archivo PDF <span class="muted">(solo tipo PDF)</span></span>
              <?php if (!empty($formPost['file'])): ?>
                <p class="current-file"><?= icon('doc') ?><a href="/<?= h($formPost['file']) ?>" target="_blank" rel="noopener">Ver PDF actual</a> <span class="muted">· elegí otro para reemplazarlo</span></p>
              <?php endif; ?>
              <input type="file" name="pdf" accept="application/pdf,.pdf" class="file-input" />
              <?php if ($editPost && !empty($formPost['file'])): ?><p class="help">Si cambiás el tipo a Nota o Noticia, el PDF se quita.</p><?php endif; ?>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary"><?= $editPost ? 'Guardar cambios' : 'Publicar en el blog' ?></button>
              <?php if ($editPost): ?><a class="btn btn-ghost" href="?tab=blog">Cancelar</a><?php endif; ?>
            </div>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head">
            <div><h2>Publicaciones <span class="count-pill"><?= count($blogItems) ?></span></h2></div>
          </div>
          <?php if (!$blogItems): ?>
            <div class="empty-state">
              <span class="empty-icon"><?= icon('doc') ?></span>
              <p class="empty-title">Sin publicaciones todavía</p>
              <p class="empty-text">Escribí la primera nota o subí un PDF con el formulario de arriba.</p>
            </div>
          <?php else: ?>
            <ul class="post-list">
              <?php foreach ($blogItems as $item): ?>
                <?php
                $pType = isset($item['type']) && isset($blogTypes[$item['type']]) ? $item['type'] : 'nota';
                $pBody = isset($item['body']) ? (string)$item['body'] : '';
                if (function_exists('mb_strlen') && mb_strlen($pBody, 'UTF-8') > 140) {
                    $pBody = mb_substr($pBody, 0, 140, 'UTF-8') . '…';
                } elseif (!function_exists('mb_strlen') && strlen($pBody) > 140) {
                    $pBody = substr($pBody, 0, 140) . '…';
                }
                ?>
                <li class="post<?= $editId === (string)$item['id'] ? ' is-current' : '' ?>" id="post-<?= h($item['id']) ?>">
                  <span class="pill pill-<?= h($pType) ?>"><?= h($blogTypes[$pType]) ?></span>
                  <div class="post-main">
                    <p class="post-title"><?= h(isset($item['title']) ? $item['title'] : '') ?></p>
                    <?php if ($pBody !== ''): ?><p class="post-excerpt"><?= h($pBody) ?></p><?php endif; ?>
                    <p class="post-meta"><?= h(fmt_date(isset($item['created']) ? $item['created'] : '')) ?><?php if (!empty($item['file'])): ?> · <a href="/<?= h($item['file']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php endif; ?></p>
                  </div>
                  <div class="post-actions">
                    <a class="btn btn-ghost btn-sm" href="?tab=blog&amp;edit=<?= h(rawurlencode((string)$item['id'])) ?>#blog-form"><?= icon('edit') ?>Editar</a>
                    <form method="post" data-confirm="¿Eliminar «<?= h(isset($item['title']) ? $item['title'] : '') ?>»? No se puede deshacer.">
                      <input type="hidden" name="action" value="blog_delete" />
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
                      <input type="hidden" name="id" value="<?= h($item['id']) ?>" />
                      <button type="submit" class="btn btn-danger-ghost btn-sm"><?= icon('trash') ?>Eliminar</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

      <?php else: ?>
        <header class="page-head">
          <div>
            <p class="eyebrow">Portada</p>
            <h1>Ubicación</h1>
            <p class="page-sub">Dirección y mapa de la sección «Estamos ubicados en» de la portada.</p>
          </div>
        </header>

        <?php if ($error && $tab === 'ubicacion' && !empty($ubicacionForm)): ?><p class="inline-error"><?= h($error) ?></p><?php endif; ?>
        <div class="split">
          <section class="panel">
            <div class="panel-head"><div><h2>Dirección del dojo</h2></div></div>
            <form method="post" action="?tab=ubicacion" class="form-grid" data-ubicacion-form>
              <input type="hidden" name="action" value="ubicacion_save" />
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
              <label class="field">
                <span class="field-label">Nombre del lugar</span>
                <input type="text" name="place_name" required maxlength="120" value="<?= h($ubicacion['place_name']) ?>" />
              </label>
              <label class="field">
                <span class="field-label">Localidad</span>
                <input type="text" name="location_label" maxlength="120" placeholder="Punta Alta, Argentina" value="<?= h($ubicacion['location_label']) ?>" />
              </label>
              <label class="field">
                <span class="field-label">Dirección · línea 1</span>
                <input type="text" name="address_1" maxlength="160" placeholder="Calle y número, código postal" value="<?= h($ubicacionLines[0]) ?>" />
              </label>
              <label class="field">
                <span class="field-label">Dirección · línea 2</span>
                <input type="text" name="address_2" maxlength="160" placeholder="Ciudad, provincia" value="<?= h($ubicacionLines[1]) ?>" />
              </label>
              <label class="field">
                <span class="field-label">Ubicación en el mapa</span>
                <input type="text" name="map_query" maxlength="200" placeholder="Dirección o coordenadas (ej: -38.8783, -62.0747)" value="<?= h($ubicacion['map_query']) ?>" />
                <span class="help">Lo que se busca en Google Maps: una dirección o coordenadas «latitud, longitud». Si lo dejás vacío se usa la dirección y la localidad.</span>
              </label>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar ubicación</button>
                <button type="button" class="btn btn-secondary" data-map-preview>Probar en el mapa</button>
              </div>
            </form>
          </section>
          <section class="panel map-panel">
            <div class="panel-head"><div><h2>Mapa</h2><p class="panel-sub">Así se busca en Google Maps.</p></div></div>
            <div class="map-preview">
              <iframe id="map-preview" src="https://maps.google.com/maps?q=<?= h(rawurlencode($ubicacionMapQ)) ?>&amp;z=16&amp;hl=es&amp;output=embed" title="Vista previa del mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <p class="map-q"><?= icon('pin') ?><span id="map-preview-q"><?= h($ubicacionMapQ) ?></span></p>
            <a class="btn btn-ghost btn-sm" id="map-preview-link" href="https://www.google.com/maps/search/?api=1&amp;query=<?= h(rawurlencode($ubicacionMapQ)) ?>" target="_blank" rel="noopener"><?= icon('external') ?>Abrir en Google Maps</a>
          </section>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <div class="viewer" id="viewer" hidden>
    <div class="viewer-backdrop" data-viewer-close></div>
    <div class="viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="viewer-pos">
      <div class="viewer-stage">
        <div class="viewer-media" id="viewer-media"></div>
        <button type="button" class="viewer-nav prev" data-viewer-prev aria-label="Anterior"><?= icon('left') ?></button>
        <button type="button" class="viewer-nav next" data-viewer-next aria-label="Siguiente"><?= icon('right') ?></button>
      </div>
      <aside class="viewer-side">
        <div class="viewer-head">
          <div>
            <p class="eyebrow" id="viewer-where"></p>
            <p class="viewer-title" id="viewer-pos"></p>
          </div>
          <button type="button" class="icon-btn" data-viewer-close aria-label="Cerrar"><?= icon('close') ?></button>
        </div>
        <dl class="viewer-info">
          <div><dt>Resolución</dt><dd><span id="viewer-res"></span> <span class="badge-warn" id="viewer-low" hidden><?= icon('warn') ?>Baja calidad</span></dd></div>
          <div><dt>Subida</dt><dd id="viewer-date"></dd></div>
        </dl>
        <p class="viewer-hint" id="viewer-hint" hidden></p>

        <form method="post" class="viewer-caption" data-only="gallery">
          <input type="hidden" name="action" value="gallery_caption" />
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
          <input type="hidden" name="id" data-f="id" />
          <label class="field">
            <span class="field-label">Epígrafe</span>
            <input type="text" name="caption" maxlength="160" data-f="caption" placeholder="Sin epígrafe" />
          </label>
          <button type="submit" class="btn btn-secondary btn-sm">Guardar epígrafe</button>
        </form>

        <p class="viewer-label">Orden</p>
        <div class="viewer-row">
          <form method="post">
            <input type="hidden" name="action" data-act="move" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <input type="hidden" name="id" data-f="id" />
            <input type="hidden" name="section" data-f="section" />
            <input type="hidden" name="dir" value="left" />
            <button type="submit" class="btn btn-secondary btn-sm" data-need="not-first"><?= icon('left') ?>Mover</button>
          </form>
          <form method="post">
            <input type="hidden" name="action" data-act="move" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <input type="hidden" name="id" data-f="id" />
            <input type="hidden" name="section" data-f="section" />
            <input type="hidden" name="dir" value="right" />
            <button type="submit" class="btn btn-secondary btn-sm" data-need="not-last">Mover<?= icon('right') ?></button>
          </form>
          <form method="post" data-only="gallery">
            <input type="hidden" name="action" value="gallery_move" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <input type="hidden" name="id" data-f="id" />
            <input type="hidden" name="dir" value="first" />
            <button type="submit" class="btn btn-secondary btn-sm" data-need="not-first"><?= icon('star') ?>Destacar</button>
          </form>
        </div>

        <p class="viewer-label">Archivo</p>
        <div class="viewer-row">
          <form method="post" enctype="multipart/form-data" data-replace-form>
            <input type="hidden" name="action" data-act="replace" />
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
            <input type="hidden" name="id" data-f="id" />
            <input type="hidden" name="section" data-f="section" />
            <label class="btn btn-secondary btn-sm">
              <?= icon('replace') ?>Reemplazar foto
              <input type="file" name="photo" class="sr-only" data-replace-input />
            </label>
          </form>
          <a class="btn btn-ghost btn-sm" id="viewer-open" href="#" target="_blank" rel="noopener"><?= icon('external') ?>Tamaño real</a>
        </div>

        <form method="post" class="viewer-delete" data-confirm="¿Eliminar esta foto? No se puede deshacer.">
          <input type="hidden" name="action" data-act="delete" />
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
          <input type="hidden" name="id" data-f="id" />
          <input type="hidden" name="section" data-f="section" />
          <button type="submit" class="btn btn-danger-ghost btn-sm btn-block"><?= icon('trash') ?>Eliminar foto</button>
        </form>
      </aside>
    </div>
  </div>
<?php endif; ?>

  <div class="toasts" aria-live="polite">
    <?php if ($notice): ?><div class="toast toast-ok" role="status"><span class="toast-dot"></span><p><?= h($notice) ?></p><button type="button" class="toast-x" aria-label="Cerrar">×</button></div><?php endif; ?>
    <?php if ($error && $loggedIn && empty($ubicacionForm)): ?><div class="toast toast-err" role="alert"><span class="toast-dot"></span><p><?= h($error) ?></p><button type="button" class="toast-x" aria-label="Cerrar">×</button></div><?php endif; ?>
  </div>

  <div class="modal" id="confirm" hidden>
    <div class="modal-backdrop" data-confirm-cancel></div>
    <div class="modal-card" role="alertdialog" aria-modal="true" aria-labelledby="confirm-text">
      <p class="modal-title">Confirmar</p>
      <p class="modal-text" id="confirm-text"></p>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" data-confirm-cancel>Cancelar</button>
        <button type="button" class="btn btn-danger" data-confirm-ok>Eliminar</button>
      </div>
    </div>
  </div>

  <script src="admin.js?v=12"></script>
</body>
</html>
