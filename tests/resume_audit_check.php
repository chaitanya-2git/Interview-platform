<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Support\{Config, Database};

if (Config::get('APP_ENV') !== 'testing' || !str_contains(strtolower(Config::get('DB_DATABASE')), 'test')) {
    throw new RuntimeException('Resume audit checks require a disposable testing database.');
}

$db = Database::connection();
$checks = 0;
foreach (['candidate.resume_uploaded', 'candidate.resume_replaced', 'admin.resume_viewed', 'admin.resume_downloaded'] as $action) {
    $query = $db->prepare('SELECT COUNT(*) FROM audit_logs WHERE action = ?');
    $query->execute([$action]);
    if ((int) $query->fetchColumn() < 1) { throw new RuntimeException('Missing audit action: ' . $action); }
    $checks++;
}

$rows = $db->query("SELECT metadata_json FROM audit_logs WHERE action LIKE '%.resume_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $json) {
    $metadata = $json ? json_decode($json, true, 16, JSON_THROW_ON_ERROR) : [];
    $encoded = strtolower(json_encode($metadata, JSON_THROW_ON_ERROR));
    if (str_contains($encoded, 'stored_filename') || str_contains($encoded, 'storage/resumes') || str_contains($encoded, 'resume contents')) {
        throw new RuntimeException('Resume audit metadata contains private storage or content data.');
    }
}
$checks++;

echo $checks . " Resume audit checks passed.\n";
