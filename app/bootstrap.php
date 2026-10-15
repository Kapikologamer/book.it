<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/accounts.php';
set_exception_handler(function (Throwable $exception): void {
    // Never log submitted values or SQL parameters.
    error_log('book.it failure: ' . get_class($exception) . ' code=' . $exception->getCode());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="pl"><meta charset="utf-8"><title>book.it</title><h1>Chwilowa niedostępność</h1><p>Nie udało się wykonać operacji. Spróbuj ponownie później.</p></html>';
});
session_boot();
