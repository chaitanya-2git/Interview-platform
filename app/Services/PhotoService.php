<?php
declare(strict_types=1);
namespace App\Services;
use App\Validation\ValidationException;
final class PhotoService
{
    public function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        $fail = static function (): never { throw new ValidationException(['photo' => 'Choose a JPEG or PNG image under 2 MB, at most 4,000 × 4,000 pixels.']); };
        if (!is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) { $fail(); }
        $path = $file['tmp_name'];
        if (filesize($path) > 2 * 1024 * 1024) { $fail(); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { $fail(); }
        $size = @getimagesize($path);
        if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 4000 || $size[1] > 4000 || $size[0] * $size[1] > 12000000) { $fail(); }
        if (!extension_loaded('gd')) { throw new \RuntimeException('GD required'); }
        $source = $mime === 'image/jpeg' ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (!$source) { $fail(); }
        // Re-encode pixels; never retain client filenames, metadata or original upload bytes.
        $edge = min($size[0], $size[1]);
        $target = imagecreatetruecolor(256, 256);
        imagefill($target, 0, 0, imagecolorallocate($target, 245, 247, 250));
        imagecopyresampled($target, $source, 0, 0, (int)(($size[0] - $edge) / 2), (int)(($size[1] - $edge) / 2), 256, 256, $edge, $edge);
        $name = bin2hex(random_bytes(24)) . '.jpg';
        $ok = imagejpeg($target, ROOT . '/storage/uploads/' . $name, 85);
        imagedestroy($source); imagedestroy($target);
        if (!$ok) { throw new \RuntimeException('Photo storage unavailable'); }
        return $name;
    }
    public function remove(?string $name): bool
    {
        if ($name === null) { return true; }
        if (!preg_match('/^[a-f0-9]{48}\.jpg$/', $name)) { return false; }
        $directory = realpath(ROOT . '/storage/uploads');
        if ($directory === false) { return false; }
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) { return true; }
        return @unlink($path);
    }
}
