<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\{Config, Database};
use App\Services\AuditService;
use App\Validation\Input;

$command = $argv[1] ?? 'help';
if ($command === 'key:generate') { echo bin2hex(random_bytes(32)) . PHP_EOL; exit; }
if ($command === 'check') {
    $required = ['pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'session'];
    $fail = version_compare(PHP_VERSION, '8.2', '<');
    echo 'PHP ' . PHP_VERSION . ': ' . (!$fail ? 'OK' : '8.2+ REQUIRED') . PHP_EOL;
    foreach ($required as $extension) { $ok = extension_loaded($extension); $fail = $fail || !$ok; echo $extension . ': ' . ($ok ? 'OK' : 'MISSING') . PHP_EOL; }
    foreach (['storage/logs', 'storage/sessions', 'storage/uploads', 'storage/resumes', 'storage/cache'] as $directory) { $ok = is_dir(ROOT . '/' . $directory) && is_writable(ROOT . '/' . $directory); $fail = $fail || !$ok; echo $directory . ': ' . ($ok ? 'writable' : 'NOT WRITABLE') . PHP_EOL; }
    try { Config::key(); echo "Application key: OK\n"; } catch (Throwable) { $fail=true; echo "Application key: INVALID\n"; }
    $environment=Config::get('APP_ENV','production');$url=Config::get('APP_URL');$urlParts=parse_url($url);$production=$environment==='production';$urlOk=is_array($urlParts)&&isset($urlParts['scheme'],$urlParts['host']);
    if($production){$urlOk=$urlOk&&$urlParts['scheme']==='https'&&rtrim((string)($urlParts['path']??''),'/')==='/interviews';}
    $fail=$fail||!$urlOk;echo 'Environment and base URL: '.($urlOk?'OK':($production?'PRODUCTION URL MUST BE HTTPS AT /interviews':'INVALID APP_URL')).PHP_EOL;
    $debugOff=ini_get('display_errors')==='0';$fail=$fail||($production&&!$debugOff);echo 'Displayed errors: '.($debugOff?'disabled':'ENABLED').PHP_EOL;
    try {
        $db=Database::connection();$db->query('SELECT 1');echo "Database connection: OK\n";
        $timezone=$db->query("SELECT setting_value FROM app_settings WHERE setting_key='business_timezone'")->fetchColumn();$timezoneOk=$timezone==='Asia/Kolkata';$fail=$fail||!$timezoneOk;echo 'Business timezone: '.($timezoneOk?'Asia/Kolkata · OK':'INVALID').PHP_EOL;
        $applied=$db->query("SELECT name,checksum FROM schema_migrations WHERE name LIKE 'migrations/%'")->fetchAll(PDO::FETCH_KEY_PAIR);$migrationOk=true;$files=glob(ROOT.'/database/migrations/*.sql');sort($files);foreach($files as $file){$name='migrations/'.basename($file);if(!isset($applied[$name])||!hash_equals(hash_file('sha256',$file),$applied[$name])){$migrationOk=false;}}$fail=$fail||!$migrationOk;echo 'Migration state: '.($migrationOk?'current':'MISSING OR MODIFIED').PHP_EOL;
    } catch(Throwable){$fail=true;echo "Database/configuration checks: FAILED\n";}
    exit($fail ? 1 : 0);
}
/**
 * MariaDB 12+ names unnamed foreign keys `1`, `2`, ... instead of `<table>_ibfk_N`.
 * Older migrations drop keys by the legacy name, so rename numeric keys to it first.
 * Databases created on MySQL or older MariaDB have no numeric names and are untouched.
 */
function normalizeLegacyForeignKeyNames(PDO $db): void
{
    $keys = $db->query(
        "SELECT rc.TABLE_NAME, rc.CONSTRAINT_NAME, rc.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE,
                GROUP_CONCAT(CONCAT('`', k.COLUMN_NAME, '`') ORDER BY k.ORDINAL_POSITION) AS cols,
                GROUP_CONCAT(CONCAT('`', k.REFERENCED_COLUMN_NAME, '`') ORDER BY k.ORDINAL_POSITION) AS ref_cols
         FROM information_schema.REFERENTIAL_CONSTRAINTS rc
         JOIN information_schema.KEY_COLUMN_USAGE k
           ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND k.TABLE_NAME = rc.TABLE_NAME AND k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
         WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.CONSTRAINT_NAME REGEXP '^[0-9]+$'
         GROUP BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, rc.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE"
    )->fetchAll(PDO::FETCH_ASSOC);
    $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
    foreach ($keys as $key) {
        $legacy = $key['TABLE_NAME'] . '_ibfk_' . $key['CONSTRAINT_NAME'];
        $exists->execute([$key['TABLE_NAME'], $legacy]);
        if ((int) $exists->fetchColumn() > 0) { continue; }
        $db->exec(sprintf(
            'ALTER TABLE `%s` DROP FOREIGN KEY `%s`, ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) ON UPDATE %s ON DELETE %s',
            $key['TABLE_NAME'], $key['CONSTRAINT_NAME'], $legacy, $key['cols'], $key['REFERENCED_TABLE_NAME'], $key['ref_cols'], $key['UPDATE_RULE'], $key['DELETE_RULE']
        ));
    }
}

if (in_array($command, ['migrate', 'seed'], true)) {
    $db = Database::connection();
    $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
    $lockName = 'blujay-setup-' . substr(hash('sha256', Config::get('DB_DATABASE')), 0, 32);
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) { throw new RuntimeException('Another setup is in progress'); }
    try {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $kind = $command === 'migrate' ? 'migrations' : 'seeds';
        $files = glob(ROOT . '/database/' . $kind . '/*.sql'); sort($files);
        foreach ($files as $file) {
            $name = $kind . '/' . basename($file); $checksum = hash_file('sha256', $file);
            $query = $db->prepare('SELECT checksum FROM schema_migrations WHERE name = ?'); $query->execute([$name]); $existing = $query->fetchColumn();
            if ($existing) {
                if (!hash_equals($existing, $checksum)) { throw new RuntimeException('Applied migration modified'); }
                echo 'Already applied: ' . $name . PHP_EOL; continue;
            }
            if ($command === 'migrate') { normalizeLegacyForeignKeyNames($db); }
            if ($command === 'seed') { $db->beginTransaction(); }
            try {
                $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
                foreach (explode(';', $sql) as $statement) { if (trim($statement) !== '') { $db->exec($statement); } }
                $query = $db->prepare('INSERT INTO schema_migrations (name, checksum) VALUES (?, ?)'); $query->execute([$name, $checksum]);
                if ($db->inTransaction()) { $db->commit(); }
            } catch (Throwable $error) { if ($db->inTransaction()) { $db->rollBack(); } throw $error; }
            echo 'Applied: ' . $name . PHP_EOL;
        }
    } finally { $query = $db->prepare('SELECT RELEASE_LOCK(?)'); $query->execute([$lockName]); }
    exit;
}
if ($command === 'admin:create') {
    $options = [];
    foreach (array_slice($argv, 2) as $argument) { if (str_starts_with($argument, '--') && str_contains($argument, '=')) { [$key, $value] = explode('=', substr($argument, 2), 2); $options[$key] = $value; } }
    $name = trim($options['name'] ?? '');
    $username = strtolower(trim($options['username'] ?? ''));
    $password = getenv('BLUJAY_ADMIN_PASSWORD') ?: '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !preg_match('/^[a-z][a-z0-9._-]{2,79}$/', $username) || !Input::password($password)) {
        fwrite(STDERR, "Usage: set BLUJAY_ADMIN_PASSWORD in the process environment, then php bin/console.php admin:create --name=\"Full Name\" --username=admin.name\nPassword: 12+ characters, maximum 72 bytes. Username: 3–80 characters, beginning with a letter.\n"); exit(1);
    }
    $db = Database::connection(); $db->beginTransaction();
    try {
        $query = $db->prepare("INSERT INTO users (role, full_name, username, password_hash) VALUES ('ADMIN', ?, ?, ?)");
        $query->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) $db->lastInsertId(); (new AuditService())->record($id, 'admin.provisioned', 'user', $id);
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) { $db->rollBack(); } throw $error; }
    putenv('BLUJAY_ADMIN_PASSWORD');
    echo "Administrator created. No default password is installed.\n"; exit;
}
if ($command === 'maintenance:prune') {
    $count = Database::connection()->exec('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP()');
    echo 'Expired rate-limit buckets removed: ' . $count . PHP_EOL; exit;
}
echo "BluJay Interview Operations\n\nCommands: key:generate, check, migrate, seed, admin:create, maintenance:prune\n";
