<?php

use App\Events\ContentPackPublishedEvent;
use App\Events\ContentPackPurchasedEvent;
use App\Events\PostCreatedEvent;
use App\Events\PostLikedEvent;
use App\Events\ReviewCreatedEvent;
use App\Events\UserFollowedEvent;
use App\Listeners\ContentPackPublishedAchievementListener;
use App\Listeners\ContentPackPurchasedAchievementListener;
use App\Listeners\FollowAchievementListener;
use App\Listeners\PostAchievementListener;
use App\Listeners\PostLikeAchievementListener;
use App\Listeners\ReviewAchievementListener;
use App\Models\AvatarFrame;
use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('FollowAchievementListener unlocks follower frames when conditions met', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $frame = AvatarFrame::create([
        'name' => '10 Followers Frame',
        'image_path' => 'frames/followers10.png',
        'type' => 'achievement',
        'condition_class' => 'has_10_followers',
        'is_active' => true,
    ]);

    // Create 10 followers
    for ($i = 0; $i < 10; $i++) {
        $follower = User::factory()->create();
        $idol->followers()->attach($follower->id);
    }

    $listener = app(FollowAchievementListener::class);
    $lastFollower = User::factory()->create();
    $listener->handle(new UserFollowedEvent($idol, $lastFollower));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});

test('PostAchievementListener unlocks post frames when count reached', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $frame = AvatarFrame::create([
        'name' => '10 Posts Frame',
        'image_path' => 'frames/posts10.png',
        'type' => 'achievement',
        'condition_class' => 'published_10_posts',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 10; $i++) {
        Post::create([
            'user_id' => $idol->id,
            'body' => "Post {$i}",
        ]);
    }

    $lastPost = $idol->posts()->first();
    $listener = app(PostAchievementListener::class);
    $listener->handle(new PostCreatedEvent($lastPost));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});

test('PostLikeAchievementListener unlocks frame when 100 likes reached', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $frame = AvatarFrame::create([
        'name' => '100 Likes Frame',
        'image_path' => 'frames/likes100.png',
        'type' => 'achievement',
        'condition_class' => 'post_liked_100_times',
        'is_active' => true,
    ]);

    $post = Post::create([
        'user_id' => $idol->id,
        'body' => 'Popular post',
    ]);

    for ($i = 0; $i < 100; $i++) {
        $user = User::factory()->create();
        PostLike::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }

    $listener = app(PostLikeAchievementListener::class);
    $listener->handle(new PostLikedEvent($post, $user));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});

test('ContentPackPublishedAchievementListener unlocks frame when 5 packs with 10 photos exist', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $frame = AvatarFrame::create([
        'name' => '5 Packs Frame',
        'image_path' => 'frames/packs5.png',
        'type' => 'achievement',
        'condition_class' => 'created_5_packs',
        'is_active' => true,
    ]);

    for ($p = 0; $p < 5; $p++) {
        $pack = ContentPack::create([
            'user_id' => $idol->id,
            'title' => "Pack {$p}",
            'price' => 500,
            'status' => 'published',
            'published_at' => now(),
        ]);

        for ($i = 0; $i < 10; $i++) {
            ContentPackPhoto::create([
                'content_pack_id' => $pack->id,
                'path' => "packs/{$pack->id}/photo_{$i}.jpg",
                'sort_order' => $i,
            ]);
        }
    }

    $listener = app(ContentPackPublishedAchievementListener::class);
    $listener->handle(new ContentPackPublishedEvent($pack));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});

test('ContentPackPurchasedAchievementListener unlocks sold packs frames', function () {
    $idol = User::factory()->create(['is_idol' => true]);
    $frame = AvatarFrame::create([
        'name' => 'First Pack Sold Frame',
        'image_path' => 'frames/pack_sold.png',
        'type' => 'achievement',
        'condition_class' => 'sold_first_pack',
        'is_active' => true,
    ]);

    $buyer = User::factory()->create();
    $pack = ContentPack::create([
        'user_id' => $idol->id,
        'title' => 'Exclusive Pack',
        'price' => 1000,
        'status' => 'published',
        'published_at' => now(),
    ]);

    $purchase = ContentPackPurchase::create([
        'content_pack_id' => $pack->id,
        'user_id' => $buyer->id,
        'price_paid' => 1000,
        'purchased_at' => now(),
    ]);

    $listener = app(ContentPackPurchasedAchievementListener::class);
    $listener->handle(new ContentPackPurchasedEvent($purchase));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});

test('ReviewAchievementListener unlocks review frames and rating frames', function () {
    $idol = User::factory()->create(['is_idol' => true, 'rating' => 45.0]);
    $frame = AvatarFrame::create([
        'name' => 'High Rating Frame',
        'image_path' => 'frames/rating40.png',
        'type' => 'achievement',
        'condition_class' => 'high_rating_40',
        'is_active' => true,
    ]);

    // Create 10 reviews
    for ($i = 0; $i < 10; $i++) {
        $reviewer = User::factory()->create();
        $review = Review::create([
            'reviewer_id' => $reviewer->id,
            'idol_id' => $idol->id,
            'rating' => 5,
            'text' => 'Great idol!',
            'is_hidden' => false,
        ]);
    }

    $listener = app(ReviewAchievementListener::class);
    $listener->handle(new ReviewCreatedEvent($review));

    expect($idol->avatarFrames()->where('avatar_frames.id', $frame->id)->exists())->toBeTrue();
});
