<?php
// Writes the .env file of a Laravel application from its example, with the address and the database of the site.
//
// php env-file.php <.env.example> <.env> <job file> <language>

[, $example, $target, $jobFile, $language] = $argv;
$job = json_decode((string) file_get_contents($jobFile), true);

$values = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => $job['url'],
    'APP_LOCALE' => $language,
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => $job['db']['host'],
    'DB_PORT' => (string) $job['db']['port'],
    'DB_DATABASE' => $job['db']['name'],
    'DB_USERNAME' => $job['db']['user'],
    'DB_PASSWORD' => $job['db']['password'],
    'QUEUE_CONNECTION' => 'database',
    'CACHE_STORE' => 'file',
    'SESSION_DRIVER' => 'file',
];

$lines = file($example, FILE_IGNORE_NEW_LINES);
foreach ($values as $key => $value) {
    $line = $key . '="' . addcslashes($value, '\\"$') . '"';
    $found = false;
    foreach ($lines as $i => $existing) {
        if (str_starts_with($existing, $key . '=')) {
            $lines[$i] = $line;
            $found = true;
        }
    }
    if (!$found) {
        $lines[] = $line;
    }
}

file_put_contents($target, implode("\n", $lines) . "\n");
chmod($target, 0600);
