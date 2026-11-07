<?php

/**
 * Root gateway for Shared Hosting environments (Hostinger / cPanel)
 * where the repository is cloned directly into public_html.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
);

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
