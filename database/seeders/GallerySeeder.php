<?php

namespace Database\Seeders;

use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class GallerySeeder extends Seeder
{
    // Параметры — меняй под нужный сценарий
    private int $idolCount    = 5;
    private int $packsPerIdol = 8;
    private int $photosPerPack = 20;

    // Разрешение и качество — влияют на вес файла (~2-5 МБ при quality=88)
    private int $imgWidth   = 1080;
    private int $imgHeight  = 1440;
    private int $imgQuality = 88;

    public function run(): void
    {
        $buyer = $this->ensureBuyer();

        $this->command->info("Покупатель: {$buyer->email} / password");
        $this->command->info("Создаём {$this->idolCount} айдолов, по {$this->packsPerIdol} паков, по {$this->photosPerPack} фото...");

        for ($i = 1; $i <= $this->idolCount; $i++) {
            $idol = User::create([
                'name'              => "Idol {$i}",
                'email'             => "idol{$i}_seed@example.com",
                'password'          => Hash::make('password'),
                'is_idol'           => true,
                'email_verified_at' => now(),
            ]);

            for ($j = 1; $j <= $this->packsPerIdol; $j++) {
                $pack = ContentPack::create([
                    'user_id'      => $idol->id,
                    'title'        => "Pack {$j} by Idol {$i}",
                    'description'  => "Seed pack {$j} for idol {$i}",
                    'price'        => fake()->numberBetween(100, 1500),
                    'status'       => 'published',
                    'published_at' => now(),
                ]);

                $dir = "content-packs/{$pack->id}";
                Storage::disk('public')->makeDirectory($dir);

                for ($k = 1; $k <= $this->photosPerPack; $k++) {
                    $filename = "{$dir}/photo_{$k}.jpg";
                    $fullPath = Storage::disk('public')->path($filename);

                    $this->generateImage($fullPath, $i, $j, $k);

                    ContentPackPhoto::create([
                        'content_pack_id' => $pack->id,
                        'path'            => $filename,
                        'sort_order'      => $k,
                    ]);
                }

                ContentPackPurchase::create([
                    'content_pack_id' => $pack->id,
                    'user_id'         => $buyer->id,
                    'price_paid'      => $pack->price,
                    'purchased_at'    => now(),
                ]);

                $this->command->getOutput()->write('.');
            }
        }

        $total = $this->idolCount * $this->packsPerIdol * $this->photosPerPack;
        $this->command->newLine();
        $this->command->info("Готово. Создано {$total} фото, {$this->idolCount} айдолов, " . ($this->idolCount * $this->packsPerIdol) . " паков.");
    }

    private function ensureBuyer(): User
    {
        $email = 'gallery_buyer@example.com';
        $existing = User::where('email', $email)->first();
        if ($existing) {
            return $existing;
        }
        return User::create([
            'name'              => 'Gallery Buyer',
            'email'             => $email,
            'password'          => Hash::make('password'),
            'is_idol'           => false,
            'email_verified_at' => now(),
        ]);
    }

    private function generateImage(string $path, int $idol, int $pack, int $photo): void
    {
        $img = imagecreatetruecolor($this->imgWidth, $this->imgHeight);

        // Уникальный фоновый градиент на основе индексов
        $hue = (($idol * 73 + $pack * 37 + $photo * 13) % 360);
        [$r1, $g1, $b1] = $this->hsvToRgb($hue, 0.45, 0.25);
        [$r2, $g2, $b2] = $this->hsvToRgb(($hue + 60) % 360, 0.5, 0.7);

        for ($y = 0; $y < $this->imgHeight; $y++) {
            $t   = $y / $this->imgHeight;
            $r   = (int) ($r1 + ($r2 - $r1) * $t);
            $g   = (int) ($g1 + ($g2 - $g1) * $t);
            $b   = (int) ($b1 + ($b2 - $b1) * $t);
            $col = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $this->imgWidth - 1, $y, $col);
        }

        // Случайный шум для уникальности и реалистичного веса файла
        for ($n = 0; $n < 8000; $n++) {
            $x   = rand(0, $this->imgWidth - 1);
            $y   = rand(0, $this->imgHeight - 1);
            $col = imagecolorallocatealpha($img, rand(0, 255), rand(0, 255), rand(0, 255), rand(60, 100));
            imagesetpixel($img, $x, $y, $col);
        }

        // Подпись для ориентира при тестировании
        $label = "Idol {$idol} / Pack {$pack} / #{$photo}";
        $white = imagecolorallocate($img, 255, 255, 255);
        $shadow = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 5, 21, 21, $label, $shadow);
        imagestring($img, 5, 20, 20, $label, $white);

        imagejpeg($img, $path, $this->imgQuality);
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
            0 => [$v, $t, $p],
            1 => [$q, $v, $p],
            2 => [$p, $v, $t],
            3 => [$p, $q, $v],
            4 => [$t, $p, $v],
            default => [$v, $p, $q],
        };

        return [(int) ($r * 255), (int) ($g * 255), (int) ($b * 255)];
    }
}
