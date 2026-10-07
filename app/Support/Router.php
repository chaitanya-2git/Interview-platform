<?php
declare(strict_types=1);
namespace App\Support;
use App\Middleware\Authorize;

final class Router
{
    private array $routes = [];
    public function add(string $method, string $path, array $handler, ?string $role = null): void { $this->routes[$path][$method] = [$handler, $role]; }
    public function dispatch(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
        $base = Config::basePath();
        if ($base !== '' && $path !== $base && !str_starts_with($path, $base . '/')) { $this->error(404); return; }
        $path = '/' . trim(substr($path, strlen($base)), '/');
        $method = $_SERVER['REQUEST_METHOD'];
        if (!isset($this->routes[$path])) { $this->error(404); return; }
        if (!isset($this->routes[$path][$method])) { header('Allow: ' . implode(', ', array_keys($this->routes[$path]))); $this->error(405); return; }
        [$handler, $role] = $this->routes[$path][$method];
        if($method==='POST'){$length=(int)($_SERVER['CONTENT_LENGTH']??0);$limit=Config::iniBytes(ini_get('post_max_size'));if($limit>0&&$length>$limit){$this->error(413);return;}}
        if ($method === 'POST' && !Session::validToken($_POST['_token'] ?? null)) { $this->error(419); return; }
        if ($role !== null && !Authorize::check($role)) { return; }
        (new $handler[0]())->{$handler[1]}();
    }
    private function error(int $status): void
    {
        http_response_code($status);
        $title=$status===419?'This page has expired':($status===413?'Upload too large':'Page unavailable');$message=$status===419?'Refresh the page and try again.':($status===413?'The upload exceeded the server limit. Choose a smaller file and try again.':'The requested page or action is not available.');View::render('error',['title'=>$title,'message'=>$message],'guest');
    }
}
