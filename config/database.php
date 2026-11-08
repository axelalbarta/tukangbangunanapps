<?php
// Koneksi database bawaan XAMPP (host localhost, user root, password kosong).
// Di Hostinger, nilai ini ditimpa oleh config/produksi.php.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'db_arsip');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
