"""Aplicación Flask — Honshitsu Dojo."""

from pathlib import Path

from flask import Flask, render_template, send_from_directory

from content import page_context

ROOT = Path(__file__).resolve().parent

app = Flask(__name__)


@app.route("/")
def index():
    return render_template("index.html", **page_context())


@app.route("/galeria/")
@app.route("/galeria")
def galeria():
    return render_template("galeria.html", **page_context("galeria"))


@app.route("/blog/")
@app.route("/blog")
def blog():
    return render_template("blog.html", **page_context("blog"))


@app.route("/gallery/<path:filename>")
def gallery_files(filename: str):
    """Sirve data.json y media/ en local (en producción lo atiende el hosting)."""
    return send_from_directory(ROOT / "gallery", filename)


@app.route("/blog/<path:filename>")
def blog_files(filename: str):
    return send_from_directory(ROOT / "blog", filename)


@app.route("/collage/<path:filename>")
def collage_files(filename: str):
    return send_from_directory(ROOT / "collage", filename)


@app.route("/ubicacion/<path:filename>")
def ubicacion_files(filename: str):
    return send_from_directory(ROOT / "ubicacion", filename)


@app.route("/admin/")
@app.route("/admin/<path:filename>")
def admin_hint(filename: str = ""):
    """El panel real es PHP en HostGator; en local mostramos una pista."""
    return (
        "<!doctype html><meta charset=utf-8>"
        "<title>Admin</title>"
        "<body style='font-family:sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem'>"
        "<h1>Panel de administración</h1>"
        "<p>El panel corre en PHP en el hosting "
        "(<code>https://honshitsudojo.com.ar/admin/</code>).</p>"
        "<p>En local podés probar la galería pública en "
        "<a href='/galeria/'>/galeria/</a>.</p>"
        "</body>",
        200,
        {"Content-Type": "text/html; charset=utf-8"},
    )


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)
