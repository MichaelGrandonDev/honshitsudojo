"""Build estático + subida por FTP a HostGator (honshitsudojo.com.ar)."""

from __future__ import annotations

import os
import sys
from ftplib import FTP, error_perm
from pathlib import Path

from build_static import OUT, build

ROOT = Path(__file__).resolve().parent


def load_env() -> None:
    env_path = ROOT / ".env"
    if not env_path.exists():
        print("Falta .env — copiá .env.example a .env y completá FTP_USER / FTP_PASS.")
        sys.exit(1)
    for line in env_path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def ensure_remote_dir(ftp: FTP, remote: str) -> None:
    parts = [p for p in remote.strip("/").split("/") if p]
    path = ""
    for part in parts:
        path += f"/{part}"
        try:
            ftp.mkd(path)
        except error_perm:
            pass
    ftp.cwd(remote if remote.startswith("/") else f"/{remote}")


def upload_dir(ftp: FTP, local: Path, remote: str, *, skip_prefixes: tuple[str, ...] = ()) -> int:
    ensure_remote_dir(ftp, remote)
    count = 0
    for path in sorted(local.rglob("*")):
        rel = path.relative_to(local).as_posix()
        if any(rel == p or rel.startswith(p.rstrip("/") + "/") for p in skip_prefixes):
            print(f"  · omitido {rel}")
            continue
        remote_path = f"{remote.rstrip('/')}/{rel}"
        if path.is_dir():
            try:
                ftp.mkd(remote_path)
            except error_perm:
                pass
            continue
        parent = "/".join(remote_path.split("/")[:-1])
        if parent and parent != remote.rstrip("/"):
            ensure_remote_dir(ftp, parent)
        with path.open("rb") as fh:
            ftp.storbinary(f"STOR {remote_path}", fh)
        print(f"  ↑ {rel}")
        count += 1
    return count


def deploy() -> None:
    load_env()
    host = os.environ.get("FTP_HOST", "").strip()
    user = os.environ.get("FTP_USER", "").strip()
    password = os.environ.get("FTP_PASS", "").strip()
    remote = os.environ.get("FTP_REMOTE_DIR", "/honshitsudojo.com.ar").strip()
    port = int(os.environ.get("FTP_PORT", "21"))
    # Por defecto no pisar galería ya administrada en el servidor
    sync_gallery = os.environ.get("FTP_SYNC_GALLERY", "").strip() in {"1", "true", "yes"}

    if not host or not user or not password:
        print("FTP_HOST, FTP_USER y FTP_PASS son obligatorios en .env")
        sys.exit(1)

    print("1/2 Generando public/ …")
    build()
    if not OUT.exists():
        print("No se generó public/")
        sys.exit(1)

    skip: tuple[str, ...] = ("admin/password.php",)
    if not sync_gallery:
        skip = skip + (
            "gallery/data.json", "gallery/media/",
            "blog/data.json", "blog/files/",
            "collage/data.json", "collage/media/",
            "ubicacion/data.json",
        )
        print("  (galería/blog remotos preservados — FTP_SYNC_GALLERY=1 para forzar)")

    print(f"2/2 Subiendo a {host}:{remote} …")
    with FTP() as ftp:
        ftp.connect(host, port, timeout=60)
        ftp.login(user, password)
        ftp.set_pasv(True)
        n = upload_dir(ftp, OUT, remote, skip_prefixes=skip)
    print(f"Listo: {n} archivos en https://honshitsudojo.com.ar/")
    print("Admin: https://honshitsudojo.com.ar/admin/")


if __name__ == "__main__":
    deploy()
