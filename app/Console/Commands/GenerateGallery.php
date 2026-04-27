<?php

namespace App\Console\Commands;

use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateGallery extends Command
{
    protected $signature   = 'gallery:generate';
    protected $description = 'Генерирует тестовые паки с фотографиями для айдола';

    private const IMG_WIDTH   = 1080;
    private const IMG_HEIGHT  = 1440;
    private const IMG_QUALITY = 88;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan>✦ Генератор галереи</>');
        $this->newLine();

        $idol = $this->resolveUser('Айдол (email / имя / ID)', requireIdol: true);
        if (!$idol) return self::FAILURE;

        $buyer = $this->resolveUser('Покупатель (email / имя / ID)');
        if (!$buyer) return self::FAILURE;

        $packCount  = (int) $this->ask('Количество паков', 5);
        $photoCount = (int) $this->ask('Фото в каждом паке', 20);
        $price      = (int) $this->ask('Цена каждого пака (₽)', 500);

        $this->newLine();
        $this->table([], [
            ['<fg=gray>Айдол</>',    "<fg=white>{$idol->name}</> <fg=gray>(#{$idol->id})</>"],
            ['<fg=gray>Покупатель</>', "<fg=white>{$buyer->name}</> <fg=gray>(#{$buyer->id})</>"],
            ['<fg=gray>Паков</>',    "<fg=white>{$packCount}</>"],
            ['<fg=gray>Фото/пак</>', "<fg=white>{$photoCount}</>"],
            ['<fg=gray>Цена</>',     "<fg=white>{$price} ₽</>"],
            ['<fg=gray>Файлов</>', "<fg=white>" . ($packCount * $photoCount) . "</> (~" . round(self::IMG_QUALITY * 0.4) . " МБ/фото)"],
        ]);
        $this->newLine();

        if (!$this->confirm('Создать?', true)) {
            return self::SUCCESS;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar($packCount * $photoCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        for ($p = 1; $p <= $packCount; $p++) {
            $pack = ContentPack::create([
                'user_id'      => $idol->id,
                'title'        => "Pack {$p}",
                'price'        => $price,
                'status'       => 'published',
                'published_at' => now(),
            ]);

            $dir = "content-packs/{$pack->id}";
            Storage::disk('public')->makeDirectory($dir);

            for ($ph = 1; $ph <= $photoCount; $ph++) {
                $bar->setMessage("Пак {$p}/{$packCount}, фото {$ph}/{$photoCount}");

                $filename = "{$dir}/photo_{$ph}.jpg";
                $fullPath = Storage::disk('public')->path($filename);

                $this->generateImage($fullPath, $idol->id, $pack->id, $ph);

                ContentPackPhoto::create([
                    'content_pack_id' => $pack->id,
                    'path'            => $filename,
                    'sort_order'      => $ph,
                ]);

                $bar->advance();
            }

            ContentPackPurchase::create([
                'content_pack_id' => $pack->id,
                'user_id'         => $buyer->id,
                'price_paid'      => $price,
                'purchased_at'    => now(),
            ]);
        }

        $bar->setMessage('Готово!');
        $bar->finish();
        $this->newLine(2);

        $this->line('  <fg=green>✦ Готово!</>');
        $this->newLine();
        $this->table([], [
            ['<fg=gray>Создано паков</>',  "<fg=white>{$packCount}</>"],
            ['<fg=gray>Создано фото</>',   "<fg=white>" . ($packCount * $photoCount) . "</>"],
            ['<fg=gray>Покупок создано</>', "<fg=white>{$packCount}</>"],
        ]);
        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveUser(string $label, bool $requireIdol = false): ?User
    {
        $input = $this->ask($label);

        $user = is_numeric($input)
            ? User::find((int) $input)
            : User::where('email', $input)->orWhere('name', $input)->first();

        if (!$user) {
            $this->error("Пользователь не найден: «{$input}»");
            return null;
        }

        if ($requireIdol && !$user->is_idol) {
            $this->error("Пользователь «{$user->name}» не является айдолом.");
            return null;
        }

        return $user;
    }

    private function generateImage(string $path, int $idolId, int $packId, int $photoNum): void
    {
        $img = imagecreatetruecolor(self::IMG_WIDTH, self::IMG_HEIGHT);

        $hue = (($idolId * 73 + $packId * 37 + $photoNum * 13) % 360);
        [$r1, $g1, $b1] = $this->hsvToRgb($hue, 0.45, 0.25);
        [$r2, $g2, $b2] = $this->hsvToRgb(($hue + 60) % 360, 0.5, 0.7);

        for ($y = 0; $y < self::IMG_HEIGHT; $y++) {
            $t   = $y / self::IMG_HEIGHT;
            $col = imagecolorallocate(
                $img,
                (int) ($r1 + ($r2 - $r1) * $t),
                (int) ($g1 + ($g2 - $g1) * $t),
                (int) ($b1 + ($b2 - $b1) * $t),
            );
            imageline($img, 0, $y, self::IMG_WIDTH - 1, $y, $col);
        }

        for ($n = 0; $n < 8000; $n++) {
            imagesetpixel(
                $img,
                rand(0, self::IMG_WIDTH - 1),
                rand(0, self::IMG_HEIGHT - 1),
                imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255)),
            );
        }

        $label  = "Pack #{$packId}  Photo #{$photoNum}";
        $white  = imagecolorallocate($img, 255, 255, 255);
        $shadow = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 5, 21, 21, $label, $shadow);
        imagestring($img, 5, 20, 20, $label, $white);

        imagejpeg($img, $path, self::IMG_QUALITY);
        imagedestroy($img);
    }

    private function hsvToRgb(int $h, float $s, float $v): array
    {
        $h = $h / 60;
        $i = (int) $h;
        $f = $h - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $s * $f);
        $t = $v * (1 - $s * (1 - $f));

        [$r, $g, $b] = match ($i % 6) {
            0       => [$v, $t, $p],
            1       => [$q, $v, $p],
            2       => [$p, $v, $t],
            3       => [$p, $q, $v],
            4       => [$t, $p, $v],
            default => [$v, $p, $q],
        };

        return [(int) ($r * 255), (int) ($g * 255), (int) ($b * 255)];
    }
}
