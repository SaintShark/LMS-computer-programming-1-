<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Prevent caching
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Clear all session variables
    $_SESSION = [];

    // Delete the session cookie if it exists
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();

    // Notify client (BFCache handling) and redirect
    echo '<script>
        try {
            sessionStorage.setItem("loggedOut", "1");
            sessionStorage.removeItem("loggedIn");
        } catch (e) {}
        window.location.replace("login.php");
    </script>';

    exit;
?>