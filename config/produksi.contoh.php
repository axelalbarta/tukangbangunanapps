<?php
// CONTOH pengaturan server produksi. Salin menjadi config/produksi.php di server lalu isi.
// File produksi.php TIDAK ikut di ZIP aplikasi supaya tidak tertimpa saat pembaruan.
define('APP_DEBUG', false);
define('DB_HOST', 'localhost');
define('DB_NAME', 'u000000000_arsip');
define('DB_USER', 'u000000000_arsip');
define('DB_PASS', 'ganti-dengan-password-database');
define('SETUP_KEY', 'ganti-dengan-kunci-acak-panjang');
