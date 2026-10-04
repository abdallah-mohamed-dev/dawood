<?php

namespace App\Services;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Models\ActivityLog;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

class ActivityLogService
{
    /**
     * Bookkeeping that is never shown in the log. updated_at changes on every
     * save so it is pure noise; password would leak the hash into the log.
     */
    private const HIDDEN_FIELDS = ['updated_at', 'password', 'remember_token'];

    /**
     * @var list<class-string<Model>>
     */
    public const TRACKED_MODELS = [
        Customer::class,
        Room::class,
        RoomMaterial::class,
        RoomCost::class,
        CustomerPayment::class,
        Material::class,
        InventoryBatch::class,
        InventoryMovement::class,
        Expense::class,
        ExpenseCategory::class,
        Partner::class,
        PartnerWithdrawal::class,
        CashboxTransaction::class,
        User::class,
        Debt::class,
        MaterialType::class,
    ];

    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'إضافة',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            default => $event,
        };
    }

    /**
     * @return array<class-string<Model>, string>
     */
    public static function subjectTypes(): array
    {
        $types = [];

        foreach (self::TRACKED_MODELS as $modelClass) {
            $types[$modelClass] = (new $modelClass)->activityTypeLabel();
        }

        return $types;
    }

    public static function fieldLabel(string $field): string
    {
        return Lang::has("fields.{$field}") ? __("fields.{$field}") : $field;
    }

    /**
     * "اسم الحقل: قديم ← جديد" لكل حقل اتغيّر، مفصولة بسطر جديد.
     */
    public static function detailsText(ActivityLog $log): string
    {
        return collect($log->changes ?? [])
            ->map(fn (array $change, string $field) => self::fieldLabel($field).': '.$change['old'].' ← '.$change['new'])
            ->implode("\n");
    }

    public function record(Model $model, string $event): void
    {
        $changes = $event === 'updated' ? $this->changesFor($model) : null;

        // A save that only touched hidden fields is not a real change.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'subject_label' => $model->activityLabel(),
            'event' => $event,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, array{old: string, new: string}>
     */
    private function changesFor(Model $model): array
    {
        $changes = [];

        foreach (array_keys($model->getChanges()) as $key) {
            if (in_array($key, self::HIDDEN_FIELDS, true)) {
                continue;
            }

            $changes[$key] = [
                // Raw on both sides: getOriginal() would run the casts and
                // then format() would apply them a second time.
                'old' => $this->format($model, $key, $model->getRawOriginal($key)),
                'new' => $this->format($model, $key, $model->getAttributes()[$key] ?? null),
            ];
        }

        return $changes;
    }

    /**
     * Turns a raw stored value into text a person can read: money in pounds,
     * enums as their Arabic label, and so on.
     */
    private function format(Model $model, string $key, mixed $raw): string
    {
        if ($raw === null) {
            return '—';
        }

        $cast = $model->getCasts()[$key] ?? null;

        if ($cast === MoneyCast::class) {
            return MoneyCast::toDisplayString((int) $raw);
        }

        if ($cast === QuantityCast::class) {
            return QuantityCast::toDisplayString((int) $raw);
        }

        // A value assigned in the same request is still the enum instance;
        // a value read back from the database is the raw backing string.
        if (is_string($cast) && is_subclass_of($cast, BackedEnum::class)) {
            $enum = $raw instanceof BackedEnum ? $raw : $cast::tryFrom($raw);

            return $enum?->label() ?? (string) $raw;
        }

        if ($cast === 'date') {
            return Carbon::parse($raw)->format('Y-m-d');
        }

        // Read back from SQLite a boolean is 0 or 1, so the cast decides, not the PHP type.
        if (in_array($cast, ['boolean', 'bool'], true)) {
            return $raw ? 'نعم' : 'لا';
        }

        return (string) $raw;
    }
}
