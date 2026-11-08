// Tur panduan sederhana tanpa library.
// Data dari window.TUR = { kunci, paksa, langkah: [{ t: [target...], j: judul, i: isi }] }
(function () {
  const cfg = window.TUR;
  if (!cfg || !cfg.langkah || !cfg.langkah.length) return;

  const simpan = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
  const baca = k => { try { return localStorage.getItem(k); } catch (e) { return null; } };
  if (!cfg.paksa && baca(cfg.kunci) === 'selesai') return;

  // Hapus ?tur=1 dari alamat supaya muat ulang tidak memulai tur lagi
  if (cfg.paksa && history.replaceState) history.replaceState(null, '', location.pathname);

  let idx = 0, target = null;
  const sorot = document.createElement('div');
  sorot.className = 'tur-sorot';
  const kotak = document.createElement('div');
  kotak.className = 'tur-kotak';
  kotak.style.left = '-9999px';
  kotak.style.top = '0';
  kotak.setAttribute('role', 'dialog');
  kotak.setAttribute('aria-live', 'polite');
  const lapis = document.createElement('div');
  lapis.className = 'tur-lapis';
  document.body.append(lapis, sorot, kotak);

  const terlihat = el => {
    if (!el) return false;
    const r = el.getBoundingClientRect();
    const st = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && st.visibility !== 'hidden' && st.display !== 'none';
  };
  const cariTarget = daftar => {
    for (const nama of daftar || []) {
      for (const el of document.querySelectorAll('[data-tour="' + nama + '"]')) {
        if (terlihat(el)) return el;
      }
    }
    return null;
  };

  function posisikan() {
    const pad = 6, gap = 12, w = Math.min(340, window.innerWidth - 24);
    kotak.style.width = w + 'px';
    if (!target) {
      sorot.style.display = 'none';
      lapis.style.display = 'block';
      kotak.style.left = Math.max(12, (window.innerWidth - w) / 2) + 'px';
      kotak.style.top = Math.max(12, (window.innerHeight - kotak.offsetHeight) / 2) + 'px';
      return;
    }
    lapis.style.display = 'none';
    sorot.style.display = 'block';
    const r = target.getBoundingClientRect();
    Object.assign(sorot.style, {
      left: (r.left - pad) + 'px', top: (r.top - pad) + 'px',
      width: (r.width + pad * 2) + 'px', height: (r.height + pad * 2) + 'px',
    });
    const h = kotak.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
    const jepit = (v, min, max) => Math.min(Math.max(v, min), max);
    let left, top;
    if (false) {
      left = r.right + pad + gap;
      top = jepit(r.top + r.height / 2 - h / 2, 12, vh - h - 12);
    } else {
      left = jepit(r.left + r.width / 2 - w / 2, 12, vw - w - 12);
      if (target.closest('.tabbar') || r.bottom + gap + h > vh) {
        top = r.top - pad - gap - h;                         // di atas
        if (top < 12) top = jepit(r.bottom + pad + gap, 12, vh - h - 12);
      } else {
        top = r.bottom + pad + gap;                          // di bawah
      }
    }
    kotak.style.left = left + 'px';
    kotak.style.top = top + 'px';
  }

  function tampil(i) {
    idx = i;
    const l = cfg.langkah[i];
    target = cariTarget(l.t);
    const akhir = i === cfg.langkah.length - 1;
    kotak.innerHTML =
      '<div class="tur-langkah">' + (i + 1) + ' dari ' + cfg.langkah.length + '</div>' +
      '<div class="tur-judul"></div><div class="tur-isi"></div>' +
      '<div class="tur-tombol">' +
      (akhir ? '' : '<button type="button" class="btn btn-link btn-sm text-muted px-0 me-auto" data-aksi="lewati">Lewati</button>') +
      (i > 0 ? '<button type="button" class="btn btn-light btn-sm" data-aksi="kembali">Kembali</button>' : '') +
      '<button type="button" class="btn btn-brand btn-sm' + (akhir ? ' ms-auto' : '') + '" data-aksi="lanjut">' + (akhir ? 'Selesai' : 'Lanjut') + '</button>' +
      '</div>';
    kotak.querySelector('.tur-judul').textContent = l.j;
    kotak.querySelector('.tur-isi').textContent = l.i;
    if (target) target.scrollIntoView({ block: 'center', inline: 'nearest' });
    posisikan();
    requestAnimationFrame(posisikan);
    setTimeout(posisikan, 250);
    kotak.querySelector('[data-aksi="lanjut"]').focus();
  }

  function tutup() {
    simpan(cfg.kunci, 'selesai');
    sorot.remove(); kotak.remove(); lapis.remove();
    window.removeEventListener('resize', posisikan);
    window.removeEventListener('scroll', posisikan, true);
    document.removeEventListener('keydown', tombol);
  }
  function tombol(e) {
    if (e.key === 'Escape') tutup();
    else if (e.key === 'ArrowRight') lanjut();
    else if (e.key === 'ArrowLeft' && idx > 0) tampil(idx - 1);
  }
  function lanjut() { idx < cfg.langkah.length - 1 ? tampil(idx + 1) : tutup(); }

  kotak.addEventListener('click', e => {
    const a = e.target.closest('[data-aksi]');
    if (!a) return;
    if (a.dataset.aksi === 'lanjut') lanjut();
    else if (a.dataset.aksi === 'kembali') tampil(idx - 1);
    else tutup();
  });
  lapis.addEventListener('click', lanjut);
  window.addEventListener('resize', posisikan);
  window.addEventListener('scroll', posisikan, true);
  document.addEventListener('keydown', tombol);

  setTimeout(() => tampil(0), cfg.paksa ? 100 : 600);
})();
