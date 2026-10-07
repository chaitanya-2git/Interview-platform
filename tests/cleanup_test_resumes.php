<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(App\Support\Config::get('APP_ENV')!=='testing'||!str_contains(strtolower(App\Support\Config::get('DB_DATABASE')),'test'))throw new RuntimeException('Test database required');
$db=App\Support\Database::connection();$service=new App\Services\ResumeService($db);$count=0;
foreach($db->query('SELECT stored_filename FROM candidate_resumes') as $row)if($service->remove((string)$row['stored_filename']))$count++;
echo "Cleaned $count test resume files.\n";
