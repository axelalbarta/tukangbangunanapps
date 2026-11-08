<?php
require __DIR__ . '/includes/init.php';
redirect(current_user() ? 'dashboard' : 'login');
