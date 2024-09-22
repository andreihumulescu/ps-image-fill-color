<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT Free License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/license/mit
 *
 * @author    Andrei H
 * @copyright Since 2024 Andrei H
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ImageManager extends ImageManagerCore
{
    private const SELECTED_COLOR = 'IMAGEFILLCOLOR_SELECTED_COLOR';

    /**
     * Get image fill color from config.
     *
     * @return array
     */
    public static function getImageFillColor()
    {
        return json_decode(Configuration::get(self::SELECTED_COLOR), true) ?? ['r' => 255, 'g' => 255, 'b' => 255];
    }

    /**
     * Create an empty image with white background.
     *
     * @param int $width
     * @param int $height
     *
     * @phpstan-ignore-next-line
     *
     * @return resource|GdImage
     */
    public static function createWhiteImage($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);
        $imageFillColor = self::getImageFillColor();
        $color = imagecolorallocate($image, $imageFillColor['r'], $imageFillColor['g'], $imageFillColor['b']);
        imagefill($image, 0, 0, $color);
        return $image;
    }

    /**
     * Cut image.
     *
     * @param string $srcFile Origin filename
     * @param string $dstFile Destination filename
     * @param int $dstWidth Desired width
     * @param int $dstHeight Desired height
     * @param string $fileType
     * @param int $dstX
     * @param int $dstY
     *
     * @return bool Operation result
     */
    public static function cut($srcFile, $dstFile, $dstWidth = null, $dstHeight = null, $fileType = 'jpg', $dstX = 0, $dstY = 0)
    {
        if (!file_exists($srcFile)) {
            return false;
        }

        // Source information
        $srcInfo = getimagesize($srcFile);
        $src = [
            'width' => $srcInfo[0],
            'height' => $srcInfo[1],
            'ressource' => ImageManager::create($srcInfo[2], $srcFile),
        ];

        // Destination information
        $dest = [];
        $dest['x'] = $dstX;
        $dest['y'] = $dstY;
        $dest['width'] = null !== $dstWidth ? $dstWidth : $src['width'];
        $dest['height'] = null !== $dstHeight ? $dstHeight : $src['height'];
        $dest['ressource'] = ImageManager::createWhiteImage($dest['width'], $dest['height']);

        $imageFillColor = self::getImageFillColor();
        $color = imagecolorallocate($dest['ressource'], $imageFillColor['r'], $imageFillColor['g'], $imageFillColor['b']);

        // @phpstan-ignore-next-line
        imagecopyresampled($dest['ressource'], $src['ressource'], 0, 0, $dest['x'], $dest['y'], $dest['width'], $dest['height'], $dest['width'], $dest['height']);
        imagecolortransparent($dest['ressource'], $color);
        $return = ImageManager::write($fileType, $dest['ressource'], $dstFile);
        Hook::exec('actionOnImageCutAfter', ['dst_file' => $dstFile, 'file_type' => $fileType]);
        // @phpstan-ignore-next-line
        @imagedestroy($src['ressource']);

        return $return;
    }

    /**
     * Resize, cut and optimize image.
     *
     * @param string $sourceFile Image object from $_FILE
     * @param string $destinationFile Destination filename
     * @param int $destinationWidth Desired width (optional), pass null to use original dimensions
     * @param int $destinationHeight Desired height (optional), pass null to use original dimensions
     * @param string $destinationFileType Desired file type inside the image. If jpg and $forceType is false, format inside will be decided by PS_IMAGE_QUALITY
     * @param bool $forceType If false and $destinationFileType is jpg, format inside will be decided by PS_IMAGE_QUALITY
     * @param int $error Out error code
     * @param int $targetWidth Needed by AdminImportController to speed up the import process
     * @param int $targetHeight Needed by AdminImportController to speed up the import process
     * @param int $quality Needed by AdminImportController to speed up the import process
     * @param int $sourceWidth Needed by AdminImportController to speed up the import process
     * @param int $sourceHeight Needed by AdminImportController to speed up the import process
     *
     * @return bool Operation result
     */
    public static function resize(
        $sourceFile,
        $destinationFile,
        $destinationWidth = null,
        $destinationHeight = null,
        $destinationFileType = 'jpg',
        $forceType = false,
        &$error = 0,
        &$targetWidth = null,
        &$targetHeight = null,
        $quality = 5,
        &$sourceWidth = null,
        &$sourceHeight = null
    ) {
        clearstatcache(true, $sourceFile);

        // Check if original file exists
        if (!file_exists($sourceFile) || !filesize($sourceFile)) {
            $error = self::ERROR_FILE_NOT_EXIST;

            return false;
        }

        list($tmpWidth, $tmpHeight, $sourceFileType) = getimagesize($sourceFile);
        $rotate = 0;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourceFile);

            if ($exif && isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $sourceWidth = $tmpWidth;
                        $sourceHeight = $tmpHeight;
                        $rotate = 180;

                        break;

                    case 6:
                        $sourceWidth = $tmpHeight;
                        $sourceHeight = $tmpWidth;
                        $rotate = -90;

                        break;

                    case 8:
                        $sourceWidth = $tmpHeight;
                        $sourceHeight = $tmpWidth;
                        $rotate = 90;

                        break;

                    default:
                        $sourceWidth = $tmpWidth;
                        $sourceHeight = $tmpHeight;
                }
            } else {
                $sourceWidth = $tmpWidth;
                $sourceHeight = $tmpHeight;
            }
        } else {
            $sourceWidth = $tmpWidth;
            $sourceHeight = $tmpHeight;
        }

        /*
         * If the filetype is not forced and we are requesting a JPG file, we will adjust the format inside
         * the image according to PS_IMAGE_QUALITY in some cases.
         */
        if (!$forceType && $destinationFileType === 'jpg') {
            // If PS_IMAGE_QUALITY is set to png_all, we will use PNG file no matter the source.
            if (Configuration::get('PS_IMAGE_QUALITY') == 'png_all') {
                $destinationFileType = 'png';
            }

            // If PS_IMAGE_QUALITY is set to png (optional), we will use PNG if the original format could support transparency.
            if (Configuration::get('PS_IMAGE_QUALITY') == 'png' && $sourceFileType != IMAGETYPE_JPEG) {
                $destinationFileType = 'png';
            }
        }

        if (!$sourceWidth) {
            $error = self::ERROR_FILE_WIDTH;

            return false;
        }
        if (!$destinationWidth) {
            $destinationWidth = $sourceWidth;
        }
        if (!$destinationHeight) {
            $destinationHeight = $sourceHeight;
        }

        $widthDiff = $destinationWidth / $sourceWidth;
        $heightDiff = $destinationHeight / $sourceHeight;

        $psImageGenerationMethod = Configuration::get('PS_IMAGE_GENERATION_METHOD');
        if ($widthDiff > 1 && $heightDiff > 1) {
            $nextWidth = $sourceWidth;
            $nextHeight = $sourceHeight;
        } else {
            if ($psImageGenerationMethod == 2 || (!$psImageGenerationMethod && $widthDiff > $heightDiff)) {
                $nextHeight = $destinationHeight;
                $nextWidth = round(($sourceWidth * $nextHeight) / $sourceHeight);
                $destinationWidth = (int) (!$psImageGenerationMethod ? $destinationWidth : $nextWidth);
            } else {
                $nextWidth = $destinationWidth;
                $nextHeight = round($sourceHeight * $destinationWidth / $sourceWidth);
                $destinationHeight = (int) (!$psImageGenerationMethod ? $destinationHeight : $nextHeight);
            }
        }

        if (!ImageManager::checkImageMemoryLimit($sourceFile)) {
            $error = self::ERROR_MEMORY_LIMIT;

            return false;
        }

        $targetWidth = $destinationWidth;
        $targetHeight = $destinationHeight;

        $destImage = imagecreatetruecolor($destinationWidth, $destinationHeight);

        // If the output is PNG, fill with transparency. Else fill with white background.
        if (in_array($destinationFileType, ['png', 'webp', 'avif'])) {
            // if png color type is 3, the file is paletted (256 colors or less). Change palette to reduce file size
            if ($destinationFileType == 'png' && $sourceFileType == IMAGETYPE_PNG && self::getPNGColorType($sourceFile) == 3) {
                imagetruecolortopalette($destImage, false, 255);
            } else {
                imagealphablending($destImage, false);
            }
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $destinationWidth, $destinationHeight, $transparent);
        } else {
            $imageFillColor = self::getImageFillColor();
            $color = imagecolorallocate($destImage, $imageFillColor['r'], $imageFillColor['g'], $imageFillColor['b']);
            imagefilledrectangle($destImage, 0, 0, $destinationWidth, $destinationHeight, $color);
        }

        $srcImage = ImageManager::create($sourceFileType, $sourceFile);
        if ($rotate) {
            /** @phpstan-ignore-next-line */
            $srcImage = imagerotate($srcImage, $rotate, 0);
        }

        if ($destinationWidth >= $sourceWidth && $destinationHeight >= $sourceHeight) {
            imagecopyresized($destImage, $srcImage, (int) (($destinationWidth - $nextWidth) / 2), (int) (($destinationHeight - $nextHeight) / 2), 0, 0, $nextWidth, $nextHeight, $sourceWidth, $sourceHeight);
        } else {
            ImageManager::imagecopyresampled($destImage, $srcImage, (int) (($destinationWidth - $nextWidth) / 2), (int) (($destinationHeight - $nextHeight) / 2), 0, 0, $nextWidth, $nextHeight, $sourceWidth, $sourceHeight, $quality);
        }
        $writeFile = ImageManager::write($destinationFileType, $destImage, $destinationFile);
        Hook::exec('actionOnImageResizeAfter', ['dst_file' => $destinationFile, 'file_type' => $destinationFileType]);
        @imagedestroy($srcImage);

        return $writeFile;
    }
}
