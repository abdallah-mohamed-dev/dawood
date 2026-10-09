<?php

namespace App\Http\Controllers\Exports;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Services\InventoryService;
use App\Services\PartnerService;
use App\Services\ProfitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Readable CSV exports for opening in Excel. Read-only: every number comes
 * from an existing model method or service, never from a new calculation.
 * Rows are streamed with lazyById() plus an explicit eager-load list, so the
 * query count does not grow with the number of rows (see CsvExportTest).
 */
class CsvExportController extends Controller
{
    public function __construct(
        private readonly ProfitService $profit,
        private readonly PartnerService $partners,
        private readonly InventoryService $inventory,
    ) {}

    public function customers(): StreamedResponse
    {
        $rows = Customer::query()->with(['rooms.customerPayments'])->lazyById(500)->map(function (Customer $customer) {
            $rooms = $customer->rooms;

            return [
                $customer->name,
                $customer->phone ?? '',
                (string) $rooms->count(),
                $this->money($rooms->sum(fn (Room $room) => $room->getRawOriginal('sale_price'))),
                $this->money($rooms->sum(fn (Room $room) => $room->paidAmount())),
                $this->money($rooms->sum(fn (Room $room) => $room->remainingAmount())),
                $this->date($customer->created_at),
            ];
        });

        return $this->stream('customers', [
            'الاسم', 'التليفون', 'عدد الغرف', 'إجمالي سعر البيع', 'المدفوع', 'المتبقي', 'تاريخ الإضافة',
        ], $rows);
    }

    public function rooms(Request $request): StreamedResponse
    {
        $rows = $this->applySeasonFilter(Room::query(), $request)
            ->with(['customer', 'season', 'roomMaterials', 'roomCosts', 'customerPayments'])
            ->lazyById(500)
            ->map(function (Room $room) {
                $figures = $this->profit->forRoom($room);

                return [
                    $room->room_type ?? '',
                    $room->customer?->name ?? '',
                    $room->customer?->phone ?? '',
                    $room->status?->label() ?? '',
                    $room->season?->name ?? '',
                    $this->money($figures['sale_price']),
                    $this->money($figures['materials']),
                    $this->money($figures['labor']),
                    $this->money($figures['other']),
                    $this->money($figures['total_cost']),
                    $this->money($figures['profit']),
                    $this->money($room->paidAmount()),
                    $this->money($room->remainingAmount()),
                    $this->date($room->created_at),
                    $this->date($room->started_at),
                    $this->date($room->completed_at),
                ];
            });

        return $this->stream('rooms', [
            'الغرفة', 'العميل', 'تليفون العميل', 'الحالة', 'الموسم', 'سعر البيع', 'تكلفة الخامات',
            'المصنعية', 'مصروفات أخرى', 'إجمالي التكلفة', 'الربح', 'المدفوع', 'المتبقي',
            'تاريخ الإنشاء', 'تاريخ بدء التنفيذ', 'تاريخ الاكتمال',
        ], $rows);
    }

    public function materials(): StreamedResponse
    {
        $rows = Material::query()->with(['materialType'])->lazyById(500)->map(fn (Material $material) => [
            $material->name,
            $material->materialType?->name ?? '',
            $material->unit ?? '',
            QuantityCast::toDecimalString($material->getRawOriginal('quantity')),
            $this->money($material->getRawOriginal('unit_price')),
            // Priced by the service, never multiplied here (CLAUDE.md rule 2).
            $this->money($this->inventory->valueOf($material, (int) $material->getRawOriginal('quantity'))),
        ]);

        return $this->stream('materials', [
            'الخامة', 'النوع', 'الوحدة', 'الكمية المتاحة', 'سعر الوحدة', 'قيمة المخزون',
        ], $rows);
    }

    /**
     * المشتريات = حركات الوارد (InventoryMovementType::In) — addStock() هو المصدر الوحيد ليها.
     * الحركة بتخزن الإجمالي بس، فسعر الوحدة بيترجع من `InventoryService::unitPriceOf()`
     * (عكس `cost()`، وفي نفس الملف) — الملف المصدَّر لازم يبان فيه كل البيانات.
     */
    public function purchases(): StreamedResponse
    {
        $rows = InventoryMovement::query()
            ->where('type', InventoryMovementType::In)
            ->with(['material'])
            ->lazyById(500)
            ->map(fn (InventoryMovement $movement) => [
                $this->date($movement->occurred_at),
                $movement->material?->name ?? '',
                QuantityCast::toDecimalString($movement->getRawOriginal('quantity')),
                $movement->material?->unit ?? '',
                $this->money($this->inventory->unitPriceOf(
                    (int) $movement->getRawOriginal('quantity'),
                    (int) $movement->getRawOriginal('cost'),
                )),
                $this->money($movement->getRawOriginal('cost')),
            ]);

        return $this->stream('purchases', [
            'التاريخ', 'الخامة', 'الكمية', 'الوحدة', 'سعر الوحدة', 'الإجمالي',
        ], $rows);
    }

    public function payments(Request $request): StreamedResponse
    {
        $rows = $this->applySeasonFilter(CustomerPayment::query(), $request, viaRoom: true)
            ->with(['room.customer', 'cashboxTransaction'])
            ->lazyById(500, column: 'customer_payments.id')
            ->map(fn (CustomerPayment $payment) => [
                $this->date($payment->paid_at),
                $payment->room?->customer?->name ?? '',
                $payment->room?->room_type ?? '',
                $this->money($payment->getRawOriginal('amount')),
                $payment->cashboxTransaction?->payment_method?->label() ?? '',
                $payment->receipt_number === null ? '' : $payment->formattedReceiptNumber(),
                $payment->note ?? '',
            ]);

        return $this->stream('payments', [
            'التاريخ', 'العميل', 'الغرفة', 'المبلغ', 'طريقة الدفع', 'رقم الإيصال', 'ملاحظة',
        ], $rows);
    }

    public function expenses(Request $request): StreamedResponse
    {
        $rows = $this->applySeasonFilter(Expense::query(), $request)
            ->with(['category', 'cashboxTransaction'])
            ->lazyById(500)
            ->map(fn (Expense $expense) => [
                $this->date($expense->occurred_at),
                $expense->category?->name ?? '',
                $this->money($expense->getRawOriginal('amount')),
                $expense->description ?? '',
                $expense->cashboxTransaction?->payment_method?->label() ?? '',
            ]);

        return $this->stream('expenses', [
            'التاريخ', 'البند', 'المبلغ', 'الوصف', 'طريقة الدفع',
        ], $rows);
    }

    /**
     * «البند» = نوع الحركة، «البيان» = اسم الحركة الفعلي (زي صفحة الخزنة).
     * الـmorphWith نفسه اللي في CashboxController عشان detailedLabel() ماتعملش N+1.
     */
    public function cashbox(): StreamedResponse
    {
        $rows = CashboxTransaction::query()
            ->with(['source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Expense::class => ['category'],
                CustomerPayment::class => ['room.customer'],
                InventoryMovement::class => ['material'],
                PartnerWithdrawal::class => ['partner'],
                RoomCost::class => ['room'],
            ])])
            ->lazyById(500)
            ->map(fn (CashboxTransaction $transaction) => [
                $this->date($transaction->occurred_at),
                $transaction->type->label(),
                $transaction->kind->label(),
                $transaction->detailedLabel(),
                $this->money($transaction->getRawOriginal('amount')),
            ]);

        return $this->stream('cashbox', [
            'التاريخ', 'الاتجاه', 'البند', 'البيان', 'المبلغ',
        ], $rows);
    }

    /**
     * المسحوب بيتبع نفس فلتر الموسم بتاع باقي الملفات: من غير فلتر = كل المواسم،
     * `open` = الموسم المفتوح (نفس رقم صفحة الشريك)، ورقم = موسم مقفول بعينه.
     */
    public function partners(Request $request): StreamedResponse
    {
        $season = $this->seasonFilterValue($request);

        $rows = Partner::query()->lazyById(500)->map(fn (Partner $partner) => [
            $partner->name,
            $partner->percentage === null ? '' : number_format($partner->percentage / 100, 2).'%',
            $partner->email ?? '',
            $this->money($this->partners->totalWithdrawnForSeason($partner, $season)),
            $this->date($partner->created_at),
        ]);

        return $this->stream('partners', [
            'الشريك', 'النسبة', 'الإيميل', 'المسحوب', 'تاريخ الإضافة',
        ], $rows);
    }

    public function withdrawals(Request $request): StreamedResponse
    {
        $rows = $this->applySeasonFilter(PartnerWithdrawal::query(), $request)
            ->with(['partner'])
            ->lazyById(500)
            ->map(fn (PartnerWithdrawal $withdrawal) => [
                $this->date($withdrawal->occurred_at),
                $withdrawal->partner?->name ?? '',
                $this->money($withdrawal->getRawOriginal('amount')),
                $withdrawal->note ?? '',
            ]);

        return $this->stream('withdrawals', [
            'التاريخ', 'الشريك', 'المبلغ', 'ملاحظة',
        ], $rows);
    }

    /**
     * The `season` query parameter as the services want it: 'open' for the
     * open season, an int for a sealed one, null for no filter at all.
     */
    private function seasonFilterValue(Request $request): int|string|null
    {
        $season = trim($request->string('season')->toString());

        return match (true) {
            $season === '' || $season === 'all' => null,
            $season === 'open' => 'open',
            default => (int) $season,
        };
    }

    /**
     * فلتر الموسم اختياري — بدونه التصدير شامل لكل البيانات زي الأصل (specs/020.1).
     * القيم: `open` (الموسم المفتوح، season_id فاضي) · `all` أو من غيره (بلا فلتر) · رقم موسم (season_id = الرقم).
     * `rooms` و`expenses` و`partner_withdrawals` عليهم عمود `season_id` مباشر. المدفوعات
     * مالهاش عمود موسم (قرار ق-5 في specs/012 — مفتوحة عبر المواسم)، فالفلتر بيعدي من خلال الغرفة.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applySeasonFilter(Builder $query, Request $request, bool $viaRoom = false): Builder
    {
        $season = trim($request->string('season')->toString());

        if ($season === '' || $season === 'all') {
            return $query;
        }

        $column = $viaRoom ? 'rooms.season_id' : 'season_id';
        $condition = fn (Builder $q) => $season === 'open'
            ? $q->whereNull($column)
            : $q->where($column, (int) $season);

        return $viaRoom
            ? $condition($query->join('rooms', 'rooms.id', '=', 'customer_payments.room_id')->select('customer_payments.*'))
            : $condition($query);
    }

    /**
     * @param  list<string>  $headers  عناوين الأعمدة بالعربي
     * @param  iterable<array<int, string>>  $rows
     */
    private function stream(string $name, array $headers, iterable $rows): StreamedResponse
    {
        $filename = 'dawood-'.$name.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM — بدونه Excel بيكركب العربي
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stored piastres → "85000.00". Null (no value) → empty cell. */
    private function money(?int $piastres): string
    {
        return $piastres === null ? '' : MoneyCast::toDecimalString($piastres);
    }

    private function date(?Carbon $date): string
    {
        return $date?->format('Y-m-d') ?? '';
    }
}
