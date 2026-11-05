<?php

namespace Tests\Feature;

use App\Http\Controllers\NewsPublicController;
use App\Models\Admin;
use App\Models\AdminLog;
use App\Models\News;
use App\Models\Post;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CursorPaginationTest extends TestCase
{
    use RefreshDatabase;
    public function test_fitness_service_category_exists(): void
    {
        $this->seed(ServiceCategorySeeder::class);

        $category = ServiceCategory::where('accent_color', '#f97316')->first();

        $this->assertNotNull($category);
        $this->assertEquals('Фитнес', $category->getTranslation('name', 'ru'));
        $this->assertEquals('Fitness', $category->getTranslation('name', 'en'));
    }

    public function test_news_cursor_pagination_handles_pinned_articles(): void
    {
        $pinnedOld = News::create([
            'title' => 'Old Pinned News',
            'excerpt' => 'Old pinned excerpt',
            'body' => 'Old pinned body',
            'is_pinned' => true,
            'published_at' => now()->subDays(10),
        ]);

        for ($i = 1; $i <= 9; $i++) {
            News::create([
                'title' => 'New Article ' . $i,
                'excerpt' => 'New excerpt ' . $i,
                'body' => 'New body ' . $i,
                'is_pinned' => false,
                'published_at' => now()->subDays($i),
            ]);
        }

        $controller = new NewsPublicController();
        $req1 = Request::create('/news/feed', 'GET');
        app()->instance('request', $req1);
        $res1 = $controller->feed($req1);
        $data1 = $res1->getData(true);

        $this->assertTrue($data1['has_more']);
        $this->assertEquals($pinnedOld->id, $data1['data'][0]['id']);
        $this->assertNotNull($data1['next_cursor']);

        // Next page with cursor
        $req2 = Request::create('/news/feed', 'GET', ['cursor' => $data1['next_cursor']]);
        app()->instance('request', $req2);
        $res2 = $controller->feed($req2);
        $data2 = $res2->getData(true);

        $this->assertNotEmpty($data2['data']);
        $this->assertEquals('New Article 8', $data2['data'][0]['title']);
    }

    public function test_profile_posts_cursor_pagination(): void
    {
        $user = User::factory()->create();

        $post1 = Post::create(['user_id' => $user->id, 'body' => 'First post']);
        $post2 = Post::create(['user_id' => $user->id, 'body' => 'Second post']);
        $post3 = Post::create(['user_id' => $user->id, 'body' => 'Third post']);

        $res1 = $this->getJson(route('profile.posts.feed', ['user' => $user->id]));
        $res1->assertOk();
        $data = $res1->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals($post3->id, $data[0]['id']);

        // Test with cursor pointing to post3
        $res2 = $this->getJson(route('profile.posts.feed', ['user' => $user->id, 'cursor' => $post3->id]));
        $res2->assertOk();
        $data2 = $res2->json('data');
        $this->assertCount(2, $data2);
        $this->assertEquals($post2->id, $data2[0]['id']);
        $this->assertEquals($post1->id, $data2[1]['id']);
    }

    public function test_admin_logs_cursor_pagination(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'cursor_admin@test.local',
            'password' => bcrypt('secret'),
        ]);

        for ($i = 1; $i <= 55; $i++) {
            AdminLog::create([
                'admin_id' => $admin->id,
                'action' => 'action_' . $i,
                'target_type' => 'test',
                'target_id' => $i,
            ]);
        }

        $res = $this->actingAs($admin, 'admin')->get(route('admin.logs.index'));
        $res->assertOk();

        $res->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Logs/Index')
            ->has('logs.data', 50)
            ->whereNot('logs.next_page_url', null)
        );
    }
}
