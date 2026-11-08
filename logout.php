<?php
require __DIR__ . '/includes/init.php';

if (is_post()) {
    verify_csrf();
    if (current_user()) {
        log_aktivitas('logout', 'user', (int)current_user()['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
redirect('login');
