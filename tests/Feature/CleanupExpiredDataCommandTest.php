<?php

declare(strict_types=1);

use App\Actions\DeleteExpiredChatMessagesAction;
use App\Actions\DeleteExpiredSendsAction;
use App\Actions\DeleteExpiredUploadedFilesAction;
use App\Contracts\ExpirableCleanupInterface;
use App\Models\ChatMessage;
use App\Models\Send;
use App\Models\User;
use App\Services\Interfaces\SendReadServiceInterface;
use App\Support\SendIndexColumns;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Factories\SendFactory;

it('runs all tagged expirable cleanup tasks', function () {
    config(['filesystems.upload.disk' => 'r2']);
    Storage::fake('r2');

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    $expiredMessage = ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    ChatMessage::query()
        ->whereKey($expiredMessage->id)
        ->update(['created_at' => now()->subDays(8)]);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'payload' => fakeChatPayload(),
    ]);

    SendFactory::create($alice, [
        'message' => 'expired secret',
        'name' => 'Expired Send',
        'valid_to' => now()->subMinute(),
    ]);

    SendFactory::create($alice, [
        'message' => 'active secret',
        'name' => 'Active Send',
    ]);

    $expiredPath = "uploads/{$alice->id}/expired.bin";
    $freshPath = "uploads/{$alice->id}/fresh.bin";

    Storage::disk('r2')->put($expiredPath, 'expired');
    Storage::disk('r2')->put($freshPath, 'fresh');
    touch(Storage::disk('r2')->path($expiredPath), now()->subDays(31)->getTimestamp());

    $this->artisan('system:cleanup-expired')
        ->expectsOutputToContain('Deleted 1 expired send(s).')
        ->expectsOutputToContain('Deleted 1 expired chat message(s).')
        ->expectsOutputToContain('Deleted 1 expired uploaded file(s).')
        ->assertSuccessful();

    expect(ChatMessage::query()->count())->toBe(1)
        ->and(ChatMessage::query()->whereKey($expiredMessage->id)->exists())->toBeFalse()
        ->and(Send::query()->count())->toBe(1)
        ->and(Send::query()->where('name', 'Expired Send')->exists())->toBeFalse()
        ->and(Send::query()->where('name', 'Active Send')->exists())->toBeTrue();

    Storage::disk('r2')->assertMissing($expiredPath);
    Storage::disk('r2')->assertExists($freshPath);
});

it('schedules system cleanup every thirty minutes', function () {
    $this->artisan('schedule:list');

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'system:cleanup-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/30 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('tags cleanup actions that implement the expirable cleanup contract', function () {
    $tasks = iterator_to_array(app()->tagged('expirable-cleanup'));

    expect($tasks)->toHaveCount(3)
        ->and($tasks[0])->toBeInstanceOf(DeleteExpiredSendsAction::class)
        ->and($tasks[1])->toBeInstanceOf(DeleteExpiredChatMessagesAction::class)
        ->and($tasks[2])->toBeInstanceOf(DeleteExpiredUploadedFilesAction::class);

    foreach ($tasks as $task) {
        expect($task)->toBeInstanceOf(ExpirableCleanupInterface::class)
            ->and($task->getEntityName())->not->toBeEmpty();
    }
});

it('clears cached send lists when expired sends are deleted', function () {
    $user = User::factory()->create();

    SendFactory::create($user, [
        'message' => 'active secret',
        'name' => 'Active Send',
    ]);

    $this->actingAs($user);
    app(SendReadServiceInterface::class)->findAll();

    $cacheKey = 'sends_'.$user->id.'_'.hash('xxh128', json_encode(array_values(SendIndexColumns::COLUMNS)));
    expect(Cache::get($cacheKey))->toHaveCount(1);

    SendFactory::create($user, [
        'message' => 'expired secret',
        'name' => 'Expired Send',
        'valid_to' => now()->subMinute(),
    ]);

    app(DeleteExpiredSendsAction::class)->execute();

    expect(Cache::get($cacheKey))->toBeNull()
        ->and(app(SendReadServiceInterface::class)->findAll())->toHaveCount(1);
});
