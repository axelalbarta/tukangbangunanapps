<?php
// Keamanan akun: verifikasi 2 langkah (Authenticator), kode cadangan, keluar dari semua perangkat, riwayat login.
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/qr.php';
$u = require_login();
$pageTitle = 'Keamanan akun';
$activeMenu = 'keamanan';
$kembali = 'semua_menu';
$errors = [];
$kodeCadanganBaru = $_SESSION['kode_cadangan_tampil'] ?? null;
unset($_SESSION['kode_cadangan_tampil']);
$aktif = (int)$u['totp_aktif'] === 1;

if (is_post()) {
    verify_csrf();
    $aksi = input('aksi');
    $pwBenar = fn() => password_verify((string)($_POST['password'] ?? ''), $u['password_hash']);

    if ($aksi === 'aktifkan' && !$aktif) {
        $rahasia = $_SESSION['totp_baru'] ?? '';
        $langkah = $rahasia ? totp_cek($rahasia, input('kode')) : null;
        if ($langkah === null) {
            $errors[] = 'Kode salah. Masukkan 6 digit terbaru dari aplikasi Authenticator (pastikan jam HP otomatis).';
        } else {
            [$teks, $hash] = kode_cadangan_baru();
            q('UPDATE users SET totp_rahasia=?, totp_aktif=1, totp_cadangan=?, totp_langkah=? WHERE id=?',
                [enkripsi($rahasia), json_encode($hash), $langkah, $u['id']]);
            unset($_SESSION['totp_baru']);
            log_aktivitas('ubah', 'user', (int)$u['id'], 'mengaktifkan verifikasi 2 langkah');
            $_SESSION['kode_cadangan_tampil'] = $teks;
            flash('success', 'Verifikasi 2 langkah aktif. Simpan kode cadangan di bawah ini.');
            redirect('keamanan');
        }
    } elseif ($aksi === 'cadangan' && $aktif) {
        if (!$pwBenar()) {
            $errors[] = 'Password salah.';
        } else {
            [$teks, $hash] = kode_cadangan_baru();
            q('UPDATE users SET totp_cadangan=? WHERE id=?', [json_encode($hash), $u['id']]);
            log_aktivitas('ubah', 'user', (int)$u['id'], 'membuat kode cadangan baru');
            $_SESSION['kode_cadangan_tampil'] = $teks;
            flash('success', 'Kode cadangan baru dibuat. Kode lama tidak berlaku lagi.');
            redirect('keamanan');
        }
    } elseif ($aksi === 'matikan' && $aktif && !wajib_2fa($u)) {
        $rahasia = dekripsi($u['totp_rahasia']);
        if (!$pwBenar()) {
            $errors[] = 'Password salah.';
        } elseif (!$rahasia || totp_cek($rahasia, input('kode')) === null) {
            $errors[] = 'Kode Authenticator salah.';
        } else {
            q('UPDATE users SET totp_aktif=0, totp_rahasia=NULL, totp_cadangan=NULL, totp_langkah=NULL WHERE id=?', [$u['id']]);
            log_aktivitas('ubah', 'user', (int)$u['id'], 'mematikan verifikasi 2 langkah');
            flash('warning', 'Verifikasi 2 langkah dimatikan.');
            redirect('keamanan');
        }
    } elseif ($aksi === 'lupakan_perangkat') {
        percaya_hapus_semua((int)$u['id']);
        log_aktivitas('ubah', 'user', (int)$u['id'], 'menghapus semua perangkat dipercaya');
        flash('success', 'Semua perangkat dipercaya dihapus. Kode 6 digit akan diminta lagi saat login berikutnya.');
        redirect('keamanan');
    } elseif ($aksi === 'keluar_semua') {
        percaya_hapus_semua((int)$u['id']);
        q('UPDATE users SET sesi_kunci=? WHERE id=?', [bin2hex(random_bytes(16)), $u['id']]);
        mulai_sesi_user(q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch());
        log_aktivitas('ubah', 'user', (int)$u['id'], 'keluar dari semua perangkat lain');
        flash('success', 'Semua perangkat lain sudah dikeluarkan. Perangkat ini tetap masuk.');
        redirect('keamanan');
    }
}

if (!$aktif) {
    $_SESSION['totp_baru'] ??= totp_rahasia_baru();
    $rahasiaBaru = $_SESSION['totp_baru'];
    $uri = totp_uri($rahasiaBaru, $u['email']);
}
$sisaCadangan = count(json_decode((string)$u['totp_cadangan'], true) ?: []);
$dipercaya = q('SELECT nama, dibuat, kedaluwarsa FROM perangkat_dipercaya WHERE user_id = ? AND kedaluwarsa > ? ORDER BY dibuat DESC', [$u['id'], date('Y-m-d H:i:s')])->fetchAll();
$riwayat = q("SELECT waktu, keterangan FROM log_aktivitas WHERE user_id = ? AND aksi = 'login' ORDER BY waktu DESC, id DESC LIMIT 8", [$u['id']])->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0">Keamanan akun</h1></div>
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<?php if (isset($_GET['wajib']) && !$aktif): ?>
  <div class="alert alert-warning"><b>Langkah wajib untuk <?= e(ROLE_LABEL[$u['role']]) ?>.</b> Demi keamanan arsip perusahaan, aktifkan verifikasi 2 langkah terlebih dahulu. Setelah itu aplikasi bisa dipakai seperti biasa.</div>
<?php endif; ?>

<?php if ($kodeCadanganBaru): ?>
  <section class="panel mb-3" style="border:2px solid var(--brand)">
    <h2 class="h6 fw-bold mb-1"><i class="bi bi-key" aria-hidden="true"></i> Kode cadangan Anda</h2>
    <p class="small text-muted mb-2">Simpan di tempat aman (foto, catat di kertas, atau simpan di Google Drive pribadi). Setiap kode hanya bisa dipakai <b>sekali</b> untuk login jika HP hilang. Kode ini tidak akan ditampilkan lagi.</p>
    <div class="row g-2 mb-2">
      <?php foreach ($kodeCadanganBaru as $k): ?><div class="col-6"><code class="d-block text-center p-2 rounded" style="background:var(--permukaan-2);font-size:1rem;letter-spacing:.06em"><?= e($k) ?></code></div><?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-light btn-sm" data-salin="<?= e(implode("\n", $kodeCadanganBaru)) ?>"><i class="bi bi-clipboard" aria-hidden="true"></i> Salin semua</button>
  </section>
<?php endif; ?>

<section class="panel mb-3">
  <div class="d-flex align-items-center gap-3 mb-2">
    <span class="baris-ikon <?= $aktif ? 'ikon-hijau' : 'ikon-kuning' ?>"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
    <div class="flex-grow-1">
      <h2 class="h6 fw-bold mb-0">Verifikasi 2 langkah</h2>
      <small class="text-muted">Login butuh password <b>dan</b> kode dari HP Anda. Password yang bocor saja tidak cukup untuk masuk.</small>
    </div>
    <span class="pil <?= $aktif ? 'pil-ok' : 'pil-tunggu' ?>"><?= $aktif ? 'Aktif' : 'Belum aktif' ?></span>
  </div>

  <?php if (!$aktif): ?>
    <ol class="small ps-3 mb-3">
      <li>Pasang aplikasi <b>Google Authenticator</b> atau <b>Microsoft Authenticator</b> dari Play Store / App Store.</li>
      <li>Di aplikasi itu, pindai kode QR di bawah. Jika membuka halaman ini dari HP yang sama, ketuk tombol <b>Tambahkan ke Authenticator</b> atau ketik kuncinya.</li>
      <li>Masukkan 6 digit kode yang muncul untuk <b>Arsip TB</b>.</li>
    </ol>
    <div class="text-center mb-3">
      <div class="d-inline-block p-2 rounded-3" style="background:#fff"><?= qr_svg($uri, 200) ?></div>
    </div>
    <div class="d-grid gap-2 mb-3">
      <a class="btn btn-light" href="<?= e($uri) ?>"><i class="bi bi-phone" aria-hidden="true"></i> Tambahkan ke Authenticator (di HP ini)</a>
    </div>
    <div class="small text-muted mb-1">Atau ketik kunci ini secara manual (jenis: berbasis waktu):</div>
    <code class="d-block p-2 rounded mb-3 text-center" style="background:var(--permukaan-2);font-size:.95rem;letter-spacing:.08em;word-break:break-all"><?= e(trim(chunk_split($rahasiaBaru, 4, ' '))) ?></code>
    <form method="post" class="d-flex gap-2" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="aktifkan">
      <input class="form-control text-center" name="kode" required inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="6 digit kode" autocomplete="one-time-code" style="letter-spacing:.3em;font-weight:700" aria-label="Kode 6 digit">
      <button class="btn btn-brand px-4">Aktifkan</button>
    </form>
  <?php else: ?>
    <p class="small mb-3">Kode cadangan tersisa: <b><?= $sisaCadangan ?></b> dari 8.</p>
    <form method="post" class="d-flex gap-2 mb-3" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="cadangan">
      <input class="form-control" type="password" name="password" required placeholder="Password Anda" autocomplete="current-password" aria-label="Password">
      <button class="btn btn-light text-nowrap">Buat kode cadangan baru</button>
    </form>
    <?php if (!wajib_2fa($u)): ?>
      <details class="small">
        <summary class="text-muted">Matikan verifikasi 2 langkah</summary>
        <form method="post" class="d-flex flex-wrap gap-2 mt-2" autocomplete="off">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="matikan">
          <input class="form-control" style="max-width:200px" type="password" name="password" required placeholder="Password" aria-label="Password">
          <input class="form-control" style="max-width:140px" name="kode" required inputmode="numeric" maxlength="7" placeholder="Kode 6 digit" aria-label="Kode 6 digit">
          <button class="btn btn-outline-danger">Matikan</button>
        </form>
      </details>
    <?php else: ?>
      <p class="small text-muted mb-0">Verifikasi 2 langkah wajib untuk role <?= e(ROLE_LABEL[$u['role']]) ?>. Jika HP hilang, login memakai kode cadangan, lalu minta Admin mereset verifikasi Anda.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($aktif): ?>
<section class="panel mb-3">
  <h2 class="h6 fw-bold mb-1">Perangkat dipercaya</h2>
  <p class="small text-muted mb-2">Di perangkat ini kode 6 digit tidak diminta lagi selama 30 hari (dipilih saat verifikasi). Mengganti password juga membatalkan semuanya.</p>
  <?php if ($dipercaya): ?>
    <ul class="small ps-3 mb-2">
      <?php foreach ($dipercaya as $d): ?><li><?= e($d['nama']) ?> · sejak <?= e(tgl($d['dibuat'])) ?>, berlaku s.d. <?= e(tgl($d['kedaluwarsa'])) ?></li><?php endforeach; ?>
    </ul>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="lupakan_perangkat">
      <button class="btn btn-light btn-sm">Hapus semua perangkat dipercaya</button></form>
  <?php else: ?>
    <p class="small mb-0">Belum ada perangkat dipercaya.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel mb-3">
  <h2 class="h6 fw-bold mb-1">Perangkat yang sedang login</h2>
  <p class="small text-muted mb-2">Lupa logout di HP/komputer lain, atau curiga akun dipakai orang lain? Keluarkan semua perangkat lain sekarang.</p>
  <form method="post" data-confirm="Keluarkan akun ini dari semua perangkat lain?">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="keluar_semua">
    <button class="btn btn-outline-danger w-100"><i class="bi bi-phone-flip" aria-hidden="true"></i> Keluar dari semua perangkat lain</button>
  </form>
</section>

<section>
  <div class="panel-judul"><h2>Riwayat login</h2></div>
  <div class="daftar">
    <?php foreach ($riwayat as $r): ?>
      <div class="baris">
        <span class="baris-ikon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></span>
        <span class="baris-isi"><span class="baris-judul"><?= e(tgl_waktu($r['waktu'])) ?></span><span class="baris-sub"><?= preg_match('/^[0-9a-f.:]{3,}/i', (string)$r['keterangan']) ? 'IP ' : '' ?><?= e($r['keterangan']) ?></span></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$riwayat): ?><?= empty_state('bi-clock-history', 'Belum ada riwayat.') ?><?php endif; ?>
  </div>
  <p class="small text-muted mt-2">Ada login yang tidak Anda kenali? Segera ganti password di menu Profil dan keluarkan semua perangkat.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
