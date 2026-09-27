"""QA de humo del sitio publicado: páginas, enlaces, recursos, datos JSON y accesibilidad básica.

Uso:
    python qa/check_site.py [https://honshitsudojo.com.ar/]

En macOS, si falla el certificado: SSL_CERT_FILE=/etc/ssl/cert.pem python qa/check_site.py
Sale con código 1 si encuentra problemas.
"""

import json
import sys
import urllib.error
import urllib.request
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse

BASE = sys.argv[1] if len(sys.argv) > 1 else "https://honshitsudojo.com.ar/"
PAGES = ["", "galeria/", "blog/"]
DATA_FILES = ["gallery/data.json", "blog/data.json", "collage/data.json", "ubicacion/data.json"]
UA = {"User-Agent": "HonshitsuQA/1.0"}
EXTERNAL_SKIP = ("google", "gstatic", "wa.me", "whatsapp", "youtube", "instagram", "facebook")


def fetch(url):
    req = urllib.request.Request(url, headers=UA)
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return r.status, dict(r.headers), r.read()
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read()
    except Exception as e:  # noqa: BLE001
        return 0, {}, str(e).encode()


class PageParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.refs, self.imgs_no_alt, self.ids, self.anchors = [], [], set(), []
        self.h1 = 0
        self.lang = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if a.get("id"):
            self.ids.add(a["id"])
        if tag == "html":
            self.lang = a.get("lang")
        if tag == "h1":
            self.h1 += 1
        for key in ("href", "src"):
            v = a.get(key)
            if v and tag in ("a", "link", "script", "img", "iframe", "source", "video"):
                self.refs.append((tag, v))
        if tag == "img" and "alt" not in a:
            self.imgs_no_alt.append(a.get("src"))
        if tag == "a" and (a.get("href") or "").startswith("#"):
            self.anchors.append(a["href"][1:])
        if a.get("data-images"):
            try:
                self.refs.extend(("collage", s) for s in json.loads(a["data-images"]))
            except ValueError:
                self.refs.append(("collage-json-invalido", a["data-images"][:60]))


def main() -> int:
    issues, checked = [], {}
    for page in PAGES:
        url = BASE + page
        status, headers, body = fetch(url)
        print(f"PÁGINA {status} {url} ({len(body)} B, Cache-Control={headers.get('Cache-Control')})")
        if status != 200:
            issues.append(f"{url} responde {status}")
            continue
        p = PageParser()
        p.feed(body.decode("utf-8", "replace"))
        if p.lang != "es":
            issues.append(f"{url}: lang={p.lang}")
        if p.h1 != 1:
            issues.append(f"{url}: tiene {p.h1} h1")
        if p.imgs_no_alt:
            issues.append(f"{url}: imágenes sin alt {p.imgs_no_alt}")
        issues.extend(f"{url}: el ancla #{a} no existe" for a in set(p.anchors) if a and a not in p.ids)
        for tag, ref in p.refs:
            if ref.startswith(("mailto:", "tel:", "#", "javascript:", "data:")):
                continue
            full = urljoin(url, ref).split("#")[0]
            host = urlparse(full).netloc
            if full in checked:
                continue
            if host and host not in urlparse(BASE).netloc and any(s in host for s in EXTERNAL_SKIP):
                checked[full] = "externo"
                continue
            status2, _, _ = fetch(full)
            checked[full] = status2
            if status2 != 200:
                issues.append(f"{url}: {tag} {ref} -> {status2}")

    for rel in DATA_FILES:
        status, _, body = fetch(BASE + rel)
        try:
            data = json.loads(body)
        except ValueError:
            issues.append(f"{rel}: JSON inválido ({status})")
            continue
        print(f"DATOS  {status} {rel}")
        if rel.startswith("gallery"):
            srcs = [i.get("src") for i in data.get("items", [])]
        elif rel.startswith("collage"):
            srcs = [i.get("src") for items in data.values() for i in items]
        elif rel.startswith("blog"):
            srcs = [i.get("file") for i in data.get("items", []) if i.get("file")]
        else:
            srcs = []
        for s in srcs:
            status2, _, _ = fetch(urljoin(BASE, s))
            if status2 != 200:
                issues.append(f"{rel}: {s} -> {status2}")

    print(f"\n{len(checked)} URLs revisadas")
    print("PROBLEMAS:" if issues else "Sin problemas.")
    for i in issues:
        print(" -", i)
    return 1 if issues else 0


if __name__ == "__main__":
    sys.exit(main())
