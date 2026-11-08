<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin', 'tim');
$pageTitle = 'Lapor Progres';
$activeMenu = 'progres';

[$wp, $pp] = filter_proyek_sql($u);
$proyekList = q("SELECT p.id, p.kode_proyek, p.nama_proyek, p.progres_persen FROM proyek p
                 WHERE $wp AND p.status IN ('berjalan','tertunda') ORDER BY p.nama_proyek", $pp)->fetchAll();
$ajax = input('ajax') === '1';

$data = ['proyek_id' => input_int('proyek_id'), 'tanggal' => date('Y-m-d'), 'persentase' => null, 'catatan' => '', 'kendala' => ''];
$errors = [];
if ($data['proyek_id'] && ($pr = proyek_akses($data['proyek_id'], $u))) {
    $data['persentase'] = (int)$pr['progres_persen'];
}

if (is_post()) {
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Total ukuran foto terlalu besar. Kurangi jumlah foto lalu coba lagi.';
    } else {
        verify_csrf();
        $data = [
            'proyek_id'  => input_int('proyek_id'),
            'tanggal'    => input('tanggal'),
            'persentase' => max(0, min(100, input_int('persentase'))),
            'catatan'    => input('catatan'),
            'kendala'    => input('kendala'),
        ];
        $proyek = proyek_akses($data['proyek_id'], $u);
        if (!$proyek) $errors[] = 'Pilih proyek.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tanggal'])) $errors[] = 'Tanggal tidak valid.';
        if ($data['catatan'] === '') $errors[] = 'Catatan pekerjaan wajib diisi.';

        $files = [];
        if (!empty($_FILES['foto']['name']) && is_array($_FILES['foto']['name'])) {
            foreach ($_FILES['foto']['name'] as $i => $name) {
                $err = $_FILES['foto']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = "Foto \"$name\" gagal diunggah (kode $err).";
                    continue;
                }
                $files[] = $_FILES['foto']['tmp_name'][$i];
            }
        }
        if (count($files) > 20) $errors[] = 'Maksimal 20 foto per laporan.';

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $tersimpan = [];
            try {
                q('INSERT INTO progres (proyek_id, tanggal, persentase, catatan, kendala, dibuat_oleh) VALUES (?,?,?,?,?,?)',
                    [$data['proyek_id'], $data['tanggal'], $data['persentase'], $data['catatan'], $data['kendala'] ?: null, $u['id']]);
                $prid = (int)$pdo->lastInsertId();
                $dir = UPLOAD_DIR . '/foto';
                if (!siapkan_folder_upload('foto')) throw new RuntimeException(pesan_izin_uploads());
                foreach ($files as $tmp) {
                    $nama = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.jpg';
                    if (!simpan_foto($tmp, $dir . '/' . $nama)) {
                        throw new RuntimeException('Salah satu file bukan foto yang valid (gunakan JPG/PNG).');
                    }
                    $tersimpan[] = $dir . '/' . $nama;
                    q('INSERT INTO progres_foto (progres_id, path_file) VALUES (?,?)', [$prid, 'foto/' . $nama]);
                }
                // Progres proyek mengikuti laporan dengan tanggal terbaru
                q('UPDATE proyek SET progres_persen = (SELECT persentase FROM progres WHERE proyek_id = ? ORDER BY tanggal DESC, id DESC LIMIT 1) WHERE id = ?',
                    [$data['proyek_id'], $data['proyek_id']]);
                $pdo->commit();
                log_aktivitas('lapor', 'progres', $prid, $proyek['nama_proyek'] . ' — ' . $data['persentase'] . '%, ' . count($files) . ' foto');
                notif_kirim(tanpa_saya(array_merge(notif_klien_proyek((int)$data['proyek_id']), notif_pimpinan())),
                    'Progres ' . $proyek['nama_proyek'] . ': ' . $data['persentase'] . '%',
                    mb_strimwidth($data['catatan'], 0, 120, '…') . ($files ? ' (' . count($files) . ' foto)' : ''),
                    'proyek_detail?id=' . $data['proyek_id'] . '&tab=progres', 'progres-' . $data['proyek_id']);
                flash('success', 'Laporan progres tersimpan' . ($files ? ' dengan ' . count($files) . ' foto.' : '.'));
                $to = 'proyek_detail?id=' . $data['proyek_id'] . '&tab=progres';
                if ($ajax) json_out(['ok' => true, 'redirect' => $to]);
                redirect($to);
            } catch (Throwable $ex) {
                $pdo->rollBack();
                foreach ($tersimpan as $f) @unlink($f);
                $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Gagal menyimpan laporan.' . (APP_DEBUG ? ' ' . $ex->getMessage() : '');
            }
        }
    }
    if ($ajax) json_out(['ok' => false, 'errors' => $errors], 422);
}

$kembali = $data['proyek_id'] ? 'proyek_detail?id=' . $data['proyek_id'] . '&tab=progres' : 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0">Lapor Progres</h1>
</div>

<div id="form-errors"><?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?></div>

<?php if (!$proyekList): ?>
  <?= empty_state('bi-buildings', 'Tidak ada proyek berjalan yang bisa dilaporkan.') ?>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card" id="form-progres"><div class="card-body">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label">Proyek *</label>
      <select class="form-select form-select-lg" name="proyek_id" required id="pilih-proyek">
        <option value="">— pilih proyek —</option>
        <?php foreach ($proyekList as $p): ?>
          <option value="<?= (int)$p['id'] ?>" data-persen="<?= (int)$p['progres_persen'] ?>" <?= (int)$p['id'] === $data['proyek_id'] ? 'selected' : '' ?>><?= e($p['nama_proyek']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Tanggal *</label>
      <input class="form-control form-control-lg" type="date" name="tanggal" value="<?= e($data['tanggal']) ?>" max="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-12">
      <label class="form-label d-flex justify-content-between">Progres keseluruhan <strong><span id="persen-label"><?= (int)$data['persentase'] ?></span>%</strong></label>
      <input type="range" class="form-range" name="persentase" id="persen" min="0" max="100" step="5" value="<?= (int)$data['persentase'] ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Pekerjaan hari ini *</label>
      <textarea class="form-control" name="catatan" rows="3" required placeholder="mis. Pengecoran kolom lantai 1 selesai, mulai pasang bekisting balok"><?= e($data['catatan']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Kendala <span class="text-muted">(jika ada)</span></label>
      <textarea class="form-control" name="kendala" rows="2" placeholder="mis. Material besi terlambat datang"><?= e($data['kendala']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Foto</label>
      <div class="d-flex gap-2 flex-wrap">
        <label class="btn btn-outline-dark"><i class="bi bi-camera"></i> Ambil foto
          <input type="file" accept="image/*" capture="environment" class="d-none foto-input"></label>
        <label class="btn btn-outline-dark"><i class="bi bi-images"></i> Pilih dari galeri
          <input type="file" name="foto[]" accept="image/*" multiple class="d-none foto-input"></label>
      </div>
      <div class="form-text">Foto otomatis diperkecil sebelum dikirim supaya hemat kuota.</div>
      <div class="foto-grid mt-2" id="preview"></div>
    </div>
  </div>
  <div class="mt-4"><button class="btn btn-brand btn-lg w-100 w-md-auto" id="btn-kirim"><i class="bi bi-send"></i> Kirim laporan</button></div>
</div></form>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
(function () {
  const form = document.getElementById('form-progres');
  if (!form) return;
  const range = document.getElementById('persen'), label = document.getElementById('persen-label');
  range.addEventListener('input', () => label.textContent = range.value);
  document.getElementById('pilih-proyek').addEventListener('change', e => {
    const opt = e.target.selectedOptions[0];
    if (opt && opt.dataset.persen) { range.value = opt.dataset.persen; label.textContent = opt.dataset.persen; }
  });

  // Kumpulkan foto dari kamera & galeri
  const fotos = [];
  const preview = document.getElementById('preview');
  function render() {
    preview.innerHTML = '';
    fotos.forEach((f, i) => {
      const box = document.createElement('div');
      box.className = 'foto-thumb';
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      const del = document.createElement('button');
      del.type = 'button'; del.className = 'foto-del'; del.innerHTML = '&times;'; del.title = 'Hapus foto';
      del.onclick = () => { fotos.splice(i, 1); render(); };
      box.append(img, del);
      preview.append(box);
    });
  }
  form.querySelectorAll('.foto-input').forEach(inp => inp.addEventListener('change', () => {
    for (const f of inp.files) if (f.type.startsWith('image/')) fotos.push(f);
    inp.value = '';
    render();
  }));

  function kompres(file) {
    return new Promise(resolve => {
      const img = new Image();
      img.onload = () => {
        const max = 1600, r = Math.min(1, max / Math.max(img.width, img.height));
        const c = document.createElement('canvas');
        c.width = Math.round(img.width * r); c.height = Math.round(img.height * r);
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(b => resolve(b || file), 'image/jpeg', 0.8);
        URL.revokeObjectURL(img.src);
      };
      img.onerror = () => resolve(file);
      img.src = URL.createObjectURL(file);
    });
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    const btn = document.getElementById('btn-kirim');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim…';
    const fd = new FormData(form);
    fd.delete('foto[]');
    fd.append('ajax', '1');
    for (let i = 0; i < fotos.length; i++) {
      btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses foto ' + (i + 1) + '/' + fotos.length;
      fd.append('foto[]', await kompres(fotos[i]), 'foto' + i + '.jpg');
    }
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim…';
    try {
      const res = await fetch(form.action || location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) { location.href = json.redirect; return; }
      document.getElementById('form-errors').innerHTML = '<div class="alert alert-danger">' + json.errors.map(s => s.replace(/</g, '&lt;')).join('<br>') + '</div>';
    } catch (err) {
      document.getElementById('form-errors').innerHTML = '<div class="alert alert-danger">Gagal mengirim. Periksa koneksi internet lalu coba lagi.</div>';
    }
    window.scrollTo(0, 0);
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send"></i> Kirim laporan';
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
