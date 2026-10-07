<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Validation\Input;
use App\Support\{View, Session};
$count = 0;
function check(bool $condition, string $description): void { global $count; if (!$condition) { throw new RuntimeException('FAIL: ' . $description); } $count++; }
check(Input::phone('98765 43210') === '+919876543210', 'Indian phone normalized');
check(Input::phone('+91 (98765) 43210') === '+919876543210', 'Formatted phone normalized');
check(Input::phone('919876543210') === '+919876543210', 'Country prefix normalized');
check(Input::phone('+44 7700 900123') === '+447700900123', 'International phone normalized');
check(Input::phone('not-a-phone') === null, 'Invalid phone rejected');
check(!Input::password('short'), 'Short password rejected');
check(Input::password('Twelve plus chars'), 'Long password accepted');
check(!Input::password(str_repeat('a', 73)), 'Bcrypt truncation prevented');
check(!Input::password("longpassword\0test"), 'NUL password rejected');
check(View::escape('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;', 'HTML escaped');
check(View::initials('Ada Lovelace') === 'AL', 'Initials');
check(View::initials('Chaitanya') === 'C', 'Single name initials');
check(Input::text(['x' => ['malicious']], 'x') === '', 'Array input handled');
$_SESSION = [];
$token = Session::token();
check(strlen($token) === 64, 'CSRF entropy');
check(Session::validToken($token), 'Valid CSRF');
check(!Session::validToken('bad'), 'Invalid CSRF rejected');
check(!Session::validToken(['bad']), 'Array CSRF rejected');
echo "$count unit checks passed.\n";
