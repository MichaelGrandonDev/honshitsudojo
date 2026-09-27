(function () {
  "use strict";

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* Toasts */
  $$(".toast").forEach(function (t) {
    var close = function () {
      t.classList.add("is-leaving");
      setTimeout(function () { t.remove(); }, 250);
    };
    var x = $(".toast-x", t);
    if (x) x.addEventListener("click", close);
    setTimeout(close, t.classList.contains("toast-err") ? 9000 : 4500);
  });

  /* Confirmación para acciones destructivas */
  var modal = $("#confirm");
  var pending = null;
  var closeModal = function () {
    if (!modal) return;
    modal.hidden = true;
    pending = null;
  };
  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form.hasAttribute("data-confirm") || !modal) return;
    e.preventDefault();
    pending = form;
    $("#confirm-text").textContent = form.getAttribute("data-confirm");
    modal.hidden = false;
    $("[data-confirm-ok]", modal).focus();
  });
  if (modal) {
    $$("[data-confirm-cancel]", modal).forEach(function (b) { b.addEventListener("click", closeModal); });
    $("[data-confirm-ok]", modal).addEventListener("click", function () {
      var form = pending;
      closeModal();
      if (form) form.submit();
    });
  }

  /* Dropzones con vista previa */
  $$("[data-dropzone]").forEach(function (zone) {
    var input = $("[data-dz-input]", zone);
    var form = zone.closest("form");
    var list = form ? $("[data-dz-previews]", form) : null;
    var submit = form ? $("[data-dz-submit]", form) : null;
    var row = form ? $("[data-dz-row]", form) : null;
    var minW = parseInt(zone.getAttribute("data-min-w"), 10) || 0;
    var minH = parseInt(zone.getAttribute("data-min-h"), 10) || 0;
    var urls = [];

    var render = function () {
      urls.forEach(function (u) { URL.revokeObjectURL(u); });
      urls = [];
      var files = Array.prototype.slice.call(input.files || []);
      if (submit) submit.disabled = !files.length;
      if (row) row.hidden = !files.length;
      zone.classList.toggle("has-files", files.length > 0);
      if (!list) return;
      list.innerHTML = "";
      list.hidden = !files.length;
      files.forEach(function (file) {
        var li = document.createElement("li");
        var thumb = document.createElement("div");
        thumb.className = "dz-thumb";
        var name = document.createElement("p");
        name.className = "dz-name";
        name.textContent = file.name;
        var meta = document.createElement("p");
        meta.className = "dz-meta";
        meta.textContent = (file.size / 1048576).toFixed(1) + " MB";
        if (/^image\//.test(file.type)) {
          var url = URL.createObjectURL(file);
          urls.push(url);
          var img = new Image();
          img.alt = "";
          img.onload = function () {
            var w = img.naturalWidth;
            var h = img.naturalHeight;
            meta.textContent = w + " × " + h + " · " + meta.textContent;
            if ((minW && w < minW) || (minH && h < minH)) {
              li.classList.add("is-low");
              var warn = document.createElement("span");
              warn.className = "badge-warn";
              warn.textContent = "Baja calidad";
              meta.appendChild(document.createTextNode(" "));
              meta.appendChild(warn);
            }
          };
          img.src = url;
          thumb.appendChild(img);
        } else {
          thumb.textContent = /^video\//.test(file.type) ? "Video" : "Archivo";
          thumb.classList.add("is-file");
        }
        li.appendChild(thumb);
        var info = document.createElement("div");
        info.className = "dz-info";
        info.appendChild(name);
        info.appendChild(meta);
        li.appendChild(info);
        list.appendChild(li);
      });
    };

    input.addEventListener("change", render);
    ["dragenter", "dragover"].forEach(function (ev) {
      zone.addEventListener(ev, function (e) {
        e.preventDefault();
        zone.classList.add("is-over");
      });
    });
    ["dragleave", "dragend", "drop"].forEach(function (ev) {
      zone.addEventListener(ev, function () { zone.classList.remove("is-over"); });
    });
    zone.addEventListener("drop", function (e) {
      e.preventDefault();
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      try {
        var dt = new DataTransfer();
        var files = Array.prototype.slice.call(e.dataTransfer.files);
        (input.multiple ? files : files.slice(0, 1)).forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
      } catch (err) {
        input.files = e.dataTransfer.files;
      }
      render();
    });
    if (form) {
      form.addEventListener("submit", function () {
        if (submit) {
          submit.disabled = true;
          submit.textContent = "Subiendo…";
        }
      });
    }
  });

  $$("[data-pick]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = document.getElementById(btn.getAttribute("data-pick"));
      if (!input) return;
      var zone = input.closest("[data-dropzone]");
      if (zone) zone.scrollIntoView({ behavior: "smooth", block: "center" });
      input.click();
    });
  });

  /* Visor de fotos */
  var viewer = $("#viewer");
  var current = null;
  var group = [];

  var fill = function (card) {
    current = card;
    var d = card.dataset;
    var kind = d.kind;
    var media = $("#viewer-media");
    media.innerHTML = "";
    var el;
    var isYoutube = d.type === "youtube" && d.youtube;
    if (isYoutube) {
      el = document.createElement("iframe");
      el.className = "viewer-yt";
      el.src = "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(d.youtube) + "?rel=0";
      el.title = d.caption || "Video de YouTube";
      el.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
      el.allowFullscreen = true;
    } else if (d.type === "video") {
      el = document.createElement("video");
      el.controls = true;
      el.playsInline = true;
      el.src = d.src;
    } else {
      el = document.createElement("img");
      el.alt = d.caption || "";
      el.src = d.src;
    }
    media.appendChild(el);

    var pos = parseInt(d.pos, 10);
    var total = parseInt(d.total, 10);
    $("#viewer-where").textContent = d.where || "";
    var isVideo = d.type === "youtube" || d.type === "video";
    var noun = isVideo ? "Video" : "Foto";
    $("#viewer-pos").textContent = (kind === "gallery" && pos === 1 ? (isVideo ? "Video destacado · " : "Foto destacada · ") : "") + noun + " " + pos + " de " + total;
    $("#viewer-res").textContent = d.res || "—";
    $("#viewer-low").hidden = !d.low;
    $("#viewer-date").textContent = d.date || "—";
    var hint = $("#viewer-hint");
    hint.hidden = !d.low;
    hint.textContent = d.low ? "Esta foto es chica y se ve borrosa en pantallas grandes. " + d.hint + " Usá «Reemplazar foto» para subir una versión más grande." : "";
    var openLink = $("#viewer-open");
    openLink.href = isYoutube ? "https://www.youtube.com/watch?v=" + encodeURIComponent(d.youtube) : d.src;
    openLink.lastChild.textContent = isYoutube ? "Ver en YouTube" : "Tamaño real";

    $$("[data-only]", viewer).forEach(function (f) { f.hidden = f.getAttribute("data-only") !== kind; });
    $$("[data-f='id']", viewer).forEach(function (i) { i.value = d.id; });
    $$("[data-f='section']", viewer).forEach(function (i) {
      i.value = d.section;
      i.disabled = !d.section;
    });
    $$("[data-f='caption']", viewer).forEach(function (i) { i.value = d.caption || ""; });
    $$("[data-act]", viewer).forEach(function (i) { i.value = kind + "_" + i.getAttribute("data-act"); });
    $$("[data-need='not-first']", viewer).forEach(function (b) { b.disabled = pos <= 1; });
    $$("[data-need='not-last']", viewer).forEach(function (b) { b.disabled = pos >= total; });
    var replace = $("[data-replace-input]", viewer);
    replace.accept = kind === "gallery"
      ? "image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov"
      : "image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif";
    var del = $(".viewer-delete", viewer);
    del.setAttribute("data-confirm", kind === "gallery" ? "¿Eliminar este archivo de la galería? No se puede deshacer." : "¿Eliminar esta foto de la sección? No se puede deshacer.");

    var idx = group.indexOf(card);
    $("[data-viewer-prev]", viewer).hidden = idx <= 0;
    $("[data-viewer-next]", viewer).hidden = idx < 0 || idx >= group.length - 1;
  };

  var openViewer = function (card, focusCaption) {
    if (!viewer) return;
    var grid = card.closest("[data-group]");
    group = grid ? $$("[data-photo]", grid) : [card];
    fill(card);
    viewer.hidden = false;
    document.body.classList.add("no-scroll");
    var cap = $(".viewer-caption input[name='caption']", viewer);
    if (focusCaption && cap && card.dataset.kind === "gallery") {
      cap.focus();
      cap.select();
    } else {
      $("[data-viewer-close].icon-btn", viewer).focus();
    }
  };

  var closeViewer = function () {
    if (!viewer || viewer.hidden) return;
    viewer.hidden = true;
    document.body.classList.remove("no-scroll");
    var v = $("#viewer-media video");
    if (v) v.pause();
    var yt = $("#viewer-media iframe");
    if (yt) yt.remove();
    if (current) {
      var btn = $("[data-open]", current);
      if (btn) btn.focus();
    }
  };

  var step = function (delta) {
    var idx = group.indexOf(current);
    var next = group[idx + delta];
    if (next) fill(next);
  };

  if (viewer) {
    $$("[data-open]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openViewer(btn.closest("[data-photo]"), btn.getAttribute("data-focus") === "caption");
      });
    });
    $$("[data-viewer-close]", viewer).forEach(function (b) { b.addEventListener("click", closeViewer); });
    $("[data-viewer-prev]", viewer).addEventListener("click", function () { step(-1); });
    $("[data-viewer-next]", viewer).addEventListener("click", function () { step(1); });
    $("[data-replace-input]", viewer).addEventListener("change", function () {
      if (this.files && this.files.length) {
        this.closest("label").classList.add("is-busy");
        this.form.submit();
      }
    });
  }

  document.addEventListener("keydown", function (e) {
    if (modal && !modal.hidden) {
      if (e.key === "Escape") closeModal();
      return;
    }
    if (!viewer || viewer.hidden) return;
    var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
    if (e.key === "Escape") {
      if (typing) e.target.blur();
      else closeViewer();
    } else if (!typing && e.key === "ArrowLeft") {
      step(-1);
    } else if (!typing && e.key === "ArrowRight") {
      step(1);
    }
  });

  /* Resaltar la foto/publicación recién editada */
  if (location.hash.length > 1) {
    var target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target) {
      target.classList.add("is-flash");
      setTimeout(function () { target.classList.remove("is-flash"); }, 2200);
    }
  }

  /* Blog: foco en título y campo PDF según el tipo */
  var titleInput = $("[data-title-input]");
  $$("[data-focus-title]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      if (!titleInput) return;
      e.preventDefault();
      titleInput.closest(".panel").scrollIntoView({ behavior: "smooth", block: "start" });
      setTimeout(function () { titleInput.focus(); }, 300);
    });
  });
  var pdfField = $("[data-pdf-field]");
  if (pdfField) {
    var radios = $$("input[name='kind']");
    var sync = function () {
      var checked = radios.filter(function (r) { return r.checked; })[0];
      pdfField.classList.toggle("is-muted", !checked || checked.value !== "pdf");
    };
    radios.forEach(function (r) { r.addEventListener("change", sync); });
    sync();
  }

  /* Ubicación: probar en el mapa sin guardar */
  var previewBtn = $("[data-map-preview]");
  if (previewBtn) {
    previewBtn.addEventListener("click", function () {
      var f = previewBtn.form;
      var q = f.map_query.value.trim();
      if (!q) {
        q = [f.address_1.value, f.address_2.value, f.location_label.value]
          .map(function (s) { return s.trim(); })
          .filter(function (s) { return s !== ""; })
          .join(", ");
      }
      if (!q) return;
      var enc = encodeURIComponent(q);
      $("#map-preview").src = "https://maps.google.com/maps?q=" + enc + "&z=16&hl=es&output=embed";
      $("#map-preview-q").textContent = q + " (sin guardar)";
      $("#map-preview-link").href = "https://www.google.com/maps/search/?api=1&query=" + enc;
    });
  }
})();
