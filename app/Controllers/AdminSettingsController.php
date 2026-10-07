<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\{AdminOperationsService,AuditService,AuthService};use App\Support\{Database,Session,View};use App\Validation\ValidationException;
final class AdminSettingsController
{
 public function index():void{$this->page();}
 public function save():void{$u=(new AuthService())->user();try{(new AdminOperationsService(Database::connection(),new AuditService(Database::connection())))->saveSettings((int)$u['id'],$_POST);Session::flash('Scheduling settings saved.');View::redirect('admin/settings');}catch(ValidationException $e){http_response_code(422);$this->page($e->errors);}}
 private function page(array $errors=[]):void{$db=Database::connection();View::render('admin/settings',['title'=>'Scheduling settings','user'=>(new AuthService())->user(),'errors'=>$errors,'settings'=>$db->query("SELECT setting_key,setting_value FROM app_settings")->fetchAll(\PDO::FETCH_KEY_PAIR),'hours'=>$db->query('SELECT * FROM business_hours ORDER BY weekday')->fetchAll()]);}
}
