<?php
// ============================================================
//  Ame Co. Workspace — logout.php
//  Destroys the session and redirects to the login page.
//  Link to this page from any "Log out" button/link.
// ============================================================
session_start();
session_unset();
session_destroy();

// Clear the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: login.php');
exit;
