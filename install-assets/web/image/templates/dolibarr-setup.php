<?php
// Finishes the installation of Dolibarr without its wizard, in the same order as its official Docker image: the
// configuration file, the tables, the first data, the administrator. Called by web-install.
//
// php dolibarr-setup.php <job file> <folder of the site> <data folder> <version>

[, $jobFile, $root, $data, $version] = $argv;
$job = json_decode((string) file_get_contents($jobFile), true);
$sql = "$root/install/mysql";

function stop(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

// A whole file at once: the server cuts it into statements, so a ";" inside a text stays where it is.
function import(mysqli $db, string $path, string $comments, bool $entity = false): void
{
    $text = (string) preg_replace($comments, '', (string) file_get_contents($path));
    if ($entity) {
        $text = str_replace('__ENTITY__', '1', $text);
    }
    if (trim($text) === '') {
        return;
    }
    if ($db->multi_query($text)) {
        do {
            $result = $db->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
    }
    if ($db->errno !== 0) {
        echo basename($path) . ': ' . $db->error . "\n";
    }
}

function constant_of(mysqli $db, string $name, string $value, int $entity): void
{
    $delete = $db->prepare('DELETE FROM llx_const WHERE name = ?');
    $delete->bind_param('s', $name);
    $delete->execute();

    $insert = $db->prepare("INSERT INTO llx_const (name, value, type, visible, note, entity) VALUES (?, ?, 'chaine', 0, '', ?)");
    $insert->bind_param('ssi', $name, $value, $entity);
    $insert->execute();
}

mysqli_report(MYSQLI_REPORT_OFF);
if (!is_dir($sql)) {
    stop('The files of Dolibarr are not complete.');
}

$conf = [
    'dolibarr_main_url_root' => $job['url'],
    'dolibarr_main_document_root' => $root,
    'dolibarr_main_url_root_alt' => '/custom',
    'dolibarr_main_document_root_alt' => "$root/custom",
    'dolibarr_main_data_root' => $data,
    'dolibarr_main_db_host' => $job['db']['host'],
    'dolibarr_main_db_port' => (string) $job['db']['port'],
    'dolibarr_main_db_name' => $job['db']['name'],
    'dolibarr_main_db_prefix' => 'llx_',
    'dolibarr_main_db_user' => $job['db']['user'],
    'dolibarr_main_db_pass' => $job['db']['password'],
    'dolibarr_main_db_type' => 'mysqli',
    'dolibarr_main_db_character_set' => 'utf8mb4',
    'dolibarr_main_db_collation' => 'utf8mb4_unicode_ci',
    'dolibarr_main_authentication' => 'dolibarr',
    'dolibarr_main_instance_unique_id' => bin2hex(random_bytes(16)),
];
$lines = "<?php\n";
foreach ($conf as $name => $value) {
    $lines .= '$' . $name . ' = ' . var_export($value, true) . ";\n";
}
$lines .= "\$dolibarr_main_prod = 1;\n";
is_dir("$root/conf") || mkdir("$root/conf", 0755, true);
is_dir("$root/custom") || mkdir("$root/custom", 0755, true);
is_dir($data) || mkdir($data, 0755, true);
file_put_contents("$root/conf/conf.php", $lines);
chmod("$root/conf/conf.php", 0600);

$db = @new mysqli($job['db']['host'], $job['db']['user'], $job['db']['password'], $job['db']['name'], (int) $job['db']['port']);
if ($db->connect_errno) {
    stop('Dolibarr could not reach its database: ' . $db->connect_error);
}
$db->set_charset('utf8mb4');
$db->query("SET SESSION sql_mode = ''");

$tables = glob("$sql/tables/*.sql") ?: [];
foreach ($tables as $file) {
    if (!str_ends_with($file, '.key.sql')) {
        import($db, $file, '/--.*/');
    }
}
foreach ($tables as $file) {
    if (str_ends_with($file, '.key.sql')) {
        import($db, $file, '/^--.*/m');
    }
}
foreach (glob("$sql/functions/*.sql") ?: [] as $file) {
    import($db, $file, '/^--.*/m');
}
foreach (glob("$sql/data/*.sql") ?: [] as $file) {
    // The chart of accounts of every country: the client imports theirs from Dolibarr.
    if (!str_contains(basename($file), 'llx_accounting_account_')) {
        import($db, $file, '/^--.*/m', true);
    }
}

$table = $db->query("SHOW TABLES LIKE 'llx_user'");
if (!$table instanceof mysqli_result || $table->num_rows === 0) {
    stop('The tables of Dolibarr could not be created.');
}

constant_of($db, 'MAIN_VERSION_LAST_INSTALL', $version, 0);
constant_of($db, 'MAIN_SECURITY_HASH_ALGO', 'password_hash', 0);
constant_of($db, 'MAIN_LANG_DEFAULT', 'auto', 1);
constant_of($db, 'MAIN_INFO_SOCIETE_NOM', (string) $job['title'], 1);
$db->query("DELETE FROM llx_const WHERE name = 'MAIN_NOT_INSTALLED'");

$login = (string) $job['admin_user'];
$hash = password_hash((string) $job['admin_password'], PASSWORD_BCRYPT);
$email = (string) $job['admin_email'];
$admin = $db->prepare("INSERT INTO llx_user (entity, login, pass_crypted, lastname, email, admin, statut) VALUES (0, ?, ?, 'SuperAdmin', ?, 1, 1)");
$admin->bind_param('sss', $login, $hash, $email);
if (!$admin->execute()) {
    stop('The administrator of Dolibarr could not be created: ' . $admin->error);
}
$db->close();

// The module of the users, with Dolibarr's own code, once everything above is in place.
chdir($root);
require "$root/master.inc.php";
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
activateModule('modUser');

file_put_contents("$data/install.lock", '');
echo "Dolibarr $version is installed.\n";
