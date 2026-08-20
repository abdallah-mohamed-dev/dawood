<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Enums\CashboxTransactionKind;
use App\Http\Requests\SetOpeningBalanceRequest;
use App\Models\CashboxTransaction;
use App\Services\CashboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class CashboxController extends Controller
{
    public function __construct(private readonly CashboxService $cashbox) {}

    public function index(): View
    {
        $transactions = CashboxTransaction::query()
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25);

        $openingBalance = CashboxTransaction::query()
            ->where('kind', CashboxTransactionKind::OpeningBalance)
            ->first();

        $summary = $this->cashbox->summary();

        return view('cashbox.index', [
            'transactions' => $transactions,
            'balance' => $summary['balance'],
            'totalIn' => $summary['total_in'],
            'totalOut' => $summary['total_out'],
            'openingBalance' => $openingBalance,
        ]);
    }

    public function storeOpeningBalance(SetOpeningBalanceRequest $request): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $this->cashbox->setOpeningBalance($amount, $request->date('occurred_at'));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة الرصيد الافتتاحي غير صالحة.']);
        }

        return redirect()->route('cashbox.index')->with('success', 'تم تحديث الرصيد الافتتاحي.');
    }
}
