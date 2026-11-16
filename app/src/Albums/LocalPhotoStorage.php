<?php
declare(strict_types=1);
namespace Danny\Albums;
final class LocalPhotoStorage implements PhotoStorage {
    public function __construct(private string $root) {}
    public function path(string $key): string {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $key)) { throw new \InvalidArgumentException('Arquivo inválido.'); }
        return $this->root . '/' . substr($key, 0, 2) . '/' . $key;
    }
    public function delete(string $key): void {
        $path = $this->path($key);
        if (is_file($path) && !unlink($path)) { throw new \RuntimeException('Não foi possível remover o arquivo.'); }
    }
    public function storeUpload(array $upload): array {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($upload['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('Upload incompleto. Selecione o arquivo novamente.');
        }
        $tmp = $upload['tmp_name'];
        $bytes = filesize($tmp);
        $maxBytes = (int)env('ALBUM_PHOTO_MAX_MB', 25) * 1024 * 1024;
        if ($bytes === false || $bytes < 1 || $bytes > $maxBytes) { throw new \InvalidArgumentException('A foto excede o limite por arquivo.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $size = @getimagesize($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$size || $size[0] * $size[1] > 24000000) {
            throw new \InvalidArgumentException('Envie JPEG, PNG ou WebP válido, com até 24 megapixels.');
        }
        $key = bin2hex(random_bytes(32));
        $path = $this->path($key);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new \RuntimeException('Armazenamento indisponível.'); }
        if (!move_uploaded_file($tmp, $path)) { throw new \RuntimeException('Falha ao salvar a foto.'); }
        chmod($path, 0600);
        $thumbnailKey = null;
        try {
            if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                $image = @imagecreatefromstring((string)file_get_contents($path));
                if ($image !== false) {
                    $scale = min(1, 640 / max($size[0], $size[1]));
                    $thumb = imagescale($image, max(1, (int)($size[0] * $scale)), max(1, (int)($size[1] * $scale)));
                    if ($thumb !== false) {
                        $thumbnailKey = bin2hex(random_bytes(32));
                        $thumbPath = $this->path($thumbnailKey);
                        if (!is_dir(dirname($thumbPath))) { mkdir(dirname($thumbPath), 0700, true); }
                        if (!imagewebp($thumb, $thumbPath, 78)) { throw new \RuntimeException('Falha ao gerar miniatura.'); }
                        chmod($thumbPath, 0600);
                        imagedestroy($thumb);
                    }
                    imagedestroy($image);
                }
            }
            $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]+/u', '_', (string)($upload['name'] ?? 'foto'));
            if (!$name) { $name = 'foto'; }
            return ['storage_key'=>$key, 'thumbnail_key'=>$thumbnailKey, 'original_name'=>mb_substr($name, 0, 180),
                'mime'=>$mime, 'bytes'=>$bytes, 'width'=>$size[0], 'height'=>$size[1]];
        } catch (\Throwable $error) {
            $this->delete($key);
            if ($thumbnailKey !== null) { $this->delete($thumbnailKey); }
            throw $error;
        }
    }
}
