<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Enums\CashboxTransactionKind;
use App\Enums\CashboxTransactionType;
use App\Enums\PaymentMethod;
use App\Http\Requests\SetOpeningBalanceRequest;
use App\Models\CashboxTransaction;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\InventoryBatch;
use App\Models\PartnerWithdrawal;
use App\Models\RoomCost;
use App\Services\CashboxService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class CashboxController extends Controller
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function index(): View
    {
        $openingBalance = CashboxTransaction::query()
            ->where('kind', CashboxTransactionKind::OpeningBalance)
            ->first();

        $summary = $this->cashbox->summary();

        return view('cashbox.index', [
            'incoming' => $this->page(CashboxTransactionType::In, 'in_page'),
            'outgoing' => $this->page(CashboxTransactionType::Out, 'out_page'),
            'balance' => $summary['balance'],
            'totalIn' => $summary['total_in'],
            'totalOut' => $summary['total_out'],
            'breakdown' => $this->cashbox->breakdownByMethod(),
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
    private function page(CashboxTransactionType $type, string $pageName): LengthAwarePaginator
    {
        return CashboxTransaction::query()
            ->where('type', $type)
            ->with(['source' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Expense::class => ['category'],
                CustomerPayment::class => ['room.customer'],
                InventoryBatch::class => ['material'],
                PartnerWithdrawal::class => ['partner'],
                RoomCost::class => ['room'],
            ])])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25, ['*'], $pageName)
            ->withQueryString();
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
