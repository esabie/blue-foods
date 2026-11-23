<?php

use App\Jobs\SendSms;
use App\Models\AuditLog;
use App\Models\OnlineOrder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->w = stockWorld();
});

test('management can text the marketing team in bulk', function () {
    Queue::fake();
    $other = User::factory()->marketing()->create(['phone' => '+233241111111']);
    User::factory()->marketing()->create(['phone' => null]);

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'marketing',
        'message' => 'Please hand in cash by Friday.',
    ])->assertRedirect(route('bulk-sms.create'))->assertSessionHas('success');

    Queue::assertPushed(SendSms::class, 2);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === $this->w->member->phone
        && $job->message === 'Please hand in cash by Friday.');
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === $other->phone);
    Queue::assertNotPushed(SendSms::class, fn (SendSms $job) => $job->to === $this->w->warehouse->phone);

    $log = AuditLog::where('entity', 'bulk_sms')->sole();
    expect($log->user_id)->toBe($this->w->manager->id)
        ->and($log->action)->toBe('sent')
        ->and($log->after_json['recipient_count'])->toBe(2)
        ->and($log->after_json['audience'])->toBe('marketing')
        ->and($log->after_json['channel'])->toBe('staff');
});

test('management can text selected people only', function () {
    Queue::fake();
    $other = User::factory()->warehouse()->create(['phone' => '+233242222222']);

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'selected',
        'user_ids' => [$other->id],
        'message' => 'Warehouse meeting at 9.',
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === $other->phone);
});

test('management can text pasted public phone numbers', function () {
    Queue::fake();

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'public',
        'phones' => "024 123 4567\n0559876543, +233201112233",
        'message' => 'Fresh stock this weekend at Blue Foods.',
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(SendSms::class, 3);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === '+233241234567');
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === '+233559876543');
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === '+233201112233');

    expect(AuditLog::where('entity', 'bulk_sms')->latest('id')->first()->after_json['channel'])->toBe('public');
});

test('invalid public numbers are rejected', function () {
    Queue::fake();

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'public',
        'phones' => "024 123 4567\nnot-a-phone",
        'message' => 'Hello',
    ])->assertSessionHasErrors('phones');

    Queue::assertNothingPushed();
});

test('management can text past online customers', function () {
    ledger()->produce($this->w->product, 10, $this->w->warehouse);
    ledger()->allocateOnline($this->w->product, 5, $this->w->manager);
    placeOnlineOrder([$this->w->product->id => 1], 'pay_on_delivery');
    placeOnlineOrder([$this->w->product->id => 1], 'pay_on_delivery');

    // Same checkout phone from helper — distinct should be 1.
    expect(OnlineOrder::count())->toBe(2);

    Queue::fake();
    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'online_customers',
        'message' => 'Thank you for ordering from Blue Foods.',
    ])->assertSessionHasNoErrors();

    Queue::assertPushed(SendSms::class, 1);
    Queue::assertPushed(SendSms::class, fn (SendSms $job) => $job->to === '+233241234567'
        && str_contains($job->message, 'Thank you for ordering'));
});

test('bulk SMS requires a message and at least one recipient', function () {
    Queue::fake();

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'selected',
        'user_ids' => [],
        'message' => 'Hello',
    ])->assertSessionHasErrors('audience');

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'marketing',
        'message' => '',
    ])->assertSessionHasErrors('message');

    $this->actingAs($this->w->manager)->post(route('bulk-sms.store'), [
        'audience' => 'public',
        'phones' => '',
        'message' => 'Hello',
    ])->assertSessionHasErrors('phones');

    Queue::assertNothingPushed();
});

test('warehouse and marketing cannot open bulk SMS', function () {
    $this->actingAs($this->w->warehouse)->get(route('bulk-sms.create'))->assertForbidden();
    $this->actingAs($this->w->member)->post(route('bulk-sms.store'), [
        'audience' => 'marketing',
        'message' => 'Nope',
    ])->assertForbidden();
});

test('the compose page lists staff and public audiences', function () {
    $this->actingAs($this->w->manager)->get(route('bulk-sms.create'))
        ->assertInertia(fn ($page) => $page
            ->component('BulkSms/Create')
            ->has('staff_audiences')
            ->has('public_audiences')
            ->has('people')
            ->has('recent')
            ->where('max_length', 320));
});
