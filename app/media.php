<?php
declare(strict_types=1);

function serve_company_media(): never
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        exit;
    }
    $name = input($_GET, 'name');
    if (!preg_match('/\A[a-f0-9]{32,64}\.(?:png|jpg|jpeg|webp)\z/', $name)) {
        http_response_code(404);
        exit;
    }
    $exists = query(db(), 'SELECT 1 FROM companies WHERE logo_path=? OR banner_path=? LIMIT 1', [$name, $name])->fetchColumn();
    $path = dirname(__DIR__) . '/storage/media/' . $name;
    if (!$exists || !is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . match (pathinfo($name, PATHINFO_EXTENSION)) { 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' });
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $name . '"');
    readfile($path);
    exit;
}
