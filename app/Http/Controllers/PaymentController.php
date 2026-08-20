<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Exceptions\PaymentExceedsRemainingException;
use App\Exceptions\RoomCancelledException;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\CustomerPayment;
use App\Models\Room;
use App\Services\CustomerPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly CustomerPaymentService $payments) {}

    public function index(): View
    {
        $payments = CustomerPayment::query()
            ->with('room.customer')
            ->latest('paid_at')
            ->latest('id')
            ->paginate(25);

        return view('payments.index', ['payments' => $payments]);
    }

    public function store(StorePaymentRequest $request, Room $room): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $note = $request->filled('note') ? $request->string('note')->toString() : null;
            $this->payments->create($room, $amount, $request->date('paid_at'), $note);
        } catch (PaymentExceedsRemainingException $e) {
            return back()->withInput()->withErrors([
                'amount' => 'المبلغ أكبر من المتبقي. المتبقي الفعلي: '.MoneyCast::toDisplayString($e->remaining).' ج.م.',
            ]);
        } catch (RoomCancelledException) {
            return back()->withInput()->with('error', 'لا يمكن تسجيل دفعة لغرفة ملغاة.');
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        }

        return back()->with('success', 'تم تسجيل الدفعة.');
    }

    public function edit(CustomerPayment $payment): View
    {
        return view('payments.edit', ['payment' => $payment]);
    }

    public function update(UpdatePaymentRequest $request, CustomerPayment $payment): RedirectResponse
    {
        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
            $this->payments->update($payment, $amount);
        } catch (PaymentExceedsRemainingException $e) {
            return back()->withInput()->withErrors([
                'amount' => 'المبلغ أكبر من المتبقي. المتبقي الفعلي: '.MoneyCast::toDisplayString($e->remaining).' ج.م.',
            ]);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.']);
        } catch (RuntimeException) {
            // Defensive only — CustomerPaymentService::update() calls
            // CashboxService::updateFor(), which throws a plain
            // RuntimeException if this payment's cashbox row is missing
            // (should be unreachable via normal create/delete flows).
            return back()->withInput()->withErrors(['amount' => 'حدث خطأ غير متوقع أثناء تحديث الدفعة.']);
        }

        return redirect()->route('rooms.show', $payment->room)->with('success', 'تم تعديل الدفعة.');
    }

    public function destroy(CustomerPayment $payment): RedirectResponse
    {
        $room = $payment->room;

        $this->payments->delete($payment);

        return redirect()->route('rooms.show', $room)->with('success', 'تم حذف الدفعة.');
    }
}
