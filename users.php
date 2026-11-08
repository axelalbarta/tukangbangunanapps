<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
$pageTitle = 'User';
$activeMenu = 'users';

$role = input('role');
$sql = 'SELECT us.*, k.id AS klien_id FROM users us LEFT JOIN klien k ON k.user_id = us.id';
$params = [];
if (isset(ROLE_LABEL[$role])) { $sql .= ' WHERE us.role = ?'; $params[] = $role; }
$sql .= " ORDER BY us.aktif DESC, FIELD(us.role,'ceo','admin','tim','klien'), us.nama";
$list = q($sql, $params)->fetchAll();

$kembali = 'semua_menu';
require __DIR__ . '/includes/header.php';
$jumlah = array_count_values(array_column(q('SELECT role FROM users')->fetchAll(), 'role'));
$warnaRole = ['ceo' => 'pil-gelap', 'admin' => 'pil-tolak', 'tim' => 'pil-info', 'klien' => 'pil-draft'];
?>
<div class="page-head">
  <h1 class="h4 mb-0">User</h1>
</div>

<div class="chip-baris mb-3" aria-label="Filter role">
  <a href="users" class="chip <?= $role === '' ? 'aktif' : '' ?>">Semua <?= array_sum($jumlah) ?></a>
  <?php foreach (ROLE_LABEL as $k => $label): ?>
    <a href="users?role=<?= $k ?>" class="chip <?= $role === $k ? 'aktif' : '' ?>"><?= e($label) ?> <?= (int)($jumlah[$k] ?? 0) ?></a>
  <?php endforeach; ?>
</div>

<div class="daftar">
  <?php foreach ($list as $r):
      $href = ($r['role'] === 'klien' && $r['klien_id']) ? 'klien_form?id=' . (int)$r['klien_id'] : 'user_form?id=' . (int)$r['id'];
      $inisial = mb_strtoupper(mb_substr(trim($r['nama']), 0, 1)); ?>
    <a href="<?= e($href) ?>" class="baris" <?= (int)$r['aktif'] ? '' : 'style="opacity:.55"' ?>>
      <span class="avatar" style="width:40px;height:40px;font-size:1rem"><?= e($inisial) ?></span>
      <span class="baris-isi">
        <span class="baris-judul"><?= e($r['nama']) ?></span>
        <span class="baris-sub"><?= e($r['email']) ?></span>
        <span class="d-flex flex-wrap gap-1 mt-1">
          <span class="pil <?= $warnaRole[$r['role']] ?>"><?= e(ROLE_LABEL[$r['role']]) ?><?= $r['jabatan'] && $r['role'] !== 'klien' ? ' · ' . e($r['jabatan']) : '' ?></span>
          <?php if (!(int)$r['aktif']): ?><span class="pil pil-draft">Nonaktif</span><?php endif; ?>
          <?php if ((int)$r['totp_aktif']): ?><span class="pil pil-ok"><i class="bi bi-shield-check" aria-hidden="true"></i> 2 langkah</span><?php endif; ?>
        </span>
      </span>
      <span class="baris-kanan">
        <?= $r['login_terakhir'] ? 'Login<br>' . e(tgl($r['login_terakhir'])) : 'Belum<br>login' ?>
      </span>
    </a>
  <?php endforeach; ?>
  <?php if (!$list): ?><?= empty_state('bi-people', 'Belum ada user.') ?><?php endif; ?>
</div>
<p class="small text-muted mt-2 mb-3">Ketuk user untuk mengubah data, mengganti password, menonaktifkan akun, atau mereset verifikasi 2 langkah. Akun klien diatur lewat menu <a href="klien">Klien</a>.</p>

<a href="user_form" class="btn btn-brand w-100 tombol-bawah" style="height:48px;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-person-plus" aria-hidden="true"></i> User baru</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
