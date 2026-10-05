<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\PaymentMethod;
use App\Http\Requests\SetOpeningBalanceRequest;
use App\Models\CashboxTransaction;
use App\Models\CustomerPayment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\PartnerWithdrawal;
use App\Models\RoomCost;
use App\Services\CashboxService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

class CashboxController extends Controller
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function index(Request $request): View
    {
        $filters = [
            'kind' => trim($request->string('kind')->toString()),
            'payment_method' => trim($request->string('payment_method')->toString()),
            'from' => trim($request->string('from')->toString()),
            'to' => trim($request->string('to')->toString()),
        ];

        $openingBalance = CashboxTransaction::query()
            ->where('kind', CashboxTransactionKind::OpeningBalance)
            ->first();

        $summary = $this->cashbox->summary();

        return view('cashbox.index', [
            'incoming' => $this->page(CashboxTransactionType::In, 'in_page', $filters),
            'outgoing' => $this->page(CashboxTransactionType::Out, 'out_page', $filters),
            'filters' => $filters,
            'kinds' => CashboxTransactionKind::cases(),
            'balance' => $summary['balance'],
            'debtsOutstanding' => (int) Debt::query()->where('is_paid', false)->sum('amount'),
            'totalIn' => $summary['total_in'],
            'totalOut' => $summary['total_out'],
            'breakdown' => $this->cashbox->breakdownByMethod(),
            'incomingMonthlyTotals' => $this->monthlyTotals(CashboxTransactionType::In, $filters),
            'outgoingMonthlyTotals' => $this->monthlyTotals(CashboxTransactionType::Out, $filters),
            'methods' => PaymentMethod::cases(),
            'openingBalance' => $openingBalance,
        ]);
    }

    /**
     * One side of the cashbox. Each table paginates under its own page
     * parameter — a shared one would move both tables at once, and
     * withQueryString() keeps the other table's page while this one moves.
     *
     * The morphWith is what makes CashboxTransaction::detailedLabel() cheap:
     * without it every row would fetch its own source and that source's own
     * relation, which is 50+ queries on a full page.
     *
     * @return LengthAwarePaginator<int, CashboxTransaction>
     */
    private function page(CashboxTransactionType $type, string $pageName, array $filters): LengthAwarePaginator
    {
        return $this->applyFilters(CashboxTransaction::query()->where('type', $type), $filters)
            ->with(['source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Expense::class => ['category'],
                CustomerPayment::class => ['room.customer'],
                InventoryMovement::class => ['material'],
                PartnerWithdrawal::class => ['partner'],
                RoomCost::class => ['room'],
            ])])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25, ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * Month totals over ALL incoming rows, not just the page on screen — the
     * separator row must show the true total even when a month spans pages.
     * Same shape as ExpenseController::monthlyTotals().
     *
     * @return Collection<string, int>
     */
    /**
     * Totals per month over every row the filters match, not just one page.
     *
     * @param  array<string, string>  $filters
     */
    private function monthlyTotals(CashboxTransactionType $type, array $filters): Collection
    {
        return $this->applyFilters(CashboxTransaction::query()->where('type', $type), $filters)
            ->selectRaw("strftime('%Y-%m', occurred_at) as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['kind'] !== '', fn (Builder $q) => $q->where('kind', $filters['kind']))
            ->when($filters['payment_method'] !== '', fn (Builder $q) => $q->where('payment_method', $filters['payment_method']))
            ->when($filters['from'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $filters['to']));
    }

    public function storeOpeningBalance(SetOpeningBalanceRequest $request): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $this->cashbox->setOpeningBalance($amount, $request->date('occurred_at'), PaymentMethod::from($request->string('payment_method')->toString()));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة الرصيد الافتتاحي غير صالحة.']);
        }

        return redirect()->route('cashbox.index')->with('success', 'تم تحديث الرصيد الافتتاحي.');
    }
}
