<?php

/*
 * Opens phpMyAdmin already signed in, for a database picked in the panel.
 *
 * The panel gives the user a link like /phpmyadmin/signon.php?token=... with a random token that
 * works once and only for one minute. This script trades the token for the database credentials by
 * calling the panel (proving who it is with a shared secret), puts them in a session that
 * phpMyAdmin reads, and sends the user to the database. The credentials never appear in a link.
 *
 * Written by the installer: the three values in capitals below are filled in when it is installed.
 */

declare(strict_types=1);

const PANEL_URL = '__PANEL_URL__';
const SIGNON_SECRET = '__SECRET__';
const COOKIE_PATH = '__URL_PATH__/';
const SESSION_NAME = 'PterodactylSignon';

function stop(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $text = htmlspecialchars($message, ENT_QUOTES);
    $panel = htmlspecialchars(PANEL_URL, ENT_QUOTES);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>phpMyAdmin</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
        . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'background:#0b0f1a;color:#c9d1e3;font-family:system-ui,sans-serif">'
        . '<div style="max-width:26rem;padding:2rem;border:1px solid #232b40;border-radius:14px;background:#121829;text-align:center">'
        . '<h1 style="margin:0 0 .75rem;font-size:1.25rem;color:#fff">Cannot open phpMyAdmin</h1>'
        . '<p style="margin:0 0 1.25rem;line-height:1.5">' . $text . '</p>'
        . '<a href="' . $panel . '" style="color:#818cf8">Back to the panel</a></div></body></html>';
    exit;
}

$token = $_GET['token'] ?? '';
if (!is_string($token) || preg_match('/^[A-Za-z0-9]{64}$/', $token) !== 1) {
    stop(400, 'This sign-in link is not valid. Open the database again from the panel.');
}

$curl = curl_init(rtrim(PANEL_URL, '/') . '/api/phpmyadmin/redeem');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['token' => $token]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Signon-Secret: ' . SIGNON_SECRET,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
]);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);

if ($body === false) {
    stop(502, 'The panel could not be reached from this server. Try again in a moment.');
}
if ($status === 404) {
    stop(410, 'This link has expired or was already used. Open the database again from the panel. If this keeps happening, the phpMyAdmin secret may not be the same in the panel and here.');
}
if ($status !== 200) {
    stop(502, 'The panel refused the request. Check that the phpMyAdmin secret is the same in the panel and here.');
}

$data = json_decode((string) $body, true);
if (
    !is_array($data)
    || !is_string($data['user'] ?? null) || !is_string($data['password'] ?? null)
    || !is_string($data['host'] ?? null) || !is_int($data['port'] ?? null)
    || !is_string($data['database'] ?? null)
) {
    stop(502, 'The panel sent an answer that cannot be used.');
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => COOKIE_PATH,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name(SESSION_NAME);
session_start();
session_regenerate_id(true);
$_SESSION = [
    'PMA_single_signon_user' => $data['user'],
    'PMA_single_signon_password' => $data['password'],
    'PMA_single_signon_host' => $data['host'],
    'PMA_single_signon_port' => $data['port'],
];
session_write_close();

header('Cache-Control: no-store');
header('Location: index.php?route=/database/structure&db=' . rawurlencode($data['database']));
