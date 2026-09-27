"""Genera una carpeta public/ con HTML estático (útil para subir por FTP a HostGator)."""

from pathlib import Path
import os
import shutil
import ssl
import tempfile
import urllib.request

from app import app
from content import SITE, page_context

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "public"
LIVE_DATA = ("gallery/data.json", "blog/data.json", "collage/data.json", "ubicacion/data.json")


def _use_live_data():
    """Renderiza el HTML con los datos que el panel guardó en el servidor (no con las copias locales)."""
    if os.environ.get("HD_OFFLINE") == "1":
        return None
    cafile = "/etc/ssl/cert.pem" if Path("/etc/ssl/cert.pem").is_file() else None
    ctx = ssl.create_default_context(cafile=cafile)
    tmp = Path(tempfile.mkdtemp(prefix="hd-live-"))
    fetched = 0
    for rel in LIVE_DATA:
        dest = tmp / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        try:
            req = urllib.request.Request(SITE["url"] + rel, headers={"User-Agent": "HonshitsuBuild/1.0"})
            with urllib.request.urlopen(req, timeout=15, context=ctx) as r:
                dest.write_bytes(r.read())
            fetched += 1
        except Exception:  # noqa: BLE001
            local = ROOT / rel
            if local.is_file():
                shutil.copy2(local, dest)
    os.environ["HD_DATA_DIR"] = str(tmp)
    print(f"Datos del servidor: {fetched}/{len(LIVE_DATA)} descargados")
    return tmp


def _copy_tree(src: Path, dest: Path) -> None:
    if dest.exists():
        shutil.rmtree(dest)
    shutil.copytree(src, dest)


def build():
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir(parents=True)

    shutil.copytree(ROOT / "static", OUT / "static")

    for name in (".htaccess", "robots.txt"):
        src = ROOT / name
        if src.exists():
            shutil.copy2(src, OUT / name)

    # Panel PHP + galería (datos y media)
    if (ROOT / "admin").exists():
        _copy_tree(ROOT / "admin", OUT / "admin")
        # Nunca incluir password.php en el build
        pwd = OUT / "admin" / "password.php"
        if pwd.exists():
            pwd.unlink()

    if (ROOT / "gallery").exists():
        _copy_tree(ROOT / "gallery", OUT / "gallery")

    if (ROOT / "blog").exists():
        _copy_tree(ROOT / "blog", OUT / "blog")

    if (ROOT / "collage").exists():
        _copy_tree(ROOT / "collage", OUT / "collage")

    if (ROOT / "ubicacion").exists():
        _copy_tree(ROOT / "ubicacion", OUT / "ubicacion")

    live_dir = _use_live_data()
    try:
        _render_pages()
    finally:
        if live_dir:
            os.environ.pop("HD_DATA_DIR", None)
            shutil.rmtree(live_dir, ignore_errors=True)

    print(f"Sitio estático generado en: {OUT}")


def _render_pages() -> None:
    with app.test_request_context("/"):
        html = app.jinja_env.get_template("index.html").render(**page_context())
        html = html.replace('href="/static/', 'href="static/')
        html = html.replace('src="/static/', 'src="static/')
        html = html.replace('"/static/img/', '"static/img/')
        (OUT / "index.html").write_text(html, encoding="utf-8")

    for page in ("galeria", "blog"):
        page_dir = OUT / page
        page_dir.mkdir(parents=True, exist_ok=True)
        with app.test_request_context(f"/{page}/"):
            html = app.jinja_env.get_template(f"{page}.html").render(**page_context(page))
            html = html.replace('href="/static/', 'href="../static/')
            html = html.replace('src="/static/', 'src="../static/')
            (page_dir / "index.html").write_text(html, encoding="utf-8")


if __name__ == "__main__":
    build()
