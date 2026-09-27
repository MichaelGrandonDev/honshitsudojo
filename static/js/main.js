(() => {
  const header = document.querySelector(".site-header");
  const toggle = document.querySelector(".nav-toggle");
  const nav = document.querySelector(".site-nav");
  const reveals = document.querySelectorAll(".reveal");

  let lastY = window.scrollY;

  const onScroll = () => {
    const y = window.scrollY;
    if (!header) return;

    const menuOpen = nav?.classList.contains("is-open");
    if (header.hasAttribute("data-transparent")) {
      header.classList.toggle("is-solid", y > 40 || menuOpen);
    }

    if (menuOpen) header.classList.remove("is-hidden");
    else if (y > lastY && y > 140) header.classList.add("is-hidden");
    else header.classList.remove("is-hidden");

    lastY = y;
  };

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      const open = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", String(!open));
      nav.classList.toggle("is-open", !open);
      header?.classList.add("is-solid");
    });

    nav.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        toggle.setAttribute("aria-expanded", "false");
        nav.classList.remove("is-open");
      });
    });
  }

  if ("IntersectionObserver" in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.14, rootMargin: "0px 0px -36px 0px" }
    );
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add("is-visible"));
  }

  /* Gallery page (featured + cards) + lightbox */
  const showcase = document.querySelector("#gallery-showcase");
  const grid = document.querySelector("#gallery-grid");
  const lightbox = document.querySelector("#lightbox");
  const stage = lightbox?.querySelector(".lightbox-stage");
  const captionEl = lightbox?.querySelector(".lightbox-caption");
  const closeBtn = lightbox?.querySelector(".lightbox-close");

  const closeLightbox = () => {
    if (!lightbox || !stage) return;
    lightbox.hidden = true;
    stage.innerHTML = "";
    if (captionEl) captionEl.textContent = "";
    document.body.style.overflow = "";
  };

  const openLightbox = (item) => {
    if (!lightbox || !stage) return;
    stage.innerHTML = "";
    if (item.type === "youtube" && item.youtube) {
      const frame = document.createElement("iframe");
      frame.className = "lightbox-yt";
      frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(item.youtube)}?autoplay=1&rel=0`;
      frame.title = item.caption || "Video de YouTube";
      frame.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
      frame.allowFullscreen = true;
      stage.appendChild(frame);
    } else if (item.type === "video") {
      const video = document.createElement("video");
      video.src = item.src;
      video.controls = true;
      video.autoplay = true;
      video.playsInline = true;
      stage.appendChild(video);
    } else {
      const img = document.createElement("img");
      img.src = item.src;
      img.alt = item.caption || "";
      stage.appendChild(img);
    }
    if (captionEl) captionEl.textContent = item.caption || "";
    lightbox.hidden = false;
    document.body.style.overflow = "hidden";
  };

  closeBtn?.addEventListener("click", closeLightbox);
  lightbox?.addEventListener("click", (e) => {
    if (e.target === lightbox) closeLightbox();
  });
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeLightbox();
  });

  const withPrefix = (src, prefix) => {
    if (!src) return "";
    if (/^(https?:|data:|\/)/i.test(src) || !prefix) return src;
    return prefix + src.replace(/^\.\//, "");
  };

  const appendThumb = (wrap, item, width, height, lazy) => {
    if (item.type === "video") {
      const video = document.createElement("video");
      video.src = item.src;
      video.muted = true;
      video.playsInline = true;
      video.preload = "metadata";
      wrap.appendChild(video);
    } else {
      const img = document.createElement("img");
      img.src = item.src;
      img.alt = item.caption || "Galería";
      if (lazy) img.loading = "lazy";
      img.width = width;
      img.height = height;
      wrap.appendChild(img);
    }
    if (item.type === "youtube") {
      const play = document.createElement("span");
      play.className = "gallery-play";
      play.setAttribute("aria-hidden", "true");
      wrap.appendChild(play);
    }
    if (item.type === "video" || item.type === "youtube") {
      const badge = document.createElement("span");
      badge.className = "gallery-badge";
      badge.textContent = item.type === "youtube" ? "YouTube" : "Video";
      wrap.appendChild(badge);
    }
  };

  const initGalleryShowcase = (rawItems) => {
    if (!showcase) return;
    const prefix = showcase.getAttribute("data-asset-prefix") || "";
    const emptyMsg = showcase.getAttribute("data-empty") || "";
    const featuredLabel = showcase.getAttribute("data-featured-label") || "Destacado";
    const siteName = "Honshitsu Dojo";
    const items = (rawItems || []).map((item) => ({
      ...item,
      src: withPrefix(item.src, prefix),
    }));

    const featuredRoot = showcase.querySelector("#gallery-featured");
    const featuredBtn = showcase.querySelector(".gallery-featured-media");
    const featuredTitle = showcase.querySelector(".gallery-featured-title");
    const featuredKicker = showcase.querySelector(".gallery-featured-kicker");
    const dots = showcase.querySelector("#gallery-dots");
    const prev = showcase.querySelector(".gallery-nav-prev");
    const next = showcase.querySelector(".gallery-nav-next");
    let index = 0;

    if (!items.length) {
      showcase.innerHTML = `<p class="gallery-empty">${emptyMsg}</p>`;
      return;
    }

    const paintFeatured = (i) => {
      index = (i + items.length) % items.length;
      const item = items[index];
      if (!featuredBtn) return;
      featuredBtn.setAttribute("data-type", item.type || "image");
      featuredBtn.setAttribute("data-src", item.src);
      featuredBtn.setAttribute("data-youtube", item.youtube || "");
      featuredBtn.setAttribute("data-caption", item.caption || "");
      featuredBtn.innerHTML = "";
      appendThumb(featuredBtn, item, 1400, 800, false);
      if (featuredTitle) featuredTitle.textContent = item.caption || "Galería";
      if (featuredKicker) featuredKicker.textContent = featuredLabel;
      dots?.querySelectorAll("button").forEach((dot, di) => {
        dot.classList.toggle("is-active", di === index);
      });
      grid?.querySelectorAll(".gallery-card").forEach((card) => {
        card.classList.toggle(
          "is-active",
          Number(card.getAttribute("data-index")) === index
        );
      });
    };

    if (dots) {
      dots.innerHTML = "";
      items.forEach((_, i) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.setAttribute("aria-label", `Foto ${i + 1}`);
        if (i === 0) dot.classList.add("is-active");
        dot.addEventListener("click", () => paintFeatured(i));
        dots.appendChild(dot);
      });
    }

    if (grid) {
      grid.innerHTML = "";
      items.forEach((item, i) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "gallery-card reveal is-visible";
        btn.setAttribute("data-type", item.type || "image");
        btn.setAttribute("data-src", item.src);
        btn.setAttribute("data-youtube", item.youtube || "");
        btn.setAttribute("data-caption", item.caption || "");
        btn.setAttribute("data-index", String(i));
        const mediaWrap = document.createElement("span");
        mediaWrap.className = "gallery-card-media";
        appendThumb(mediaWrap, item, 640, 640, true);
        const body = document.createElement("span");
        body.className = "gallery-card-body";
        body.innerHTML = `<span class="gallery-card-title"></span><span class="gallery-card-sub"></span>`;
        body.querySelector(".gallery-card-title").textContent =
          item.caption || siteName;
        body.querySelector(".gallery-card-sub").textContent = siteName;
        btn.appendChild(mediaWrap);
        btn.appendChild(body);
        btn.addEventListener("click", () => {
          paintFeatured(i);
          openLightbox(item);
        });
        grid.appendChild(btn);
      });
    }

    featuredBtn?.addEventListener("click", () => openLightbox(items[index]));
    prev?.addEventListener("click", () => paintFeatured(index - 1));
    next?.addEventListener("click", () => paintFeatured(index + 1));
    paintFeatured(0);
    if (featuredRoot) featuredRoot.hidden = false;
  };

  if (showcase) {
    const src = showcase.getAttribute("data-src") || "../gallery/data.json";
    // Bind SSR cards / featured until JSON refresh
    showcase.querySelectorAll("[data-src]").forEach((btn) => {
      if (!btn.matches(".gallery-card, .gallery-featured-media")) return;
      btn.addEventListener("click", () => {
        openLightbox({
          type: btn.getAttribute("data-type") || "image",
          src: btn.getAttribute("data-src"),
          youtube: btn.getAttribute("data-youtube") || "",
          caption: btn.getAttribute("data-caption") || "",
        });
      });
    });

    fetch(src, { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((data) => {
        const items = Array.isArray(data?.items) ? data.items : [];
        if (items.length) initGalleryShowcase(items);
      })
      .catch(() => {
        /* SSR content remains */
      });
  }

  /* Botón flotante de WhatsApp arrastrable */
  const wa = document.querySelector("[data-wa-float]");
  if (wa) {
    const KEY = "hd-wa-pos";
    const MARGIN = 8;
    let saved = null;
    try {
      saved = JSON.parse(localStorage.getItem(KEY) || "null");
    } catch (e) {
      saved = null;
    }

    const place = (rx, ry) => {
      const maxX = window.innerWidth - wa.offsetWidth - MARGIN * 2;
      const maxY = window.innerHeight - wa.offsetHeight - MARGIN * 2;
      wa.style.left = `${MARGIN + Math.min(Math.max(rx, 0), 1) * maxX}px`;
      wa.style.top = `${MARGIN + Math.min(Math.max(ry, 0), 1) * maxY}px`;
      wa.style.right = "auto";
      wa.style.bottom = "auto";
    };

    if (saved && typeof saved.x === "number" && typeof saved.y === "number") place(saved.x, saved.y);
    window.addEventListener("resize", () => {
      if (saved) place(saved.x, saved.y);
    });

    let start = null;
    let moved = false;

    wa.addEventListener("pointerdown", (e) => {
      if (e.button !== 0) return;
      const r = wa.getBoundingClientRect();
      start = { px: e.clientX, py: e.clientY, x: r.left, y: r.top };
      moved = false;
      wa.setPointerCapture(e.pointerId);
    });

    wa.addEventListener("pointermove", (e) => {
      if (!start) return;
      const dx = e.clientX - start.px;
      const dy = e.clientY - start.py;
      if (!moved && Math.hypot(dx, dy) < 6) return;
      moved = true;
      wa.classList.add("is-dragging");
      const maxX = window.innerWidth - wa.offsetWidth - MARGIN;
      const maxY = window.innerHeight - wa.offsetHeight - MARGIN;
      wa.style.left = `${Math.min(Math.max(start.x + dx, MARGIN), maxX)}px`;
      wa.style.top = `${Math.min(Math.max(start.y + dy, MARGIN), maxY)}px`;
      wa.style.right = "auto";
      wa.style.bottom = "auto";
    });

    const end = () => {
      if (!start) return;
      start = null;
      wa.classList.remove("is-dragging");
      if (!moved) return;
      const r = wa.getBoundingClientRect();
      const spanX = window.innerWidth - wa.offsetWidth - MARGIN * 2;
      const spanY = window.innerHeight - wa.offsetHeight - MARGIN * 2;
      saved = {
        x: spanX > 0 ? (r.left - MARGIN) / spanX : 1,
        y: spanY > 0 ? (r.top - MARGIN) / spanY : 1,
      };
      try {
        localStorage.setItem(KEY, JSON.stringify(saved));
      } catch (e) {
        /* sin almacenamiento: la posición dura solo esta visita */
      }
    };
    wa.addEventListener("pointerup", end);
    wa.addEventListener("pointercancel", end);

    wa.addEventListener("click", (e) => {
      if (moved) {
        e.preventDefault();
        moved = false;
      }
    });
    wa.addEventListener("dragstart", (e) => e.preventDefault());
  }

  /* Collage de fotos junto a los textos */
  const collages = [...document.querySelectorAll("[data-collage]")].map((box) => {
    let urls = [];
    try {
      urls = JSON.parse(box.getAttribute("data-images") || "[]");
    } catch (e) {
      urls = [];
    }
    const figs = [...box.querySelectorAll(".collage-item")];
    const navs = [...box.querySelectorAll(".collage-nav")];
    let offset = 0;

    const render = () => {
      const n = urls.length;
      const slots = n === 1 ? [1] : n === 2 ? [0, 2] : n ? [0, 1, 2] : [];
      figs.forEach((fig, i) => {
        const k = slots.indexOf(i);
        fig.hidden = k < 0;
        if (k >= 0) {
          const src = urls[(offset + k) % n];
          const img = fig.querySelector("img");
          if (img.getAttribute("src") !== src) img.setAttribute("src", src);
        }
      });
      navs.forEach((b) => {
        b.hidden = n < 2;
      });
      box.classList.toggle("is-empty", !n);
      box.closest(".split")?.classList.toggle("has-collage", n > 0);
    };

    const shift = (step) => {
      if (urls.length < 2) return;
      offset = (offset + step + urls.length) % urls.length;
      box.classList.add("is-swapping");
      setTimeout(() => {
        render();
        box.classList.remove("is-swapping");
      }, 350);
    };
    box.querySelector(".collage-prev")?.addEventListener("click", () => shift(-1));
    box.querySelector(".collage-next")?.addEventListener("click", () => shift(1));

    return {
      box,
      key: box.getAttribute("data-collage"),
      update(next) {
        if (JSON.stringify(next) === JSON.stringify(urls)) return;
        urls = next;
        offset = 0;
        render();
      },
    };
  });

  if (collages.length) {
    const src = collages[0].box.getAttribute("data-src") || "collage/data.json";
    fetch(src, { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (!data) return;
        collages.forEach((c) => {
          const items = Array.isArray(data[c.key]) ? data[c.key] : [];
          c.update(items.map((i) => i && i.src).filter(Boolean));
        });
      })
      .catch(() => {});
  }

  /* Ubicación (dirección y mapa administrados desde /admin/) */
  const ubicacion = document.querySelector("[data-ubicacion-src]");
  if (ubicacion) {
    const setText = (el, value) => {
      if (el && typeof value === "string" && el.textContent.trim() !== value) el.textContent = value;
    };
    fetch(ubicacion.getAttribute("data-ubicacion-src"), { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (!data || typeof data !== "object") return;
        const str = (v) => (typeof v === "string" ? v.trim() : "");
        const name = str(data.place_name);
        const label = str(data.location_label);
        const lines = (Array.isArray(data.address_lines) ? data.address_lines : []).map(str).filter(Boolean);
        const query = str(data.map_query) || [...lines, label].filter(Boolean).join(", ");

        if (name) setText(ubicacion.querySelector("[data-ubicacion-name]"), name);
        setText(ubicacion.querySelector("[data-ubicacion-label]"), label);

        const address = ubicacion.querySelector("[data-ubicacion-address]");
        if (address && lines.length) {
          const current = [""];
          address.childNodes.forEach((node) => {
            if (node.nodeName === "BR") current.push("");
            else current[current.length - 1] += node.textContent;
          });
          if (current.map((s) => s.trim()).filter(Boolean).join("\n") !== lines.join("\n")) {
            address.replaceChildren();
            lines.forEach((line, i) => {
              if (i) address.appendChild(document.createElement("br"));
              address.appendChild(document.createTextNode(line));
            });
          }
        }

        if (!query) return;
        const q = encodeURIComponent(query);
        const map = ubicacion.querySelector("[data-ubicacion-map]");
        if (map) {
          let currentQ = "";
          try {
            currentQ = new URL(map.getAttribute("src") || "", location.href).searchParams.get("q") || "";
          } catch (e) {}
          if (currentQ !== query) {
            map.setAttribute("src", `https://maps.google.com/maps?q=${q}&z=16&hl=es&output=embed`);
          }
          const title = `Mapa: ${[name, lines.join(", ")].filter(Boolean).join(", ")}`;
          if (map.getAttribute("title") !== title) map.setAttribute("title", title);
        }
        const dir = ubicacion.querySelector("[data-ubicacion-directions]");
        const href = `https://www.google.com/maps/dir/?api=1&destination=${q}`;
        if (dir && dir.getAttribute("href") !== href) dir.setAttribute("href", href);
      })
      .catch(() => {});
  }

  /* Blog */
  const blogList = document.querySelector("#blog-list");
  const typeLabel = { nota: "Nota", noticia: "Noticia", pdf: "PDF" };

  const renderBlog = (items, emptyMsg) => {
    if (!blogList) return;
    blogList.innerHTML = "";
    if (!items.length) {
      blogList.classList.add("is-empty");
      blogList.textContent = emptyMsg || "";
      return;
    }
    blogList.classList.remove("is-empty");
    items.forEach((item) => {
      const article = document.createElement("article");
      article.className = "blog-card reveal";
      const badge = document.createElement("span");
      badge.className = "blog-type";
      badge.textContent = typeLabel[item.type] || item.type || "";
      const h3 = document.createElement("h3");
      h3.textContent = item.title || "";
      article.appendChild(badge);
      article.appendChild(h3);
      if (item.body) {
        const p = document.createElement("p");
        p.textContent = item.body;
        article.appendChild(p);
      }
      if (item.file) {
        const a = document.createElement("a");
        a.href = (blogList.getAttribute("data-asset-prefix") || "") + item.file;
        a.target = "_blank";
        a.rel = "noopener noreferrer";
        a.className = "blog-pdf";
        a.textContent = "Abrir PDF";
        article.appendChild(a);
      }
      blogList.appendChild(article);
    });

    if ("IntersectionObserver" in window) {
      const io = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("is-visible");
              io.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.12 }
      );
      blogList.querySelectorAll(".reveal").forEach((el) => io.observe(el));
    } else {
      blogList.querySelectorAll(".reveal").forEach((el) => el.classList.add("is-visible"));
    }
  };

  if (blogList) {
    const src = blogList.getAttribute("data-src") || "blog/data.json";
    const emptyMsg = blogList.getAttribute("data-empty") || "";
    fetch(src, { cache: "no-store" })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((data) => {
        const items = Array.isArray(data?.items) ? data.items : [];
        renderBlog(items, emptyMsg);
      })
      .catch(() => renderBlog([], emptyMsg));
  }
})();
