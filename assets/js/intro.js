// Animasi pembuka "Membangun": tampil sekali setiap aplikasi baru dibuka (versi terpasang / Android).
(function () {
  const html = document.documentElement;
  if (!html.classList.contains('intro-aktif')) return;
  const lapis = document.querySelector('.intro');
  if (!lapis) { html.classList.remove('intro-aktif'); return; }
  const img = lapis.querySelector('img');
  let selesai = false;
  function tutup() {
    if (selesai) return;
    selesai = true;
    try { sessionStorage.setItem('tb_intro', '1'); } catch (e) {}
    lapis.classList.add('intro-keluar');
    setTimeout(function () { html.classList.remove('intro-aktif'); lapis.remove(); }, 380);
  }
  img.addEventListener('load', function () { setTimeout(tutup, 2000); });
  img.addEventListener('error', tutup);
  img.src = img.dataset.src;
  lapis.addEventListener('click', tutup);   // sentuh layar = lewati
  setTimeout(tutup, 4000);                  // pengaman bila gambar lambat dimuat
})();
