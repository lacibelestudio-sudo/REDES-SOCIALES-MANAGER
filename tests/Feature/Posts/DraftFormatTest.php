<?php

use App\Enums\InstagramAccountType;
use App\Enums\PostFormat;
use App\Enums\WorkspaceRole;
use App\Models\ConnectedAccount;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    WorkspaceMembership::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $this->user->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    $this->account = ConnectedAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => 'instagram',
        'capabilities' => ['instagram_account_type' => InstagramAccountType::Business->value],
    ]);
});

test('Instagram Stories are rejected for Creator and unknown account types', function (string $type): void {
    $this->account->forceFill([
        'capabilities' => ['instagram_account_type' => $type],
    ])->save();
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)->putJson(route('posts.update', $post), [
        'segments' => ['Existing story remains unchanged'],
        'destination' => ['kind' => 'account', 'id' => $this->account->id],
        'targets' => [[
            'connected_account_id' => $this->account->id,
            'format' => PostFormat::Story->value,
        ]],
        'media_ids' => [],
        'expected_updated_at' => $post->updated_at->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('targets.0.format');

    expect($post->targets()->count())->toBe(0);
})->with([
    'Creator' => [InstagramAccountType::Creator->value],
    'unknown' => [InstagramAccountType::Unknown->value],
]);

test('a story update cannot use an Instagram account from another workspace', function (): void {
    $foreignWorkspace = Workspace::factory()->create();
    $foreignAccount = ConnectedAccount::factory()->create([
        'workspace_id' => $foreignWorkspace->id,
        'platform' => 'instagram',
        'capabilities' => ['instagram_account_type' => InstagramAccountType::Business->value],
    ]);
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)->putJson(route('posts.update', $post), [
        'segments' => ['No cross-workspace account'],
        'destination' => ['kind' => 'account', 'id' => $foreignAccount->id],
        'targets' => [[
            'connected_account_id' => $foreignAccount->id,
            'format' => PostFormat::Story->value,
        ]],
        'media_ids' => [],
        'expected_updated_at' => $post->updated_at->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('targets.0.connected_account_id');

    expect($post->targets()->count())->toBe(0);
});

test('a target format round-trips through save and PostView', function () {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->putJson(route('posts.update', $post), [
        'segments' => ['Hello from a story'],
        'destination' => ['kind' => 'account', 'id' => $this->account->id],
        'targets' => [[
            'connected_account_id' => $this->account->id,
            'format' => 'story',
        ]],
        'media_ids' => [],
        'expected_updated_at' => $post->updated_at->toIso8601String(),
    ]);

    $response->assertOk();
    $target = $response->json('post.targets.0');
    expect($target['format'])->toBe('story');
    expect($post->targets()->first()->fresh()->format)->toBe(PostFormat::Story);
});

test('an omitted format leaves the existing target format untouched', function () {
    $post = Post::factory()->create(['workspace_id' => $this->workspace->id]);
    $post->targets()->create([
        'connected_account_id' => $this->account->id,
        'platform' => 'instagram',
        'sections' => ['x'],
        'format' => 'reels',
    ]);

    $this->actingAs($this->user)->putJson(route('posts.update', $post), [
        'segments' => ['x'],
        'destination' => ['kind' => 'account', 'id' => $this->account->id],
        'targets' => [['connected_account_id' => $this->account->id]],
        'media_ids' => [],
        'expected_updated_at' => $post->fresh()->updated_at->toIso8601String(),
    ])->assertOk();

    expect($post->targets()->first()->fresh()->format)->toBe(PostFormat::Reels);
});
