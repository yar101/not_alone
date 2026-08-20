<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AvatarService
{
    /**
     * Process, resize, optimize and save user avatar.
     */
    public function updateAvatar(User $user, UploadedFile $file): string
    {
        $disk = config('filesystems.default');

        if ($user->avatar_path) {
            Storage::disk($disk)->delete($user->avatar_path);
        }

        $realPath = $file->getRealPath();
        $imageInfo = @getimagesize($realPath);
        if (! $imageInfo) {
            throw new \InvalidArgumentException('Не удалось определить формат изображения аватара.');
        }

        [$origWidth, $origHeight] = $imageInfo;
        if ($origWidth > 6000 || $origHeight > 6000) {
            throw new \InvalidArgumentException('Разрешение изображения слишком велико (максимум 6000x6000 px).');
        }

        $img = @imagecreatefromstring(file_get_contents($realPath));

        if (! $img) {
            throw new \InvalidArgumentException('Не удалось обработать изображение аватара.');
        }

        // Fix orientation from EXIF
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            if (! empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $img = imagerotate($img, 180, 0);
                        break;
                    case 6:
                        $img = imagerotate($img, -90, 0);
                        break;
                    case 8:
                        $img = imagerotate($img, 90, 0);
                        break;
                }
            }
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $targetSize = 600;

        $newImg = imagecreatetruecolor($targetSize, $targetSize);

        $white = imagecolorallocate($newImg, 255, 255, 255);
        imagefill($newImg, 0, 0, $white);

        if ($width > $height) {
            $srcX = (int) (($width - $height) / 2);
            $srcY = 0;
            $srcW = $height;
            $srcH = $height;
        } else {
            $srcX = 0;
            $srcY = (int) (($height - $width) / 2);
            $srcW = $width;
            $srcH = $width;
        }

        imagecopyresampled($newImg, $img, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $srcW, $srcH);

        $path = "avatars/{$user->id}_".time().'.jpg';

        ob_start();
        imagejpeg($newImg, null, 80);
        $imageData = ob_get_clean();

        Storage::disk($disk)->put($path, $imageData);

        imagedestroy($img);
        imagedestroy($newImg);

        $user->update(['avatar_path' => $path]);

        return $path;
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }
    }
}
