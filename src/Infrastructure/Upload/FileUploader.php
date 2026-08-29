<?php

declare(strict_types=1);

namespace Mirai\Infrastructure\Upload;

use Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_OK;

/**
 * Единый сервис загрузки изображений. Заменяет три расходящиеся реализации старого кода
 * (menu/gallery/vip), каждая со своей — и слабой — политикой.
 *
 * Гарантии:
 *  - тип определяется по СОДЕРЖИМОМУ (finfo), а не по расширению/имени клиента;
 *  - имя файла рандомное (нет перезаписи/предсказуемых путей/коллизий);
 *  - лимит размера; SVG намеренно запрещён (вектор XSS).
 */
final class FileUploader
{
    /** @var array<string,string> MIME => расширение */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly int $maxBytes = 8 * 1024 * 1024,
    ) {}

    /**
     * Валидирует и сохраняет PSR-7 загруженный файл в $targetDir.
     * Возвращает сгенерированное имя файла (без пути).
     */
    public function save(UploadedFileInterface $file, string $targetDir): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new UploadException('Ошибка загрузки файла (код ' . $file->getError() . ').');
        }

        $size = $file->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            throw new UploadException('Файл слишком большой (максимум ' . $this->maxBytes . ' байт).');
        }

        $stream = $file->getStream();
        $uri = $stream->getMetadata('uri');
        $mime = is_string($uri) && is_file($uri)
            ? $this->detectMimeFromPath($uri)
            : $this->detectMimeFromString((string) $stream);

        $ext = $this->extensionFor($mime);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;

        $this->ensureDir($targetDir);
        $file->moveTo(rtrim($targetDir, '/') . '/' . $name);

        return $name;
    }

    /** @return array<string,string> список поддерживаемых MIME => расширение */
    public function allowedTypes(): array
    {
        return self::ALLOWED;
    }

    public function detectMimeFromPath(string $path): string
    {
        // Объектный API finfo: корректен и на PHP 8.2 (рантайм), и на 8.5 (без deprecated finfo_close).
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    public function detectMimeFromString(string $content): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    /** Расширение для допустимого MIME или UploadException, если тип запрещён. */
    public function extensionFor(string $mime): string
    {
        if (!isset(self::ALLOWED[$mime])) {
            throw new UploadException('Недопустимый тип файла: ' . $mime . '. Разрешены: '
                . implode(', ', array_keys(self::ALLOWED)) . '.');
        }

        return self::ALLOWED[$mime];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new UploadException('Не удалось создать каталог загрузки: ' . $dir);
        }
    }
}
