<?php

declare(strict_types=1);

namespace Mirai\Tests\Unit;

use Mirai\Infrastructure\Upload\FileUploader;
use Mirai\Infrastructure\Upload\UploadException;
use PHPUnit\Framework\TestCase;

final class FileUploaderTest extends TestCase
{
    public function testDetectsPngByContentAndMapsExtension(): void
    {
        // 1x1 прозрачный PNG
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $tmp = tempnam(sys_get_temp_dir(), 'mirai_png_');
        file_put_contents($tmp, $png);

        $uploader = new FileUploader();
        $mime = $uploader->detectMimeFromPath($tmp);

        self::assertSame('image/png', $mime);
        self::assertSame('png', $uploader->extensionFor($mime));

        unlink($tmp);
    }

    public function testRejectsNonImageContent(): void
    {
        $uploader = new FileUploader();
        $mime = $uploader->detectMimeFromString("не картинка, а обычный текст\n");

        $this->expectException(UploadException::class);
        $uploader->extensionFor($mime);
    }

    public function testRejectsSvgAsUnsupported(): void
    {
        $uploader = new FileUploader();

        // SVG намеренно запрещён (XSS-вектор) — extensionFor должен бросить.
        $this->expectException(UploadException::class);
        $uploader->extensionFor('image/svg+xml');
    }

    public function testAllowedTypesAreImageOnly(): void
    {
        $types = array_keys((new FileUploader())->allowedTypes());

        self::assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], $types);
    }
}
