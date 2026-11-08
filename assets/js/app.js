// Konfirmasi sebelum aksi berbahaya (hapus, dll.)
document.addEventListener('submit', function (e) {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !confirm(msg)) e.preventDefault();
});

// Cek ukuran file sebelum upload & tampilkan status loading
document.querySelectorAll('form[data-maxsize]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    const max = parseInt(form.dataset.maxsize, 10);
    const input = form.querySelector('input[type=file]');
    if (input && input.files[0] && input.files[0].size > max) {
      e.preventDefault();
      alert('File terlalu besar. Maksimal ' + Math.round(max / 1048576) + ' MB.');
      return;
    }
    const btn = form.querySelector('[data-loading]');
    if (btn) {
      setTimeout(function () { btn.disabled = true; }, 0);
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + btn.dataset.loading;
    }
  });
});

// Format angka rupiah saat mengetik (1.000.000)
document.querySelectorAll('input[data-rupiah]').forEach(function (inp) {
  inp.addEventListener('input', function () {
    const digits = inp.value.replace(/\D/g, '');
    inp.value = digits ? Number(digits).toLocaleString('id-ID') : '';
  });
});

// Tema terang / gelap / otomatis (disimpan di perangkat ini)
(function () {
  const h = document.documentElement;
  const media = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
  const baca = () => { try { return localStorage.getItem('tb_tema') || 'otomatis'; } catch (e) { return 'otomatis'; } };
  const simpan = v => { try { localStorage.setItem('tb_tema', v); } catch (e) {} };
  function terapkan(pilihan) {
    const gelap = pilihan === 'gelap' || (pilihan === 'otomatis' && media && media.matches);
    h.dataset.theme = gelap ? 'dark' : 'light';
    h.dataset.bsTheme = gelap ? 'dark' : 'light';
    h.dataset.temaPilihan = pilihan;
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = gelap ? '#0a0a0c' : '#111111';
    document.querySelectorAll('[data-tema-pilih]').forEach(b => b.setAttribute('aria-pressed', b.dataset.temaPilih === pilihan ? 'true' : 'false'));
  }
  terapkan(baca());
  if (media && media.addEventListener) media.addEventListener('change', () => { if (baca() === 'otomatis') terapkan('otomatis'); });
  document.addEventListener('click', function (e) {
    const ganti = e.target.closest('[data-ganti-tema]');
    const pilih = e.target.closest('[data-tema-pilih]');
    if (ganti) {
      const baru = h.dataset.theme === 'dark' ? 'terang' : 'gelap';
      simpan(baru); terapkan(baru);
    } else if (pilih) {
      simpan(pilih.dataset.temaPilih); terapkan(pilih.dataset.temaPilih);
    }
  });
})();

// Daftarkan service worker untuk PWA (bisa dipasang di layar utama HP)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('service-worker.js').catch(function () {});
  });
}

// Tombol "Pasang aplikasi" (PWA) di halaman Semua menu
(function () {
  const panel = document.querySelector('[data-pasang]');
  if (!panel) return;
  const terpasang = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  if (terpasang) return;
  const tombol = panel.querySelector('[data-pasang-tombol]');
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  if (ios) { panel.hidden = false; panel.querySelector('[data-pasang-ios]').hidden = false; return; }
  let tunda = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); tunda = e; panel.hidden = false; tombol.hidden = false;
  });
  tombol.addEventListener('click', async function () {
    if (!tunda) return;
    tunda.prompt();
    await tunda.userChoice.catch(function () {});
    tunda = null; panel.hidden = true;
  });
  window.addEventListener('appinstalled', function () { panel.hidden = true; });
})();

// Pilihan yang langsung mengirim formulir saat diganti (pengganti onchange, aman untuk CSP)
document.addEventListener('change', function (e) {
  if (e.target.matches('[data-kirim-otomatis]') && e.target.form) e.target.form.submit();
});

// Tombol salin teks (mis. kode cadangan)
document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-salin]');
  if (!b) return;
  const teks = b.getAttribute('data-salin');
  const selesai = function () { b.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> Tersalin'; };
  if (navigator.clipboard) navigator.clipboard.writeText(teks).then(selesai).catch(function () {});
});

// ---------------------------------------------------------------------------
// Penampil gambar di dalam aplikasi (foto progres & dokumen gambar).
// Tombol Kembali di HP menutup penampil, bukan keluar dari aplikasi.
// ---------------------------------------------------------------------------
(function () {
  let lapis = null, daftar = [], idx = 0, terbuka = false;

  function buat() {
    lapis = document.createElement('div');
    lapis.className = 'lihat';
    lapis.setAttribute('role', 'dialog');
    lapis.setAttribute('aria-modal', 'true');
    lapis.setAttribute('aria-label', 'Penampil gambar');
    lapis.innerHTML =
      '<div class="lihat-atas"><span class="lihat-judul"></span><span class="lihat-hitung"></span>' +
      '<a class="lihat-btn lihat-unduh" aria-label="Unduh" download><i class="bi bi-download" aria-hidden="true"></i></a>' +
      '<button type="button" class="lihat-btn lihat-tutup" aria-label="Tutup"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>' +
      '<div class="lihat-wadah"><img alt=""><div class="lihat-muat" aria-hidden="true"></div></div>' +
      '<button type="button" class="lihat-btn lihat-geser lihat-kiri" aria-label="Sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>' +
      '<button type="button" class="lihat-btn lihat-geser lihat-kanan" aria-label="Berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' +
      '<div class="lihat-petunjuk">Ketuk dua kali untuk memperbesar · geser untuk foto lain</div>';
    document.body.appendChild(lapis);
    const wadah = lapis.querySelector('.lihat-wadah');
    const img = lapis.querySelector('img');
    lapis.querySelector('.lihat-tutup').addEventListener('click', tutup);
    lapis.querySelector('.lihat-kiri').addEventListener('click', function () { ke(idx - 1); });
    lapis.querySelector('.lihat-kanan').addEventListener('click', function () { ke(idx + 1); });
    wadah.addEventListener('click', function (e) { if (e.target === wadah) tutup(); });
    img.addEventListener('load', function () { lapis.classList.remove('memuat'); });
    img.addEventListener('error', function () { lapis.classList.remove('memuat'); });
    // Ketuk dua kali: perbesar / perkecil di titik yang diketuk
    let ketukTerakhir = 0;
    function zoom(e) {
      const besar = !lapis.classList.contains('zoom');
      const r = img.getBoundingClientRect();
      const fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
      lapis.classList.toggle('zoom', besar);
      if (besar) requestAnimationFrame(function () {
        wadah.scrollLeft = fx * img.offsetWidth - wadah.clientWidth / 2;
        wadah.scrollTop = fy * img.offsetHeight - wadah.clientHeight / 2;
      });
    }
    img.addEventListener('dblclick', zoom);
    img.addEventListener('touchend', function (e) {
      const kini = Date.now();
      if (kini - ketukTerakhir < 300 && e.changedTouches[0]) { e.preventDefault(); zoom(e.changedTouches[0]); }
      ketukTerakhir = kini;
    });
    // Geser kiri/kanan untuk foto lain (saat tidak diperbesar)
    let x0 = null, y0 = null;
    wadah.addEventListener('touchstart', function (e) { if (e.touches.length === 1) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; } }, { passive: true });
    wadah.addEventListener('touchend', function (e) {
      if (x0 === null || lapis.classList.contains('zoom') || !e.changedTouches[0]) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) ke(idx + (dx < 0 ? 1 : -1));
      else if (dy > 110 && Math.abs(dy) > Math.abs(dx) * 1.5) tutup(); // usap ke bawah = tutup
      x0 = null;
    }, { passive: true });
  }

  function tampil() {
    const a = daftar[idx];
    const img = lapis.querySelector('img');
    lapis.classList.remove('zoom');
    lapis.classList.add('memuat');
    img.src = a.getAttribute('href');
    img.alt = a.dataset.judul || '';
    lapis.querySelector('.lihat-judul').textContent = a.dataset.judul || '';
    lapis.querySelector('.lihat-hitung').textContent = daftar.length > 1 ? (idx + 1) + ' / ' + daftar.length : '';
    const unduh = lapis.querySelector('.lihat-unduh');
    const href = a.getAttribute('href');
    unduh.href = href.indexOf('download') === 0 ? href + (href.indexOf('?') >= 0 ? '&' : '?') + 'dl=1' : href;
    lapis.classList.toggle('satu', daftar.length < 2);
  }

  function ke(i) {
    if (daftar.length < 2) return;
    idx = (i + daftar.length) % daftar.length;
    tampil();
  }

  function buka(a) {
    if (!lapis) buat();
    const grup = a.closest('.foto-grid');
    daftar = grup ? Array.from(grup.querySelectorAll('[data-lihat="gambar"]')) : [a];
    idx = Math.max(0, daftar.indexOf(a));
    tampil();
    lapis.classList.add('aktif');
    document.documentElement.classList.add('lihat-terbuka');
    if (!terbuka) { history.pushState({ lihat: 1 }, ''); terbuka = true; }
    lapis.querySelector('.lihat-tutup').focus();
  }

  function tutupTampilan() {
    if (!lapis) return;
    lapis.classList.remove('aktif', 'zoom');
    document.documentElement.classList.remove('lihat-terbuka');
    lapis.querySelector('img').removeAttribute('src');
    terbuka = false;
  }
  function tutup() { if (terbuka) history.back(); else tutupTampilan(); }

  window.addEventListener('popstate', function () { if (terbuka) tutupTampilan(); });
  document.addEventListener('keydown', function (e) {
    if (!terbuka) return;
    if (e.key === 'Escape') tutup();
    else if (e.key === 'ArrowRight') ke(idx + 1);
    else if (e.key === 'ArrowLeft') ke(idx - 1);
  });
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[data-lihat="gambar"]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault();
    buka(a);
  });
})();

// Di aplikasi terpasang (Android/PWA), tautan internal yang biasanya membuka tab baru
// dibuka di layar yang sama, supaya tombol Kembali kembali ke aplikasi.
(function () {
  let app = false;
  try { app = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true || sessionStorage.getItem('tb_app') === '1'; } catch (e) {}
  if (!app) return;
  document.addEventListener('click', function (e) {
    const a = e.target.closest('a[target="_blank"]');
    if (!a || e.defaultPrevented) return;
    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin) return; // Google Maps, WhatsApp tetap membuka aplikasinya sendiri
    e.preventDefault();
    location.href = url.href;
  });
})();

// Tombol mata untuk melihat / menyembunyikan password di semua kolom password
(function () {
  document.querySelectorAll('input[type="password"]').forEach(function (inp) {
    if (inp.dataset.mata) return;
    inp.dataset.mata = '1';
    const bungkus = document.createElement('span');
    bungkus.className = 'sandi' + (inp.style.maxWidth ? ' sandi-sempit' : '');
    if (inp.style.maxWidth) { bungkus.style.maxWidth = inp.style.maxWidth; inp.style.maxWidth = ''; }
    inp.parentNode.insertBefore(bungkus, inp);
    bungkus.appendChild(inp);
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sandi-mata';
    btn.setAttribute('aria-label', 'Tampilkan password');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
    btn.addEventListener('click', function () {
      const lihat = inp.type === 'password';
      inp.type = lihat ? 'text' : 'password';
      btn.setAttribute('aria-pressed', lihat ? 'true' : 'false');
      btn.setAttribute('aria-label', lihat ? 'Sembunyikan password' : 'Tampilkan password');
      btn.innerHTML = '<i class="bi ' + (lihat ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
      inp.focus();
    });
    bungkus.appendChild(btn);
    // Sembunyikan lagi sebelum formulir dikirim (supaya browser menyimpan sebagai password)
    if (inp.form) inp.form.addEventListener('submit', function () { inp.type = 'password'; });
  });
})();

// ---------------------------------------------------------------------------
// Notifikasi di HP / laptop (Web Push)
// ---------------------------------------------------------------------------
(function () {
  const panel = document.querySelector('[data-notif-panel]');
  const ajak = document.querySelector('[data-notif-ajak]');
  if (!panel && !ajak) return;
  const didukung = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const csrf = (document.querySelector('meta[name="csrf"]') || {}).content || (document.querySelector('input[name="csrf"]') || {}).value || '';
  const status = panel && panel.querySelector('[data-notif-status]');
  const tampil = (sel, ya) => document.querySelectorAll(sel).forEach(el => { el.hidden = !ya; });
  const ke8 = s => { const p = '='.repeat((4 - s.length % 4) % 4); const b = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from(b, c => c.charCodeAt(0)); };
  const b64u = buf => btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const kirim = data => { const f = new FormData(); f.append('csrf', csrf); Object.keys(data).forEach(k => f.append(k, data[k])); return fetch('push', { method: 'POST', body: f, credentials: 'same-origin' }).then(r => r.json()); };

  function tulis(teks) { if (status) status.textContent = teks; }

  async function langganan() {
    const reg = await navigator.serviceWorker.ready;
    return reg.pushManager.getSubscription();
  }

  async function segarkan() {
    if (!didukung) {
      const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
      tulis(ios ? 'Di iPhone/iPad, pasang dulu aplikasi ke Layar Utama (Bagikan → Tambah ke Layar Utama), lalu buka dari ikon tersebut.' : 'Browser ini belum mendukung notifikasi. Gunakan Chrome versi terbaru.');
      tampil('[data-notif-aktifkan],[data-notif-tes],[data-notif-matikan]', false);
      if (ajak) ajak.hidden = true;
      return;
    }
    if (Notification.permission === 'denied') {
      tulis('Notifikasi diblokir. Buka pengaturan situs/aplikasi di HP, izinkan Notifikasi untuk Arsip TB, lalu kembali ke sini.');
      tampil('[data-notif-aktifkan],[data-notif-tes],[data-notif-matikan]', false);
      if (ajak) ajak.hidden = true;
      return;
    }
    const sub = await langganan();
    const aktif = !!sub && Notification.permission === 'granted';
    tulis(aktif ? 'Aktif di perangkat ini. Anda akan diberi tahu soal dokumen dan progres proyek.' : 'Belum aktif di perangkat ini.');
    tampil('[data-notif-aktifkan]', !aktif);
    tampil('[data-notif-tes],[data-notif-matikan]', aktif);
    let nanti = false;
    try { nanti = localStorage.getItem('tb_notif_nanti') === '1'; } catch (e) {}
    if (ajak) ajak.hidden = aktif || nanti;
  }

  async function aktifkan(tombol) {
    try {
      if (tombol) tombol.disabled = true;
      const izin = await Notification.requestPermission();
      if (izin !== 'granted') { await segarkan(); return; }
      const info = await fetch('push', { credentials: 'same-origin' }).then(r => r.json());
      const reg = await navigator.serviceWorker.ready;
      let sub = await reg.pushManager.getSubscription();
      if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: ke8(info.kunci) });
      const hasil = await kirim({ aksi: 'simpan', endpoint: sub.endpoint, p256dh: b64u(sub.getKey('p256dh')), auth: b64u(sub.getKey('auth')) });
      if (!hasil.ok) throw new Error(hasil.pesan || 'gagal');
      await kirim({ aksi: 'tes' });
    } catch (e) {
      tulis('Gagal mengaktifkan notifikasi. Periksa koneksi internet lalu coba lagi.');
    } finally {
      if (tombol) tombol.disabled = false;
      segarkan();
    }
  }

  document.addEventListener('click', async function (e) {
    const t = e.target.closest('[data-notif-aktifkan],[data-notif-tes],[data-notif-matikan],[data-notif-nanti]');
    if (!t) return;
    if (t.matches('[data-notif-aktifkan]')) aktifkan(t);
    else if (t.matches('[data-notif-tes]')) { await kirim({ aksi: 'tes' }); tulis('Notifikasi uji dikirim. Seharusnya muncul dalam beberapa detik.'); }
    else if (t.matches('[data-notif-matikan]')) {
      const sub = await langganan();
      if (sub) { await kirim({ aksi: 'hapus', endpoint: sub.endpoint }); await sub.unsubscribe(); }
      segarkan();
    } else if (t.matches('[data-notif-nanti]')) {
      try { localStorage.setItem('tb_notif_nanti', '1'); } catch (err) {}
      if (ajak) ajak.hidden = true;
    }
  });
  segarkan();
})();
