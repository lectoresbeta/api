<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Image;

use LectoresBeta\Shared\Application\Image\ImageProcessor;
use LectoresBeta\Shared\Application\Image\ProcessedImage;

/**
 * El normalizador, con GD.
 *
 * Tres decisiones que conviene entender:
 *
 * - **se decide por el contenido, nunca por la extensión ni por el
 *   `Content-Type` que declare el cliente** (`file-uploads.md`). Los dos los
 *   escribe quien sube el fichero, así que no son información;
 * - **la salida siempre es WebP**, sea cual sea la entrada. Un solo formato
 *   de salida quita de en medio media docena de casos particulares al
 *   servirlo, y pesa menos que el JPEG equivalente;
 * - `imagecreatefromstring()` **no conserva metadatos**: el objeto que
 *   devuelve son píxeles, y lo que se guarda es el resultado de volver a
 *   codificarlos. Ahí es donde muere el EXIF, y con él la geolocalización.
 *
 * HEIC no se admite: GD no lo decodifica, así que una foto hecha con un
 * iPhone en su formato por defecto se rechaza con un error que lo dice
 * (`F-3`, abierta). Convertirlo exigiría ImageMagick con `libheif`.
 */
final readonly class GdImageProcessor implements ImageProcessor
{
    private const QUALITY = 82;

    /** Lo que GD sabe decodificar y este proyecto acepta. */
    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function normalise(string $bytes, int $maxSide): ?ProcessedImage
    {
        $source = $this->decode($bytes);

        if (null === $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $maxSide / max($width, $height));

        $target = $this->canvas((int) round($width * $scale), (int) round($height * $scale));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        return $this->encode($target);
    }

    public function normaliseSquare(string $bytes, int $side): ?ProcessedImage
    {
        $source = $this->decode($bytes);

        if (null === $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $crop = min($width, $height);

        // El cuadrado más grande que cabe, centrado. Con una imagen ya
        // cuadrada —lo normal, porque el recorte lo hace el navegador— esto
        // no quita ni un píxel.
        $target = $this->canvas(min($side, $crop), min($side, $crop));
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            (int) (($width - $crop) / 2),
            (int) (($height - $crop) / 2),
            imagesx($target),
            imagesy($target),
            $crop,
            $crop,
        );

        return $this->encode($target);
    }

    private function decode(string $bytes): ?\GdImage
    {
        if ('' === $bytes) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if (false === $info || !\in_array($info['mime'], self::SUPPORTED, true)) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        return false === $image ? null : $image;
    }

    /**
     * Con transparencia conservada: un PNG con fondo transparente que se
     * guardara sobre negro se vería mal justo en el sitio donde más se nota,
     * que es un avatar recortado en círculo.
     */
    private function canvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        if (false !== $transparent) {
            imagefill($canvas, 0, 0, $transparent);
        }

        return $canvas;
    }

    private function encode(\GdImage $image): ?ProcessedImage
    {
        ob_start();
        $written = imagewebp($image, null, self::QUALITY);
        $contents = ob_get_clean();

        if (!$written || false === $contents || '' === $contents) {
            return null;
        }

        return new ProcessedImage($contents, 'image/webp', 'webp', imagesx($image), imagesy($image));
    }
}
