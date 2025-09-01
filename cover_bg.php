<?php
// cover_bg.php — rotates background images from /cover/<group> and returns ABSOLUTE URLs

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getNextCoverImage(string $group): ?string {
    $map = ['login'=>'login','cover1'=>'cover1','owner'=>'owner','cart'=>'cart'];
    if (!isset($map[$group])) return null;

    // ---------- File-system path (works no matter where this file is included from)
    $folderFs = __DIR__ . '/cover/' . $map[$group];

    // ---------- Build absolute URL prefix based on DOCUMENT_ROOT
    // Example on localhost:
    //   DOCUMENT_ROOT = C:/xampp/htdocs
    //   __DIR__       = C:/xampp/htdocs/Merkaza-Almost-Done/public_html
    //   $baseUrl      = /Merkaza-Almost-Done/public_html
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $hereFs  = str_replace('\\', '/', __DIR__);
    $baseUrl = substr($hereFs, strlen($docRoot));   // starts with "/" or "" if same
    if ($baseUrl === false) $baseUrl = '';          // safety
    $folderUrl = rtrim($baseUrl, '/') . '/cover/' . $map[$group];  // absolute URL

    // Gather images
    $files = [];
    foreach (['*.jpg','*.jpeg','*.png','*.webp','*.gif'] as $pattern) {
        $files = array_merge($files, glob($folderFs . '/' . $pattern, GLOB_NOSORT));
    }
    if (!$files) return null;

    natsort($files);
    $files = array_values($files);

    if (!isset($_SESSION['cover_index']) || !is_array($_SESSION['cover_index'])) {
        $_SESSION['cover_index'] = [];
    }
    $i = $_SESSION['cover_index'][$group] ?? 0;
    $count = count($files);
    if ($i >= $count) $i = 0;

    $currentUrl = $folderUrl . '/' . basename($files[$i]);
    $_SESSION['cover_index'][$group] = ($i + 1) % $count; // advance

    return $currentUrl;
}

function peekNextCoverImage(string $group): ?string {
    $map = ['login'=>'login','cover1'=>'cover1','owner'=>'owner','cart'=>'cart'];
    if (!isset($map[$group])) return null;

    $folderFs = __DIR__ . '/cover/' . $map[$group];

    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $hereFs  = str_replace('\\', '/', __DIR__);
    $baseUrl = substr($hereFs, strlen($docRoot));
    if ($baseUrl === false) $baseUrl = '';
    $folderUrl = rtrim($baseUrl, '/') . '/cover/' . $map[$group];

    $files = [];
    foreach (['*.jpg','*.jpeg','*.png','*.webp','*.gif'] as $pattern) {
        $files = array_merge($files, glob($folderFs . '/' . $pattern, GLOB_NOSORT));
    }
    if (!$files) return null;

    natsort($files);
    $files = array_values($files);
    $i = $_SESSION['cover_index'][$group] ?? 0;

    return $folderUrl . '/' . basename($files[$i % count($files)]);
}
