<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Jobs\SendSms;
use App\Models\AuditLog;
use App\Models\OnlineOrder;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Management-triggered texts to staff or to a pasted / customer phone list.
 */
class BulkSms
{
    public const STAFF_AUDIENCES = [
        'marketing' => 'Marketing team',
        'warehouse' => 'Warehouse staff',
        'management' => 'Management',
        'all_staff' => 'All staff',
        'selected' => 'Pick people',
    ];

    public const PUBLIC_AUDIENCES = [
        'public' => 'Paste phone numbers',
        'online_customers' => 'Past online customers',
    ];

    public const AUDIENCES = self::STAFF_AUDIENCES + self::PUBLIC_AUDIENCES;

    public const MAX_LENGTH = 320;

    public const MAX_PUBLIC_RECIPIENTS = 500;

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, User>
     */
    public function staffRecipients(string $audience, array $userIds = []): Collection
    {
        $query = User::human()
            ->where('status', UserStatus::Active)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name');

        return match ($audience) {
            'marketing' => $query->where('role', Role::Marketing)->get(),
            'warehouse' => $query->where('role', Role::Warehouse)->get(),
            'management' => $query->where('role', Role::Management)->get(),
            'all_staff' => $query->get(),
            'selected' => $query->whereIn('id', $userIds)->get(),
            default => collect(),
        };
    }

    /**
     * Distinct checkout phones from online orders, newest customers first.
     *
     * @return Collection<int, string>
     */
    public function onlineCustomerPhones(): Collection
    {
        return OnlineOrder::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('id')
            ->pluck('phone')
            ->map(fn (string $phone) => Phone::normalize($phone))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function parsePhones(string $raw): Collection
    {
        // Split on newlines / commas / semicolons only, so "024 123 4567" stays one number.
        $parts = preg_split('/[\n\r,;]+/u', trim($raw)) ?: [];
        $phones = collect($parts)
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values();

        if ($phones->isEmpty()) {
            throw ValidationException::withMessages([
                'phones' => 'Paste at least one phone number.',
            ]);
        }

        $invalid = [];
        $normalized = collect();

        foreach ($phones as $part) {
            if (! Phone::looksLikePhone($part)) {
                $invalid[] = $part;
                continue;
            }
            $phone = Phone::normalize($part);
            if ($phone === null || ! preg_match('/^\+\d{9,15}$/', $phone)) {
                $invalid[] = $part;
                continue;
            }
            $normalized->push($phone);
        }

        if ($invalid !== []) {
            $sample = collect($invalid)->take(3)->implode(', ');
            throw ValidationException::withMessages([
                'phones' => 'These numbers look invalid: '.$sample.(count($invalid) > 3 ? '…' : '').'. Use Ghana format, e.g. 024 123 4567.',
            ]);
        }

        $unique = $normalized->unique()->values();
        if ($unique->count() > self::MAX_PUBLIC_RECIPIENTS) {
            throw ValidationException::withMessages([
                'phones' => 'Send to at most '.self::MAX_PUBLIC_RECIPIENTS.' numbers at a time.',
            ]);
        }

        return $unique;
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, string>
     */
    public function resolvePhones(string $audience, array $userIds = [], string $phonesRaw = ''): Collection
    {
        return match ($audience) {
            'public' => $this->parsePhones($phonesRaw),
            'online_customers' => $this->onlineCustomerPhones()->take(self::MAX_PUBLIC_RECIPIENTS)->values(),
            default => $this->staffRecipients($audience, $userIds)->pluck('phone')->filter()->unique()->values(),
        };
    }

    /**
     * Queue one SMS per phone and write an audit row.
     *
     * @param  list<int>  $userIds
     * @return array{count: int, phones: Collection<int, string>}
     */
    public function send(
        string $audience,
        string $message,
        User $actor,
        array $userIds = [],
        string $phonesRaw = '',
    ): array {
        $message = trim($message);
        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Write a message to send.']);
        }
        if (mb_strlen($message) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'message' => 'Keep the message to '.self::MAX_LENGTH.' characters or fewer.',
            ]);
        }

        if (! array_key_exists($audience, self::AUDIENCES)) {
            throw ValidationException::withMessages([
                'audience' => 'Choose who should receive this text.',
            ]);
        }

        $phones = $this->resolvePhones($audience, $userIds, $phonesRaw);
        if ($phones->isEmpty()) {
            $field = in_array($audience, ['public', 'online_customers'], true) ? 'phones' : 'audience';
            throw ValidationException::withMessages([
                $field => match ($audience) {
                    'selected' => 'Select at least one person who has a phone number.',
                    'public' => 'Paste at least one phone number.',
                    'online_customers' => 'No online customer phones yet.',
                    default => 'Nobody in that group has a phone number on file.',
                },
            ]);
        }

        foreach ($phones as $phone) {
            SendSms::dispatch($phone, $message);
        }

        AuditLog::create([
            'user_id' => $actor->id ?? Auth::id(),
            'entity' => 'bulk_sms',
            'entity_id' => $actor->id,
            'action' => 'sent',
            'before_json' => null,
            'after_json' => [
                'audience' => $audience,
                'audience_label' => self::AUDIENCES[$audience],
                'channel' => array_key_exists($audience, self::PUBLIC_AUDIENCES) ? 'public' : 'staff',
                'recipient_count' => $phones->count(),
                'recipient_ids' => array_key_exists($audience, self::STAFF_AUDIENCES)
                    ? $this->staffRecipients($audience, $userIds)->pluck('id')->all()
                    : [],
                'message' => $message,
            ],
        ]);

        return ['count' => $phones->count(), 'phones' => $phones];
    }
}
