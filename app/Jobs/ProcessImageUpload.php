<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tempPath,
        public string $destinationPath,
        public ?string $modelClass = null,
        public ?int $modelId = null,
        public ?string $attributeName = null,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($this->tempPath)) {
            Log::warning("ProcessImageUpload: Temporary file not found at {$this->tempPath}");

            return;
        }

        $fileContents = $disk->get($this->tempPath);
        if (! $fileContents) {
            Log::warning("ProcessImageUpload: File content empty at {$this->tempPath}");

            return;
        }

        $img = @imagecreatefromstring($fileContents);

        if ($img) {
            // Fix orientation from EXIF using temp local stream
            $tmpLocal = tempnam(sys_get_temp_dir(), 'img_proc_');
            file_put_contents($tmpLocal, $fileContents);
            $exif = function_exists('exif_read_data') ? @exif_read_data($tmpLocal) : false;
            @unlink($tmpLocal);
            if (! empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0);
                        break;
                    case 6: $img = imagerotate($img, -90, 0);
                        break;
                    case 8: $img = imagerotate($img, 90, 0);
                        break;
                }
            }

            $width = imagesx($img);
            $height = imagesy($img);
            $maxDim = 1600;

            if ($width > $maxDim || $height > $maxDim) {
                $ratio = $width / $height;
                if ($ratio > 1) {
                    $newWidth = $maxDim;
                    $newHeight = (int) ($maxDim / $ratio);
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int) ($maxDim * $ratio);
                }
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }

            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($newImg, 255, 255, 255);
            imagefill($newImg, 0, 0, $white);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            imagejpeg($newImg, null, 70);
            $imageData = ob_get_clean();

            $disk->put($this->destinationPath, $imageData);
            imagedestroy($img);
            imagedestroy($newImg);

            // Delete temp file
            $disk->delete($this->tempPath);

            // Update model if requested
            if ($this->modelClass && $this->modelId && $this->attributeName) {
                $model = $this->modelClass::find($this->modelId);
                if ($model) {
                    $model->update([$this->attributeName => $this->destinationPath]);

                    if ($this->modelClass === \App\Models\ContentPackPhoto::class) {
                        $pack = $model->contentPack;
                        if ($pack && $pack->cover_path === $this->tempPath) {
                            $pack->update(['cover_path' => $this->destinationPath]);
                        }
                    }
                }
            }
        } else {
            Log::warning('ProcessImageUpload: Failed to process image with GD. Moving original file directly.');
            // If GD fails, just move the file to destination without resizing
            $disk->move($this->tempPath, $this->destinationPath);

            if ($this->modelClass && $this->modelId && $this->attributeName) {
                $model = $this->modelClass::find($this->modelId);
                if ($model) {
                    $model->update([$this->attributeName => $this->destinationPath]);

                    if ($this->modelClass === \App\Models\ContentPackPhoto::class) {
                        $pack = $model->contentPack;
                        if ($pack && $pack->cover_path === $this->tempPath) {
                            $pack->update(['cover_path' => $this->destinationPath]);
                        }
                    }
                }
            }
        }
    }
}
