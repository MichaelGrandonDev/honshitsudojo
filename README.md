# 本質道場 · Honshitsu Dojo

Sitio web oficial de **Honshitsu Dojo** — Karate Do Shorin Ryu, Escuela Miyazato — en Punta Alta, Argentina, con **panel de administración propio** (login, sesiones y gestión de contenido) y un proceso de **QA y pruebas de seguridad** documentado.

🌐 **En producción:** [honshitsudojo.com.ar](https://honshitsudojo.com.ar/)

![Python](https://img.shields.io/badge/Python-3.13-111111?logo=python&logoColor=white)
![Flask](https://img.shields.io/badge/Flask-3.x-111111?logo=flask&logoColor=white)
![Jinja2](https://img.shields.io/badge/Jinja2-3.x-B22222?logo=jinja&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-panel_admin-111111?logo=php&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-B22222?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-111111?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-vanilla-B22222?logo=javascript&logoColor=white)
![JSON](https://img.shields.io/badge/JSON-datos-111111?logo=json&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-.htaccess-B22222?logo=apache&logoColor=white)
![Bash](https://img.shields.io/badge/Bash-QA-111111?logo=gnubash&logoColor=white)

---

## Índice

1. [Sobre el proyecto](#sobre-el-proyecto)
2. [Lenguajes utilizados](#lenguajes-utilizados)
3. [Arquitectura](#arquitectura)
4. [Panel de administración: login y usuarios](#panel-de-administración-login-y-usuarios)
5. [Gestión interna de datos](#gestión-interna-de-datos)
6. [Seguridad aplicada](#seguridad-aplicada)
7. [QA testing](#qa-testing)
8. [Cómo correrlo localmente](#cómo-correrlo-localmente)
9. [Estructura del proyecto](#estructura-del-proyecto)
10. [Qué no incluye este repositorio](#qué-no-incluye-este-repositorio)

---

## Sobre el proyecto

Sitio hecho a medida, sin plantillas ni CMS, con estética minimalista japonesa en negro y rojo. Presenta el dojo, su historia, el significado del nombre, el sensei, los valores, una galería de fotos y videos, un blog con documentos y la ubicación con mapa.

El contenido del día a día (fotos, videos, notas, ubicación) lo carga el dojo desde un **panel de administración propio con login**, sin tocar código.

> **No es WordPress.** No usa CMS ni base de datos: los datos editables se guardan en archivos JSON gestionados por el panel.

---

## Lenguajes utilizados

| Lenguaje | Dónde | Para qué |
|---|---|---|
| **Python 3** | `app.py`, `content.py`, `build_static.py`, `deploy.py`, `qa/check_site.py` | Servidor local, generación del sitio estático, publicación por FTP y QA automatizado. |
| **Jinja2** (plantillas) | `templates/` | Estructura HTML de inicio, galería y blog, con macros reutilizables. |
| **HTML5** | Plantillas y panel | Marcado semántico y accesible (`lang`, un solo `h1`, `alt`, `aria-label`). |
| **CSS3** | `static/css/styles.css`, `admin/style.css` | Diseño responsive con variables CSS, Grid, Flexbox y animaciones. Sin frameworks. |
| **JavaScript** (vanilla, ES6+) | `static/js/main.js`, `admin/admin.js` | Menú, animaciones al hacer scroll, visor de galería (lightbox), carga de datos JSON, collage, mapa y visor del panel. Sin librerías. |
| **PHP** | `admin/` | Panel de administración: login, sesiones, CSRF, subida de archivos y edición de datos. Compatible con PHP 5 y versiones actuales. |
| **JSON** | `gallery/`, `blog/`, `collage/`, `ubicacion/` | Almacenamiento de datos en lugar de una base de datos. |
| **Apache `.htaccess`** | Raíz, `admin/` y carpetas de archivos | HTTPS forzado, caché, bloqueo de archivos sensibles y de ejecución de scripts. |
| **Bash** | `qa/security_checks.sh` | Pruebas de seguridad automatizadas con `curl`. |

### Tecnologías e integraciones

- **Flask 3** — servidor de desarrollo local (única dependencia externa de Python).
- **Google Fonts** — *Noto Sans JP*, *Noto Serif JP*, *Shippori Mincho*.
- **Google Maps** embebido — sección Ubicación y botón «Cómo llegar».
- **YouTube** (`youtube-nocookie.com`) — videos incrustados en la galería.
- **WhatsApp / Instagram** — contacto.
- **HostGator** (Apache + PHP) — hosting con HTTPS.
- **Chrome DevTools Protocol** — pruebas funcionales en navegador.
- **Git + GitHub** — control de versiones.

---

## Arquitectura

```
                 ┌──────────────────────────── Desarrollo ────────────────────────────┐
 content.py ──┐  │                                                                     │
 templates/ ──┼──► build_static.py ──► public/ (HTML estático + assets) ──► deploy.py ─┼─► FTP
 static/ ─────┘  │      ▲                                                              │
                 │      └── descarga los JSON actuales del sitio (datos reales)        │
                 └─────────────────────────────────────────────────────────────────────┘

                 ┌──────────────────────────── Producción ────────────────────────────┐
 Visitante ──────► HTML estático ──► main.js pide los JSON (sin caché) y pinta la galería│
 Dojo (admin) ───► /admin/ (PHP) ──► escribe data.json + guarda archivos en media/     │
                 └─────────────────────────────────────────────────────────────────────┘
```

**Técnicas aplicadas:**

- **Generación estática (SSG):** las páginas se renderizan una vez con Jinja y se sirven como HTML plano, así cargan rápido y el servidor no ejecuta Python.
- **Build con datos en vivo:** `build_static.py` descarga los JSON publicados antes de renderizar, así el HTML generado ya trae el contenido real y no aparece contenido viejo antes de que cargue el JavaScript. Con `HD_OFFLINE=1` usa los datos locales.
- **Hidratación del lado del cliente:** `main.js` vuelve a pedir los JSON con `cache: "no-store"`, así lo que se carga en el panel aparece al instante sin volver a publicar.
- **Cache busting:** los CSS/JS llevan `?v=<hash>` calculado del contenido de los archivos.
- **Deploy seguro:** `deploy.py` no pisa los datos ni los archivos cargados desde el panel (salvo `FTP_SYNC_GALLERY=1`).
- **Mejora progresiva:** sin JavaScript el sitio sigue mostrando contenido e imágenes.

---

## Panel de administración: login y usuarios

El panel en `/admin/` está escrito en **PHP puro** y permite al dojo gestionar el contenido con una interfaz propia:

- **Resumen** con estado de la galería, el blog y el collage.
- **Galería:** subir, reemplazar, ordenar, destacar y eliminar fotos y videos; agregar **videos de YouTube por enlace** (acepta `watch`, `youtu.be`, `shorts`, `embed` y `live`). Visor integrado y aviso de fotos de baja resolución.
- **Blog:** publicar y editar notas, noticias y PDF.
- **Collage:** fotos de las secciones «¿Qué es Karate Do?» y «Un poco de historia».
- **Ubicación:** lugar, dirección y búsqueda del mapa.

**Autenticación y sesiones:**

- Login con usuario y contraseña, comparados con `hash_equals()` (tiempo constante, sin fugas por tiempo de respuesta).
- Credenciales **fuera del código**, en `admin/credentials.php`, que no se sube al repositorio. Si falta o está vacío, **el login se rechaza** en lugar de quedar abierto (*fail closed*).
- `session_regenerate_id(true)` al ingresar, contra la fijación de sesión.
- Cookie de sesión con nombre propio, `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- Cierre de sesión que destruye la sesión en el servidor.
- Alcance actual: **un usuario administrador**, pensado para la persona que gestiona el dojo.

---

## Gestión interna de datos

No hay base de datos: cada sección tiene su propio `data.json`, que solo el panel escribe.

| Archivo | Contenido |
|---|---|
| `gallery/data.json` | Fotos, videos y videos de YouTube: tipo, archivo, descripción, destacado, orden y fecha. |
| `blog/data.json` | Notas y documentos: título, texto, PDF adjunto y fecha. |
| `collage/data.json` | Fotos de las secciones Karate e Historia. |
| `ubicacion/data.json` | Nombre del lugar, dirección y consulta del mapa. |

**Técnicas:**

- **Escritura con bloqueo exclusivo** (`LOCK_EX`) y JSON con formato legible, para que dos guardados simultáneos no se mezclen.
- **Creación automática** de carpetas y JSON vacíos la primera vez.
- **Nombres de archivo generados por el servidor** (identificador aleatorio más la extensión validada); el nombre original del usuario nunca se usa como ruta.
- **Borrado seguro:** al eliminar o reemplazar un elemento se borra también su archivo, solo dentro de la carpeta permitida.
- **Una sola fuente de verdad:** el mismo JSON alimenta al panel, al build estático y al JavaScript del sitio.

---

## Seguridad aplicada

| Riesgo | Medida |
|---|---|
| Robo de sesión / XSS | Toda salida del panel pasa por `h()` (`htmlspecialchars`). Cookies `HttpOnly`. |
| CSRF | Token aleatorio (`random_bytes`) en cada formulario y validado con `hash_equals` en **todos** los POST. |
| Fijación de sesión | `session_regenerate_id(true)` al ingresar. |
| Credenciales expuestas | Archivo aparte, ignorado por Git, bloqueado por `.htaccess`; login que falla cerrado. |
| Subida de archivos maliciosos | Validación doble: **tipo MIME real** (`finfo`) y **extensión** permitida. Límite de 80 MB. |
| Ejecución de scripts subidos | `.htaccess` en `gallery/media/`, `collage/media/` y `blog/files/` que prohíbe PHP, CGI, etc. |
| Path traversal | Rutas rechazadas si contienen `..` o no empiezan con la carpeta esperada. |
| Listado de carpetas | `Options -Indexes` en todas las carpetas de datos. |
| Acceso a archivos internos | `config.php`, `bootstrap.php` y `credentials.php` devuelven 403. |
| Tráfico sin cifrar | Redirección 301 a HTTPS en `.htaccess`. |
| Enlaces de YouTube arbitrarios | Solo se guarda el ID de 11 caracteres validado por expresión regular; se incrusta desde `youtube-nocookie.com`. |

---

## QA testing

El sitio y el panel pasaron por una ronda de QA manual y automatizada, en producción y en el entorno local. Los fallos encontrados se corrigieron y se volvieron a verificar.

### Técnicas aplicadas

| Tipo de prueba | Qué se verificó | Herramienta |
|---|---|---|
| **Smoke test / crawl** | Todas las páginas, enlaces, CSS, JS e imágenes responden 200; los JSON son válidos y cada archivo que referencian existe. | `qa/check_site.py` |
| **Funcionales E2E** | Galería, lightbox (teclado, flechas, Escape), videos y YouTube, collage, mapa, menú, botón de WhatsApp, blog. | Navegador + Chrome DevTools Protocol |
| **Responsive / mobile** | Vista de celular (390×844): sin scroll horizontal, menú y botones usables. | Emulación de dispositivo |
| **Panel admin** | Login, pestañas, visor, validaciones de formularios, confirmaciones antes de borrar. | Navegador |
| **Autenticación** | Acciones sin sesión redirigen al login; credenciales incorrectas se rechazan; la pantalla de ingreso no expone datos. | `qa/security_checks.sh` |
| **CSRF** | POST con token ausente o inválido → rechazado. | `curl` |
| **Exposición de archivos** | `.env`, `.git`, código Python, configuración PHP y credenciales no son accesibles; sin listado de carpetas. | `qa/security_checks.sh` |
| **Validación de entradas** | Enlaces de YouTube válidos e inválidos; tipos de archivo no permitidos. | Manual |
| **Accesibilidad básica** | `lang="es"`, un `h1` por página, `alt` en imágenes, `aria-label` en botones de ícono. | `qa/check_site.py` |
| **Regresión de deploy** | Hash SHA de cada archivo publicado comparado con el build local. | `shasum` + `curl` |
| **Integridad de datos** | Las pruebas que escriben datos toman una copia de los JSON antes y los restauran al terminar. | Manual |
| **Revisión de código** | Compatibilidad con PHP 5, escapado de salidas, validación de subidas, sintaxis JS (`node --check`). | Revisión estática |

### Fallos encontrados y corregidos

- Imagen de la sección Ubicación que faltaba en el servidor → subida y verificada.
- El HTML generado mostraba contenido de ejemplo hasta que cargaba el JavaScript → el build ahora usa los datos en vivo.
- Botón destacado de la galería sin etiqueta accesible → `aria-label` agregado.
- El visor del panel decía «Foto» en los videos → texto corregido.
- La pantalla de login mostraba las credenciales → eliminado; credenciales movidas a un archivo privado.

### Correr las pruebas

```bash
# Crawl de enlaces, recursos, JSON y accesibilidad
python qa/check_site.py https://honshitsudojo.com.ar/

# Pruebas de seguridad no destructivas
bash qa/security_checks.sh https://honshitsudojo.com.ar
```

Ambos scripts terminan con código 1 si encuentran un problema, así que pueden usarse en CI.

---

## Cómo correrlo localmente

Requiere Python 3.

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

python app.py              # http://127.0.0.1:5000
python build_static.py     # genera public/ (HD_OFFLINE=1 para no descargar datos)

cp .env.example .env       # completar datos FTP
python deploy.py           # build + publicación
```

**Panel de administración:** necesita PHP (en el hosting). Antes de publicarlo por primera vez:

```bash
cp admin/credentials.example.php admin/credentials.php
# editar usuario y contraseña
```

**Imágenes:** las fotos del dojo no están en el repositorio (ver abajo). Para correr el sitio localmente hay que colocar imágenes propias en `static/img/` con los nombres que usa `content.py`.

---

## Estructura del proyecto

```
HONSHITSUDOJO/
├── app.py                   # Servidor local Flask
├── content.py               # Textos y datos del sitio
├── build_static.py          # Genera public/ con datos en vivo
├── deploy.py                # Build + FTP
├── passenger_wsgi.py        # Entrada opcional para Passenger
├── requirements.txt
├── templates/               # Jinja2: base, inicio, galería, blog
├── static/
│   ├── css/styles.css
│   ├── js/main.js
│   └── img/                 # Fotos (no incluidas en el repo)
├── admin/
│   ├── index.php            # Panel: rutas, acciones y vistas
│   ├── bootstrap.php        # Sesión, CSRF, JSON, subidas, helpers
│   ├── config.php           # Límites y tipos permitidos
│   ├── credentials.example.php
│   ├── admin.js · style.css
│   └── .htaccess            # Bloquea archivos internos
├── gallery/ · blog/ · collage/ · ubicacion/   # data.json + .htaccess
├── qa/
│   ├── check_site.py        # Crawl y QA automatizado
│   └── security_checks.sh   # Pruebas de seguridad
└── .htaccess                # HTTPS y caché
```

---

## Qué no incluye este repositorio

Por privacidad y seguridad, el repositorio público **no contiene**:

- **Contraseñas ni credenciales:** `admin/credentials.php` y `.env` están en `.gitignore`. Se incluyen `admin/credentials.example.php` y `.env.example` como modelo.
- **Fotos:** ni las del diseño (`static/img/`) ni las cargadas desde el panel (`gallery/media/`, `collage/media/`, `blog/files/`).
- **Build:** `public/` se genera con cada build.

---

<sub>Diseñado y desarrollado por Michael Grandon · 本質 — preservar la esencia.</sub>
