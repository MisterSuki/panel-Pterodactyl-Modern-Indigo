<?php

/*
 * phpMyAdmin configuration written by the Pterodactyl installer.
 *
 * Nobody types a password here: the only way in is the link the panel gives, which goes through
 * signon.php. Opening phpMyAdmin directly sends the visitor back to the panel.
 */

declare(strict_types=1);

$cfg['blowfish_secret'] = '__BLOWFISH__';
$cfg['TempDir'] = '__TMP_DIR__';

$i = 1;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'PterodactylSignon';
$cfg['Servers'][$i]['SignonCookieParams'] = [
    'lifetime' => 0,
    'path' => '__URL_PATH__/',
    'httponly' => true,
    'samesite' => 'Lax',
];
$cfg['Servers'][$i]['SignonURL'] = '__PANEL_URL__';
$cfg['Servers'][$i]['LogoutURL'] = '__PANEL_URL__';
// The real host and port come from the panel, for each database.
$cfg['Servers'][$i]['host'] = 'localhost';

$cfg['AllowArbitraryServer'] = false;
$cfg['VersionCheck'] = false;
$cfg['SendErrorReports'] = 'never';
$cfg['ShowServerInfo'] = false;
$cfg['DefaultLang'] = 'en';
