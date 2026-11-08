// Peta lokasi proyek (Leaflet + OpenStreetMap, gratis tanpa API key)
(function () {
  const PUSAT_AWAL = [-0.8615, 134.062]; // Manokwari
  // Sumber peta tanpa API key. Jika satu sumber gagal, otomatis pindah ke sumber berikutnya.
  const RP = 'strict-origin-when-cross-origin';
  const SUMBER = [
    { nama: 'Peta jalan', url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
      opsi: { maxZoom: 19, attribution: 'Peta &copy; Esri', referrerPolicy: RP } },
    { nama: 'OpenStreetMap', url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
      opsi: { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>', referrerPolicy: RP } },
  ];
  const SATELIT = { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    opsi: { maxZoom: 19, attribution: 'Citra &copy; Esri', referrerPolicy: RP } };
  const LABEL = { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
    opsi: { maxZoom: 19, referrerPolicy: RP } };

  /** Pasang lapisan peta + pilihan Satelit, dengan pengalih otomatis bila sumber gagal */
  function pasangLapisan(peta) {
    const lapisan = SUMBER.map(s => L.tileLayer(s.url, s.opsi));
    const satelit = L.layerGroup([L.tileLayer(SATELIT.url, SATELIT.opsi), L.tileLayer(LABEL.url, LABEL.opsi)]);
    const pilihan = {};
    SUMBER.forEach((s, i) => { pilihan[s.nama] = lapisan[i]; });
    pilihan['Satelit'] = satelit;
    let aktif = 0;
    function pantau(i) {
      let gagal = 0, berhasil = false;
      lapisan[i].on('tileload', () => { berhasil = true; });
      lapisan[i].on('tileerror', () => {
        if (berhasil || aktif !== i || ++gagal < 4 || i + 1 >= lapisan.length) return;
        peta.removeLayer(lapisan[i]);
        aktif = i + 1;
        lapisan[aktif].addTo(peta);
        pantau(aktif);
      });
    }
    lapisan[0].addTo(peta);
    pantau(0);
    L.control.layers(pilihan, null, { position: 'topright' }).addTo(peta);
  }

  const ikonPin = () => L.divIcon({
    className: 'pin-tb',
    html: '<svg viewBox="0 0 32 44" width="32" height="44"><path d="M16 1C8 1 1.5 7.4 1.5 15.3 1.5 26 16 43 16 43s14.5-17 14.5-27.7C30.5 7.4 24 1 16 1z" fill="#d71920" stroke="#fff" stroke-width="2"/><circle cx="16" cy="15.5" r="5.5" fill="#fff"/></svg>',
    iconSize: [32, 44], iconAnchor: [16, 43], popupAnchor: [0, -38],
  });
  const fmt = n => (Math.round(n * 1e6) / 1e6).toString();

  /** Baca koordinat dari teks: "-0.86, 134.06" atau link Google Maps */
  function bacaKoordinat(teks) {
    const t = decodeURIComponent(teks.trim());
    const pola = [
      /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,            // titik pin: ...!3d-0.86!4d134.06
      /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,            // tengah peta: .../@-0.86,134.06,17z
      /[?&](?:q|ll|query|destination)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
      /^(-?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)$/, // -0.86, 134.06
    ];
    for (const p of pola) {
      const m = t.match(p);
      if (m) {
        const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
        if (Math.abs(lat) <= 90 && Math.abs(lng) <= 180) return [lat, lng];
      }
    }
    return null;
  }

  /** Form proyek: pilih titik lokasi */
  window.petaPilihLokasi = function () {
    const el = document.getElementById('peta');
    if (!el) return;
    const inLat = document.getElementById('lat'), inLng = document.getElementById('lng');
    const label = document.getElementById('peta-koordinat'), pesan = document.getElementById('peta-pesan');
    const cari = document.getElementById('peta-cari');
    const lokasiTeks = document.querySelector('input[name="lokasi"]');
    const ada = inLat.value !== '' && inLng.value !== '';
    let peta = null, pin = null;

    const info = (msg, bahaya) => { pesan.textContent = msg || ''; pesan.className = 'small ' + (bahaya ? 'text-danger' : 'text-success'); };
    function setTitik(lat, lng, zoom) {
      inLat.value = fmt(lat); inLng.value = fmt(lng);
      label.textContent = fmt(lat) + ', ' + fmt(lng);
      if (!peta) return;
      if (pin) pin.setLatLng([lat, lng]);
      else {
        pin = L.marker([lat, lng], { draggable: true, icon: ikonPin(), autoPan: true }).addTo(peta);
        pin.on('dragend', () => { const p = pin.getLatLng(); setTitik(p.lat, p.lng); });
      }
      if (zoom) peta.setView([lat, lng], zoom);
    }

    if (window.L) {
      peta = L.map(el, { scrollWheelZoom: false, tap: true }).setView(ada ? [+inLat.value, +inLng.value] : PUSAT_AWAL, ada ? 17 : 13);
      pasangLapisan(peta);
      if (ada) setTitik(+inLat.value, +inLng.value);
      peta.on('click', e => { setTitik(e.latlng.lat, e.latlng.lng); info(''); });
      el.addEventListener('focus', () => peta.scrollWheelZoom.enable());
      setTimeout(() => peta.invalidateSize(), 200);
    } else {
      el.innerHTML = '<div class="p-3 small text-muted">Peta tidak bisa dimuat (perlu koneksi internet). Anda tetap bisa menempel koordinat atau link Google Maps di kolom di atas.</div>';
      el.style.height = 'auto';
    }

    async function jalankanCari() {
      const q = cari.value.trim();
      if (!q) { info('Ketik alamat atau tempel koordinat dulu.', true); return; }
      const k = bacaKoordinat(q);
      if (k) { setTitik(k[0], k[1], 17); info('Titik dipasang dari koordinat.'); return; }
      if (/goo\.gl|maps\.app/.test(q)) {
        info('Link pendek Google Maps tidak bisa dibaca langsung. Buka link itu, lalu salin angka koordinatnya (contoh: -0.8615, 134.0620).', true);
        return;
      }
      info('Mencari…');
      try {
        const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=id&accept-language=id&q=' + encodeURIComponent(q),
          { referrerPolicy: 'strict-origin-when-cross-origin' });
        const hasil = await res.json();
        if (!hasil.length) { info('Alamat tidak ditemukan. Coba nama jalan/kelurahan lain, atau klik langsung di peta.', true); return; }
        setTitik(+hasil[0].lat, +hasil[0].lon, 17);
        info('Ditemukan: ' + hasil[0].display_name + '. Geser pin bila kurang tepat.');
      } catch (e) {
        info('Pencarian gagal. Periksa koneksi internet, atau klik langsung di peta.', true);
      }
    }
    document.getElementById('peta-tombol-cari').addEventListener('click', jalankanCari);
    cari.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); jalankanCari(); } });
    if (!cari.value && lokasiTeks && lokasiTeks.value) cari.placeholder = 'Cari alamat (mis. ' + lokasiTeks.value + ') atau tempel koordinat';

    document.getElementById('peta-saya').addEventListener('click', () => {
      if (!navigator.geolocation) { info('Browser ini tidak mendukung lokasi.', true); return; }
      info('Mengambil lokasi Anda…');
      navigator.geolocation.getCurrentPosition(
        pos => { setTitik(pos.coords.latitude, pos.coords.longitude, 18); info('Lokasi Anda dipasang (akurasi ± ' + Math.round(pos.coords.accuracy) + ' m).'); },
        err => info(err.code === 1 ? 'Izin lokasi ditolak. Izinkan akses lokasi di browser, lalu coba lagi.' : 'Lokasi tidak bisa diambil. Pastikan GPS aktif. (Di hosting, fitur ini butuh HTTPS.)', true),
        { enableHighAccuracy: true, timeout: 15000 }
      );
    });

    document.getElementById('peta-hapus').addEventListener('click', () => {
      inLat.value = ''; inLng.value = '';
      label.textContent = 'Belum ditandai';
      if (pin) { pin.remove(); pin = null; }
      info('');
    });
  };

  /** Halaman detail: tampilkan titik saja */
  window.petaTampil = function (id, lat, lng, judul) {
    const el = document.getElementById(id);
    if (!el) return;
    if (!window.L) { el.innerHTML = '<div class="p-3 small text-muted">Peta tidak bisa dimuat (perlu koneksi internet).</div>'; el.style.height = 'auto'; return; }
    const peta = L.map(el, { scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false }).setView([lat, lng], 16);
    pasangLapisan(peta);
    const m = L.marker([lat, lng], { icon: ikonPin() }).addTo(peta);
    if (judul) m.bindTooltip(judul, { direction: 'top', offset: [0, -40] });
  };
})();
