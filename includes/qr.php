<?php
// Pembuat kode QR sederhana (mode byte, koreksi galat M, versi 1–10) tanpa library luar.
// Dipakai untuk menampilkan QR verifikasi 2 langkah. Diadaptasi dari algoritma Project Nayuki (MIT).

function qr_matriks(string $teks): array
{
    $ECC = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];   // codeword koreksi per blok (level M)
    $BLOK = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];            // jumlah blok (level M)
    $data = array_values(unpack('C*', $teks) ?: []);
    $n = count($data);

    $modulMentah = function (int $v): int {
        $r = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $na = intdiv($v, 7) + 2;
            $r -= (25 * $na - 10) * $na - 55;
            if ($v >= 7) $r -= 36;
        }
        return $r;
    };
    $ver = 0;
    for ($v = 1; $v <= 10; $v++) {
        $kapasitas = intdiv($modulMentah($v), 8) - $ECC[$v] * $BLOK[$v];
        $bitHitung = $v <= 9 ? 8 : 16;
        if (4 + $bitHitung + $n * 8 <= $kapasitas * 8) { $ver = $v; break; }
    }
    if (!$ver) throw new InvalidArgumentException('Teks terlalu panjang untuk QR.');
    $kapasitas = intdiv($modulMentah($ver), 8) - $ECC[$ver] * $BLOK[$ver];

    // Bit data
    $bits = [];
    $tambah = function (int $nilai, int $panjang) use (&$bits) {
        for ($i = $panjang - 1; $i >= 0; $i--) $bits[] = ($nilai >> $i) & 1;
    };
    $tambah(4, 4);
    $tambah($n, $ver <= 9 ? 8 : 16);
    foreach ($data as $b) $tambah($b, 8);
    $tambah(0, min(4, $kapasitas * 8 - count($bits)));
    $tambah(0, (8 - count($bits) % 8) % 8);
    for ($pad = 0xEC; count($bits) < $kapasitas * 8; $pad ^= 0xEC ^ 0x11) $tambah($pad, 8);
    $kata = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $b = 0;
        for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bits[$i + $j];
        $kata[] = $b;
    }

    // Reed–Solomon
    $kali = function (int $x, int $y): int {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    };
    $derajat = $ECC[$ver];
    $pembagi = array_fill(0, $derajat - 1, 0);
    $pembagi[] = 1;
    $akar = 1;
    for ($i = 0; $i < $derajat; $i++) {
        for ($j = 0; $j < $derajat; $j++) {
            $pembagi[$j] = $kali($pembagi[$j], $akar);
            if ($j + 1 < $derajat) $pembagi[$j] ^= $pembagi[$j + 1];
        }
        $akar = $kali($akar, 0x02);
    }
    $sisa = function (array $dat) use ($pembagi, $derajat, $kali): array {
        $hasil = array_fill(0, $derajat, 0);
        foreach ($dat as $b) {
            $faktor = $b ^ array_shift($hasil);
            $hasil[] = 0;
            for ($i = 0; $i < $derajat; $i++) $hasil[$i] ^= $kali($pembagi[$i], $faktor);
        }
        return $hasil;
    };
    $jmlBlok = $BLOK[$ver];
    $total = intdiv($modulMentah($ver), 8);
    $blokPendek = $jmlBlok - $total % $jmlBlok;
    $panjangPendek = intdiv($total, $jmlBlok);
    $blok = [];
    for ($i = 0, $k = 0; $i < $jmlBlok; $i++) {
        $len = $panjangPendek - $derajat + ($i < $blokPendek ? 0 : 1);
        $dat = array_slice($kata, $k, $len);
        $k += $len;
        $ecc = $sisa($dat);
        if ($i < $blokPendek) $dat[] = 0;
        $blok[] = array_merge($dat, $ecc);
    }
    $akhir = [];
    for ($i = 0; $i < count($blok[0]); $i++) {
        foreach ($blok as $j => $bl) {
            if ($i !== $panjangPendek - $derajat || $j >= $blokPendek) $akhir[] = $bl[$i];
        }
    }

    // Pola tetap
    $ukuran = $ver * 4 + 17;
    $m = array_fill(0, $ukuran, array_fill(0, $ukuran, false));
    $f = $m;
    $set = function (int $x, int $y, bool $gelap) use (&$m, &$f) { $m[$y][$x] = $gelap; $f[$y][$x] = true; };
    for ($i = 0; $i < $ukuran; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$ukuran - 4, 3], [3, $ukuran - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $d = max(abs($dx), abs($dy));
            $xx = $cx + $dx; $yy = $cy + $dy;
            if ($xx >= 0 && $xx < $ukuran && $yy >= 0 && $yy < $ukuran) $set($xx, $yy, $d !== 2 && $d !== 4);
        }
    }
    $posAlign = [];
    if ($ver > 1) {
        $na = intdiv($ver, 7) + 2;
        $langkah = (int)(ceil(($ver * 4 + 4) / ($na * 2 - 2)) * 2);
        $posAlign = [6];
        for ($p = $ukuran - 7; count($posAlign) < $na; $p -= $langkah) array_splice($posAlign, 1, 0, [$p]);
    }
    $na = count($posAlign);
    for ($i = 0; $i < $na; $i++) for ($j = 0; $j < $na; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) {
            $set($posAlign[$i] + $dx, $posAlign[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
        }
    }
    $format = function (int $mask) use ($set, $ukuran) {
        $d = (0 << 3) | $mask; // level M = 0
        $r = $d;
        for ($i = 0; $i < 10; $i++) $r = ($r << 1) ^ (($r >> 9) * 0x537);
        $b = (($d << 10) | $r) ^ 0x5412;
        $bit = fn(int $i) => (($b >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $set(8, $i, $bit($i));
        $set(8, 7, $bit(6)); $set(8, 8, $bit(7)); $set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, $bit($i));
        for ($i = 0; $i < 8; $i++) $set($ukuran - 1 - $i, 8, $bit($i));
        for ($i = 8; $i < 15; $i++) $set(8, $ukuran - 15 + $i, $bit($i));
        $set(8, $ukuran - 8, true);
    };
    $format(0);
    if ($ver >= 7) {
        $r = $ver;
        for ($i = 0; $i < 12; $i++) $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
        $b = ($ver << 12) | $r;
        for ($i = 0; $i < 18; $i++) {
            $g = (($b >> $i) & 1) === 1;
            $a = $ukuran - 11 + $i % 3; $c = intdiv($i, 3);
            $set($a, $c, $g); $set($c, $a, $g);
        }
    }

    // Isi data
    $i = 0;
    $totalBit = count($akhir) * 8;
    for ($kanan = $ukuran - 1; $kanan >= 1; $kanan -= 2) {
        if ($kanan === 6) $kanan = 5;
        for ($v = 0; $v < $ukuran; $v++) for ($j = 0; $j < 2; $j++) {
            $x = $kanan - $j;
            $naik = (($kanan + 1) & 2) === 0;
            $y = $naik ? $ukuran - 1 - $v : $v;
            if (!$f[$y][$x] && $i < $totalBit) {
                $m[$y][$x] = (($akhir[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                $i++;
            }
        }
    }

    // Pilih mask dengan penalti terkecil
    $terbaik = null; $nilaiTerbaik = PHP_INT_MAX; $dasar = $m;
    for ($mask = 0; $mask < 8; $mask++) {
        $m = $dasar;
        for ($y = 0; $y < $ukuran; $y++) for ($x = 0; $x < $ukuran; $x++) {
            if ($f[$y][$x]) continue;
            $balik = match ($mask) {
                0 => ($x + $y) % 2 === 0, 1 => $y % 2 === 0, 2 => $x % 3 === 0, 3 => ($x + $y) % 3 === 0,
                4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, 5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0, 7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
            };
            if ($balik) $m[$y][$x] = !$m[$y][$x];
        }
        $format($mask);
        $p = 0; $gelap = 0;
        for ($y = 0; $y < $ukuran; $y++) {
            $runX = 1; $runY = 1;
            for ($x = 0; $x < $ukuran; $x++) {
                if ($m[$y][$x]) $gelap++;
                if ($x > 0) {
                    if ($m[$y][$x] === $m[$y][$x - 1]) { $runX++; if ($runX === 5) $p += 3; elseif ($runX > 5) $p++; } else $runX = 1;
                    if ($m[$x][$y] === $m[$x - 1][$y]) { $runY++; if ($runY === 5) $p += 3; elseif ($runY > 5) $p++; } else $runY = 1;
                }
                if ($x > 0 && $y > 0 && $m[$y][$x] === $m[$y][$x - 1] && $m[$y][$x] === $m[$y - 1][$x] && $m[$y][$x] === $m[$y - 1][$x - 1]) $p += 3;
            }
        }
        $total2 = $ukuran * $ukuran;
        $p += intdiv(abs($gelap * 20 - $total2 * 10) + $total2 - 1, $total2) * 10 - 10;
        if ($p < $nilaiTerbaik) { $nilaiTerbaik = $p; $terbaik = $m; }
    }
    return $terbaik;
}

/** QR sebagai SVG (bisa langsung ditaruh di halaman) */
function qr_svg(string $teks, int $px = 220): string
{
    $m = qr_matriks($teks);
    $n = count($m);
    $tepi = 4;
    $s = $n + $tepi * 2;
    $jalur = '';
    foreach ($m as $y => $baris) foreach ($baris as $x => $gelap) {
        if ($gelap) $jalur .= 'M' . ($x + $tepi) . ' ' . ($y + $tepi) . 'h1v1h-1z';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $s . ' ' . $s . '" width="' . $px . '" height="' . $px
        . '" shape-rendering="crispEdges" role="img" aria-label="Kode QR"><rect width="100%" height="100%" fill="#fff"/>'
        . '<path d="' . $jalur . '" fill="#000"/></svg>';
}
