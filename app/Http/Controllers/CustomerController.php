<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = Customer::query()
            ->withCount('rooms')
            ->orderBy('name')
            ->paginate(25);

        return view('customers.index', ['customers' => $customers]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'تم إضافة العميل.');
    }

    public function show(Customer $customer): View
    {
        $rooms = $customer->rooms()->with('customerPayments')->latest('id')->get();

        return view('customers.show', ['customer' => $customer, 'rooms' => $rooms]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', 'تم تعديل بيانات العميل.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->rooms()->exists()) {
            return back()->with('error', 'لا يمكن حذف هذا العميل لأن لديه غرفًا مرتبطة به. احذف الغرف أولًا.');
        }

        try {
            $customer->delete();
        } catch (QueryException) {
            return back()->with('error', 'لا يمكن حذف هذا العميل لأن لديه غرفًا مرتبطة به. احذف الغرف أولًا.');
        }

        return redirect()->route('customers.index')->with('success', 'تم حذف العميل.');
    }
}
