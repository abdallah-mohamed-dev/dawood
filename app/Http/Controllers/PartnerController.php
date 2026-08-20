<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Services\PartnerService;
use App\Services\ProfitService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerService $partners,
        private readonly ProfitService $profit,
    ) {}

    public function index(): View
    {
        $netProfit = $this->profit->netProfit();

        $partners = Partner::query()->orderBy('name')->paginate(25);

        $rows = $partners->getCollection()->map(fn (Partner $partner) => [
            'partner' => $partner,
            'share' => $this->partners->share($partner),
            'withdrawn' => $this->partners->totalWithdrawn($partner),
            'remaining' => $this->partners->remaining($partner),
        ]);

        return view('partners.index', [
            'partners' => $partners,
            'rows' => $rows,
            'netProfit' => $netProfit,
            'hasOverWithdrawal' => $rows->contains(fn (array $row) => $row['remaining'] < 0),
        ]);
    }

    public function create(): View
    {
        return view('partners.create');
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        try {
            $percentage = MoneyCast::toScaledInt($request->string('percentage')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['percentage' => 'قيمة النسبة غير صالحة.']);
        }

        Partner::query()->create([
            'name' => $request->string('name')->toString(),
            'percentage' => $percentage,
        ]);

        return redirect()->route('partners.index')->with('success', 'تم إضافة الشريك.');
    }

    public function show(Partner $partner): View
    {
        $withdrawals = $partner->withdrawals()->latest('occurred_at')->latest('id')->get();

        return view('partners.show', [
            'partner' => $partner,
            'share' => $this->partners->share($partner),
            'withdrawn' => $this->partners->totalWithdrawn($partner),
            'remaining' => $this->partners->remaining($partner),
            'netProfit' => $this->profit->netProfit(),
            'withdrawals' => $withdrawals,
            'percentageDisplay' => number_format($partner->percentage / 100, 2),
        ]);
    }

    public function edit(Partner $partner): View
    {
        return view('partners.edit', ['partner' => $partner]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        try {
            $percentage = MoneyCast::toScaledInt($request->string('percentage')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['percentage' => 'قيمة النسبة غير صالحة.']);
        }

        $partner->update([
            'name' => $request->string('name')->toString(),
            'percentage' => $percentage,
        ]);

        return redirect()->route('partners.show', $partner)->with('success', 'تم تعديل بيانات الشريك.');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        if ($partner->withdrawals()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذا الشريك لأن لديه سحوبات مرتبطة به. احذف السحوبات أولًا.');
        }

        try {
            $partner->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذا الشريك لأن لديه سحوبات مرتبطة به. احذف السحوبات أولًا.');
        }

        return redirect()->route('partners.index')->with('success', 'تم حذف الشريك.');
    }

    public function storeWithdrawal(StoreWithdrawalRequest $request, Partner $partner): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $note = $request->filled('note') ? $request->string('note')->toString() : null;
            $this->partners->withdraw($partner, $amount, $request->date('occurred_at'), $note);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        return back()->with('success', 'تم تسجيل السحب.');
    }

    public function destroyWithdrawal(Partner $partner, PartnerWithdrawal $withdrawal): RedirectResponse
    {
        abort_if($withdrawal->partner_id !== $partner->id, 404);

        $this->partners->deleteWithdrawal($withdrawal);

        return back()->with('success', 'تم حذف السحب.');
    }
}
