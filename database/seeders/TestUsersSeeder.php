<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('123123');
        $purchasesMade = 0;

        for ($i = 1; $i <= 100; $i++) {
            $isIdol = $i % 2 === 0;
            $gender = $isIdol ? 'female' : 'male';
            $email = "u{$i}@test.com";
            $name = "u{$i}".($isIdol ? ' (Idol)' : '');

            $user = User::where('email', $email)->orWhere('name', $name)->first();

            if (! $user) {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'password' => $password,
                    'is_idol' => $isIdol,
                    'gender' => $gender,
                    'email_verified_at' => now(),
                ]);
            }

            if ($isIdol) {
                $photoPath = "content-packs/{$user->id}-test/cover.jpg";
                Storage::disk(config('filesystems.default'))->put($photoPath, $this->generateImageBuffer($i, 1));

                // Generate a pack for this idol
                $pack = \App\Models\ContentPack::firstOrCreate([
                    'user_id' => $user->id,
                    'title' => "Test Pack by {$user->name}",
                ], [
                    'description' => 'This is a test pack.',
                    'price' => 500,
                    'status' => 'published',
                    'published_at' => now(),
                    'cover_path' => $photoPath,
                ]);

                \App\Models\ContentPackPhoto::firstOrCreate([
                    'content_pack_id' => $pack->id,
                    'path' => $photoPath,
                    'sort_order' => 1,
                ]);

                if ($purchasesMade < 2) {
                    // Give this pack to user 1 (the first test user) as a purchase, unviewed
                    \App\Models\ContentPackPurchase::firstOrCreate([
                        'content_pack_id' => $pack->id,
                        'user_id' => 1,
                    ], [
                        'price_paid' => $pack->price,
                        'purchased_at' => now(),
                        'viewed_at' => null,
                    ]);
                    $purchasesMade++;
                }
            }
        }
    }

    private function generateImageBuffer(int $idolId, int $photoId): string
    {
        $width = 400;
        $height = 600;
        $img = imagecreatetruecolor($width, $height);

        $hue = (($idolId * 73 + $photoId * 13) % 360);
        [$r1, $g1, $b1] = $this->hsvToRgb($hue, 0.45, 0.25);
        [$r2, $g2, $b2] = $this->hsvToRgb(($hue + 60) % 360, 0.5, 0.7);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $r = (int) ($r1 + ($r2 - $r1) * $t);
            $g = (int) ($g1 + ($g2 - $g1) * $t);
            $b = (int) ($b1 + ($b2 - $b1) * $t);
            $col = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width - 1, $y, $col);
        }

        $label = "Idol {$idolId} / #{$photoId}";
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 20, 20, $label, $white);

        ob_start();
        imagejpeg($img, null, 70);
        $buffer = ob_get_clean();
        imagedestroy($img);

        return $buffer;
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
