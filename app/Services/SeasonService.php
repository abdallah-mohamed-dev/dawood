<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Enums\SeasonStatus;
use App\Models\CashboxTransaction;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only writer for the seasons tables (specs/012 ق-9). A season is closed
 * by a button, never automatically (ق-3). Money that is still held — cash,
 * stock, work in progress, what is owed to partners — is carried forward;
 * only the profit of the period is frozen.
 */
class SeasonService
{
    public function __construct(
        private readonly ProfitService $profit,
        private readonly PartnerService $partners,
        private readonly CashboxService $cashbox,
        private readonly InventoryService $inventory,
        private readonly SeasonBackupService $backup,
    ) {}

    /**
     * The open season. Creates one if there is none — only happens if the
     * database was built without the first-season migration.
     */
    public function current(): Season
    {
        $open = Season::query()->where('status', SeasonStatus::Open)->first();

        if ($open !== null) {
            return $open;
        }

        $last = Season::query()->orderByDesc('number')->first();

        return Season::query()->create([
            'number' => ($last?->number ?? 0) + 1,
            'started_at' => $last?->ended_at?->addDay()->toDateString() ?? now()->toDateString(),
            'status' => SeasonStatus::Open,
            'loss_carried_in' => (int) ($last?->getRawOriginal('loss_carried_out') ?? 0),
        ]);
    }

    /**
     * "موسم 2 — 2026/03 → 2026/08", or the same with "حتى الآن" for the open one.
     */
    public function displayName(Season $season): string
    {
        if ($season->name !== null && $season->name !== '') {
            return $season->name;
        }

        $end = $season->ended_at?->format('Y/m') ?? 'حتى الآن';

        return 'موسم '.$season->number.' — '.$season->started_at->format('Y/m').' → '.$end;
    }

    /**
     * Every number the close screen shows, read without writing anything.
     *
     * @return array<string, mixed>
     */
    public function preview(DateTimeInterface|string|null $endsAt = null): array
    {
        // Read-only on purpose: no current() here, because that would create a season.
        $season = Season::query()->where('status', SeasonStatus::Open)->firstOrFail();
        $endsAt = $endsAt === null ? now()->toDateString() : CarbonImmutable::parse($endsAt)->toDateString();

        return $this->calculate($season, $endsAt);
    }

    /**
     * Closes the open season at $endsAt. The database copy is taken first;
     * if that fails, nothing else happens.
     */
    public function close(DateTimeInterface|string $endsAt): Season
    {
        $endsAt = CarbonImmutable::parse($endsAt)->toDateString();

        $this->backup->create();

        return DB::transaction(function () use ($endsAt) {
            $season = Season::query()->where('status', SeasonStatus::Open)->lockForUpdate()->first();

            if ($season === null) {
                throw new RuntimeException('مفيش موسم مفتوح دلوقتي.');
            }

            if ($endsAt < $season->started_at->toDateString()) {
                throw new InvalidArgumentException('تاريخ نهاية الموسم لازم يكون بعد بدايته.');
            }

            $result = $this->calculate($season, $endsAt);

            $season->forceFill([
                'ended_at' => $endsAt,
                'status' => SeasonStatus::Closed,
                'closed_at' => now(),
                'revenue' => $result['revenue'],
                'cost_of_materials' => $result['cost_of_materials'],
                'room_costs' => $result['room_costs'],
                'cancelled_room_costs' => $result['cancelled_room_costs'],
                'admin_expenses' => $result['admin_expenses'],
                'net_profit' => $result['net_profit'],
                'loss_carried_out' => $result['loss_carried_out'],
                'distributable_profit' => $result['distributable'],
                'cashbox_balance_at_close' => $result['cashbox_balance'],
                'stock_value_at_close' => $result['stock_value'],
                'wip_carried_forward' => $result['wip']['cost'],
            ])->save();

            foreach ($result['partners'] as $row) {
                SeasonPartnerShare::query()->create([
                    'season_id' => $season->id,
                    'partner_id' => $row['partner_id'],
                    'percentage' => $row['percentage'],
                    'share_amount' => $row['share'],
                    'carried_in' => $row['carried_in'],
                    'withdrawn' => $row['withdrawn'],
                    'carried_out' => $row['carried_out'],
                ]);
            }

            // Sealing is a plain update on purpose: it is bookkeeping, not an edit,
            // so it must not flood the activity log with one row per record.
            Room::query()
                ->whereIn('status', [RoomStatus::Completed, RoomStatus::Cancelled])
                ->whereNull('season_id')
                ->update(['season_id' => $season->id]);

            Expense::query()->whereNull('season_id')->update(['season_id' => $season->id]);

            // Costs of rooms still in progress stay open: that is work carried forward (ق-1).
            RoomCost::query()
                ->whereNull('season_id')
                ->whereHas('room', fn ($query) => $query->whereIn('status', [RoomStatus::Completed, RoomStatus::Cancelled]))
                ->update(['season_id' => $season->id]);

            PartnerWithdrawal::query()->whereNull('season_id')->update(['season_id' => $season->id]);

            Season::query()->create([
                'number' => $season->number + 1,
                'started_at' => CarbonImmutable::parse($endsAt)->addDay()->toDateString(),
                'status' => SeasonStatus::Open,
                'loss_carried_in' => $result['loss_carried_out'],
            ]);

            return $season->refresh();
        });
    }

    /**
     * Undoes the last close (ق-4). Only allowed while nothing has happened
     * since the close, so the reopened season is exactly what it was.
     */
    public function reopen(Season $season): void
    {
        DB::transaction(function () use ($season) {
            $season = Season::query()->whereKey($season->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canReopen($season)) {
                throw new RuntimeException('مينفعش تفتح الموسم ده: إما مش آخر موسم مقفول، أو الموسم الجديد اتحرك فيه بيانات.');
            }

            Season::query()->where('status', SeasonStatus::Open)->where('number', '>', $season->number)->delete();

            foreach (['rooms', 'expenses', 'room_costs', 'partner_withdrawals'] as $table) {
                DB::table($table)->where('season_id', $season->id)->update(['season_id' => null]);
            }

            SeasonPartnerShare::query()->where('season_id', $season->id)->delete();

            $season->forceFill([
                'ended_at' => null,
                'status' => SeasonStatus::Open,
                'closed_at' => null,
                'revenue' => null,
                'cost_of_materials' => null,
                'room_costs' => null,
                'cancelled_room_costs' => null,
                'admin_expenses' => null,
                'net_profit' => null,
                'loss_carried_out' => null,
                'distributable_profit' => null,
                'cashbox_balance_at_close' => null,
                'stock_value_at_close' => null,
                'wip_carried_forward' => null,
            ])->save();
        });
    }

    /**
     * Only the latest closed season can be reopened, and only while nothing has
     * been written since it closed.
     */
    public function canReopen(Season $season): bool
    {
        if ($season->status !== SeasonStatus::Closed) {
            return false;
        }

        $latestClosed = Season::query()->where('status', SeasonStatus::Closed)->max('number');

        return $season->number === $latestClosed && ! $this->hasActivitySince($season);
    }

    /**
     * The one answer to "is this record in a closed season?" Every service asks
     * this before changing a record.
     */
    public function isLocked(Model $record): bool
    {
        return match (true) {
            $record instanceof Room, $record instanceof Expense, $record instanceof RoomCost, $record instanceof PartnerWithdrawal => $record->season_id !== null,
            $record instanceof RoomMaterial => $record->room?->season_id !== null,
            default => false,
        };
    }

    /**
     * Every figure a close produces, computed from the open season's rows.
     * Pure read: it is used by both preview() and close().
     *
     * @return array<string, mixed>
     */
    private function calculate(Season $season, string $endsAt): array
    {
        $revenue = $this->profit->revenue();
        $costOfMaterials = $this->profit->costOfMaterials();
        $roomCosts = $this->profit->roomCosts();
        $cancelledRoomCosts = $this->profit->cancelledRoomCosts();
        $adminExpenses = $this->profit->adminExpenses();
        $netProfit = $this->profit->netProfit();

        $lossCarriedIn = (int) $season->getRawOriginal('loss_carried_in');
        $balance = $netProfit - $lossCarriedIn;
        $distributable = max(0, $balance);
        $lossCarriedOut = $balance < 0 ? -$balance : 0;

        $partnerRows = Partner::query()->orderBy('id')->get()->map(function (Partner $partner) use ($distributable) {
            $percentage = (int) $partner->getRawOriginal('percentage');
            $carriedIn = $this->partners->carriedIn($partner);
            $share = PartnerService::shareOf($distributable, $percentage);
            $withdrawn = $this->partners->totalWithdrawn($partner);

            return [
                'partner_id' => $partner->id,
                'name' => $partner->name,
                'percentage' => $percentage,
                'carried_in' => $carriedIn,
                'share' => $share,
                'withdrawn' => $withdrawn,
                'carried_out' => $carriedIn + $share - $withdrawn,
            ];
        })->all();

        $archivable = Room::query()
            ->whereIn('status', [RoomStatus::Completed, RoomStatus::Cancelled])
            ->whereNull('season_id');

        $cashboxBalance = $this->cashbox->balance();

        return [
            'ends_at' => $endsAt,
            'revenue' => $revenue,
            'cost_of_materials' => $costOfMaterials,
            'room_costs' => $roomCosts,
            'cancelled_room_costs' => $cancelledRoomCosts,
            'admin_expenses' => $adminExpenses,
            'net_profit' => $netProfit,
            'loss_carried_in' => $lossCarriedIn,
            'balance' => $balance,
            'distributable' => $distributable,
            'loss_carried_out' => $lossCarriedOut,
            'archived_rooms' => [
                'count' => (clone $archivable)->count(),
                'value' => (int) (clone $archivable)->sum('sale_price'),
            ],
            'wip' => [
                'count' => Room::query()->where('status', RoomStatus::InProgress)->count() + Room::query()->where('status', RoomStatus::Draft)->count(),
                'cost' => $this->profit->workInProgress(),
            ],
            'partners' => $partnerRows,
            'owed_total' => collect($partnerRows)->sum(fn (array $row) => max(0, $row['carried_out'])),
            'cashbox_balance' => $cashboxBalance,
            'stock_value' => $this->inventory->stockValue(),
            'warnings' => [
                'owed_exceeds_cashbox' => collect($partnerRows)->sum(fn (array $row) => max(0, $row['carried_out'])) > $cashboxBalance,
                'loss_carried_out' => $lossCarriedOut > 0,
            ],
        ];
    }

    /**
     * Anything written after this season closed. Reopening is refused then.
     */
    private function hasActivitySince(Season $season): bool
    {
        $since = $season->closed_at;

        if ($since === null) {
            return false;
        }

        foreach ([Room::class, Expense::class, RoomCost::class, PartnerWithdrawal::class, CustomerPayment::class, CashboxTransaction::class, InventoryMovement::class, RoomMaterial::class] as $modelClass) {
            // Strictly after: the close itself and any rows written in the same second are not activity.
            if ($modelClass::query()->where('created_at', '>', $since)->exists()) {
                return true;
            }
        }

        return false;
    }
}
