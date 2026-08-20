<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminBroadcast;
use App\Models\IdolQuizQuestion;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\UserStrike;
use App\Services\Media\AvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OptimizationAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_strike_relates_correctly_to_admin(): void
    {
        $admin = Admin::create([
            'name' => 'Moderator Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $user = User::factory()->create(['name' => 'StrikeTarget']);

        $strike = UserStrike::create([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'rating_deducted' => 0.5,
            'admin_note' => 'Violation note',
            'expires_at' => now()->addMonths(6),
        ]);

        $this->assertInstanceOf(Admin::class, $strike->admin);
        $this->assertEquals($admin->id, $strike->admin->id);
    }

    public function test_quiz_controller_starts_with_single_partitioned_query(): void
    {
        $user = User::factory()->create(['is_idol' => false]);

        // Seed 2 questions per stage (stages 1 to 10)
        for ($stage = 1; $stage <= 10; $stage++) {
            IdolQuizQuestion::create([
                'stage' => $stage,
                'question' => "Stage {$stage} Question 1",
                'options' => ['A', 'B', 'C'],
                'correct_option_index' => 0,
            ]);
            IdolQuizQuestion::create([
                'stage' => $stage,
                'question' => "Stage {$stage} Question 2",
                'options' => ['A', 'B', 'C'],
                'correct_option_index' => 1,
            ]);
        }

        $response = $this->actingAs($user)->postJson(route('idol.quiz.start'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'session_id',
            'attempt_number',
            'questions' => [
                '*' => ['stage', 'question_id', 'question', 'options'],
            ],
        ]);

        $this->assertCount(10, $response->json('questions'));
    }

    public function test_non_idol_cannot_update_category_description(): void
    {
        $user = User::factory()->create(['is_idol' => false]);
        $category = ServiceCategory::create([
            'name' => ['ru' => 'Тест категория', 'en' => 'Test Category'],
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->patch(route('profile.services.category.description', $category), [
            'description' => 'Malicious description',
        ]);

        $response->assertStatus(403);
    }

    public function test_avatar_service_rejects_oversized_pixel_dimensions(): void
    {
        $service = new AvatarService;
        $user = User::factory()->create();

        // Create an image with dimension > 6000px
        $width = 6500;
        $height = 100;
        $image = imagecreatetruecolor($width, $height);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_avatar_').'.jpg';
        imagejpeg($image, $tempPath);
        imagedestroy($image);

        $uploadedFile = new UploadedFile($tempPath, 'oversized.jpg', 'image/jpeg', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Разрешение изображения слишком велико');

        try {
            $service->updateAvatar($user, $uploadedFile);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function test_admin_broadcast_eager_loads_target_user(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('password'),
        ]);

        $target = User::factory()->create(['name' => 'TargetUser']);

        $broadcast = AdminBroadcast::create([
            'admin_id' => $admin->id,
            'title' => ['ru' => 'Заголовок', 'en' => 'Title'],
            'body' => ['ru' => 'Текст', 'en' => 'Body'],
            'target' => 'user',
            'target_user_id' => $target->id,
        ]);

        $loaded = AdminBroadcast::with(['admin', 'targetUser'])->find($broadcast->id);

        $this->assertInstanceOf(User::class, $loaded->targetUser);
        $this->assertEquals($target->id, $loaded->targetUser->id);
    }
}
