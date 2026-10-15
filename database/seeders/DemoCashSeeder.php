<?php

namespace Database\Seeders;

use App\Enums\HandInMethod;
use App\Enums\Role;
use App\Models\CashHandIn;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashBook;
use App\Services\StaffNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backdated cash hand-ins for the demo sales history. Most days are handed in the same
 * evening; some arrive with the next day's money or part-paid. Each member is left in a
 * different state so the Cash page has something to show: Kofi is days behind, Ama and
 * Kwabena owe their latest day's sales, Yaa is clear and has one cancelled double entry.
 */
class DemoCashSeeder extends Seeder
{
    /** How many of each member's most recent days with sales are left unpaid. */
    private const UNPAID_SALES_DAYS = [
        'ama@bluefoods.test' => 1,
        'kofi@bluefoods.test' => 4,
        'yaa@bluefoods.test' => 0,
        'kwabena@bluefoods.test' => 1,
    ];

    public function run(CashBook $cash, StaffNotifier $notifier): void
    {
        if (CashHandIn::exists()) {
            $this->command?->info('Cash hand-ins already present; skipping.');

            return;
        }

        try {
            $notifier->quietly(fn () => DB::transaction(fn () => $this->seed($cash)));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function seed(CashBook $cash): void
    {
        mt_srand(2027);
        $tz = config('inventory.display_timezone');
        $now = CarbonImmutable::now($tz);
        $today = $now->startOfDay();
        $manager = User::where('role', Role::Management)->where('email', 'manager@bluefoods.test')->first()
            ?? User::where('role', Role::Management)->human()->firstOrFail();

        $events = [];
        foreach (User::where('role', Role::Marketing)->get() as $member) {
            $byDay = Sale::notReversed()
                ->where('member_user_id', $member->id)
                ->orderBy('created_at')
                ->get(['created_at', 'total_pesewas'])
                ->groupBy(fn (Sale $s) => $s->created_at->setTimezone($tz)->toDateString());
            $byDay = $byDay->slice(0, max(0, $byDay->count() - (self::UNPAID_SALES_DAYS[$member->email] ?? 1)));

            $carry = 0;
            $lastDay = $byDay->keys()->last();
            foreach ($byDay as $date => $sales) {
                $due = $carry + (int) $sales->sum('total_pesewas');
                $roll = mt_rand(1, 20);

                if ($roll === 1 && $date !== $lastDay) {
                    $carry = $due;

                    continue;
                }

                $amount = $roll === 2 && $date !== $lastDay ? intdiv(intdiv($due * 7, 10), 1000) * 1000 : $due;
                if ($amount > 0) {
                    $events[] = [min(CarbonImmutable::parse($date, $tz)->setTime(18, mt_rand(0, 50)), $now), $member, $amount];
                }
                $carry = $due - $amount;
            }
        }

        usort($events, fn ($a, $b) => $a[0] <=> $b[0]);

        $doubleEntered = false;
        foreach ($events as [$at, $member, $amount]) {
            Carbon::setTestNow($at);
            $handIn = $this->handIn($cash, $member, $amount, $manager);

            if (! $doubleEntered && $handIn->method === HandInMethod::Cash && $member->email === 'yaa@bluefoods.test' && $at->gte($today->subDays(24))) {
                Carbon::setTestNow($at->addMinutes(2));
                $twice = $cash->record($member, $amount, HandInMethod::Cash, null, null, $manager);
                Carbon::setTestNow($at->addHour());
                $cash->cancel($twice, 'Entered twice by mistake', $manager);
                $doubleEntered = true;
            }
        }

        $this->command?->info(sprintf('Demo cash: %d hand-ins.', CashHandIn::count()));
    }

    private function handIn(CashBook $cash, User $member, int $amount, User $manager): CashHandIn
    {
        if (mt_rand(1, 5) <= 2) {
            $reference = sprintf('MP%s.%s.%s%05d', now()->format('ymd'), now()->format('Hi'), chr(65 + $member->id % 26), mt_rand(0, 99999));

            return $cash->record($member, $amount, HandInMethod::MobileMoney, $reference, null, $manager);
        }

        return $cash->record($member, $amount, HandInMethod::Cash, null, null, $manager);
    }
}
