<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\PaymentMethod;
use App\Enums\RoomCostType;
use App\Enums\RoomStatus;
use App\Exceptions\ExceedsRequiredQuantityException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\RoomHasCostsException;
use App\Http\Requests\DestroyRoomRequest;
use App\Http\Requests\IssueRoomMaterialRequest;
use App\Http\Requests\StoreRoomCostRequest;
use App\Http\Requests\StoreRoomMaterialRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomMaterialService $roomMaterialService,
        private readonly RoomService $roomService,
        private readonly RoomCostService $roomCostService,
        private readonly InventoryService $inventory,
        private readonly ProfitService $profit,
    ) {}

    public function store(StoreRoomRequest $request, Customer $customer): RedirectResponse
    {
        try {
            $salePrice = MoneyCast::toScaledInt($request->string('sale_price')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['sale_price' => 'قيمة سعر البيع غير صالحة.']);
        }

        $room = Room::query()->create([
            'customer_id' => $customer->id,
            'room_type' => $request->string('room_type')->toString(),
            'sale_price' => $salePrice,
            'status' => RoomStatus::Draft,
        ]);

        return redirect()->route('rooms.show', $room)->with('success', 'تم إنشاء الغرفة.');
    }

    public function show(Room $room): View
    {
        $room->load([
            'customer',
            'roomMaterials.material',
            'customerPayments' => fn ($query) => $query->latest('paid_at')->latest('id'),
            'roomCosts' => fn ($query) => $query->latest('occurred_at')->latest('id'),
        ]);

        return view('rooms.show', [
            'room' => $room,
            'profit' => $this->profit->forRoom($room),
            'stockByMaterial' => $this->inventory->stockByMaterialIds($room->roomMaterials->pluck('material_id')->all()),
            'availableMaterials' => Material::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function destroy(DestroyRoomRequest $request, Room $room): RedirectResponse
    {
        $customerId = $room->customer_id;

        try {
            $this->roomService->deleteRoom($room, $request->boolean('return_materials'));
        } catch (RoomHasCostsException) {
            return back()->with('error', 'لا يمكن حذف الغرفة لأنها تحتوي على دفعات مصنعية أو مصروفات إضافية. احذف هذه البنود أولًا ثم احذف الغرفة.');
        }

        return redirect()->route('customers.show', $customerId)->with('success', 'تم حذف الغرفة.');
    }

    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(RoomStatus::class)],
        ]);

        $room->update(['status' => $validated['status']]);

        return back()->with('success', 'تم تحديث حالة الغرفة.');
    }

    public function storeMaterial(StoreRoomMaterialRequest $request, Room $room): RedirectResponse
    {
        $material = Material::query()->findOrFail($request->integer('material_id'));

        try {
            $quantity = QuantityCast::toScaledInt($request->string('required_quantity')->toString());
            $this->roomMaterialService->addRequirement($room, $material, $quantity);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['required_quantity' => 'قيمة الكمية غير صالحة.']);
        } catch (QueryException) {
            // Defense in depth alongside StoreRoomMaterialRequest's unique
            // check — closes the check-then-insert race on a double submit.
            return back()->withInput()->withErrors(['material_id' => 'هذه المادة مضافة بالفعل لهذه الغرفة.']);
        }

        return back()->with('success', 'تمت إضافة الاحتياج.');
    }

    public function issueMaterial(IssueRoomMaterialRequest $request, Room $room, RoomMaterial $roomMaterial): RedirectResponse
    {
        abort_if($roomMaterial->room_id !== $room->id, 404);

        // Same per-row bag the Form Request uses — see IssueRoomMaterialRequest.
        $bag = 'issue_'.$roomMaterial->id;

        try {
            $quantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.'], $bag);
        }

        if ($quantity <= 0) {
            return back()->withInput()->withErrors(['quantity' => 'يجب أن تكون الكمية أكبر من صفر.'], $bag);
        }

        try {
            $this->roomMaterialService->issue($roomMaterial, $quantity, now()->toDateString());
        } catch (InsufficientStockException) {
            return back()->with('error', 'الكمية المتاحة في المخزون غير كافية لصرف هذه الكمية.');
        } catch (ExceedsRequiredQuantityException) {
            return back()->with('error', 'لا يمكن صرف كمية أكبر من المطلوب.');
        }

        return back()->with('success', 'تم صرف الكمية.');
    }

    public function destroyMaterial(Room $room, RoomMaterial $roomMaterial): RedirectResponse
    {
        abort_if($roomMaterial->room_id !== $room->id, 404);

        try {
            $this->roomMaterialService->removeRequirement($roomMaterial);
        } catch (InvalidArgumentException) {
            return back()->with('error', 'لا يمكن حذف احتياج تم الصرف منه بالفعل.');
        }

        return back()->with('success', 'تم حذف الاحتياج.');
    }

    public function storeCost(StoreRoomCostRequest $request, Room $room): RedirectResponse
    {
        $type = RoomCostType::from($request->string('type')->toString());
        $bag = 'roomCost_'.$type->value;

        try {
            $amount = MoneyCast::toScaledInt($request->string('amount')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => 'قيمة المبلغ غير صالحة.'], $bag);
        }

        if ($amount <= 0) {
            return back()->withInput()->withErrors(['amount' => 'يجب أن يكون المبلغ أكبر من صفر.'], $bag);
        }

        $this->roomCostService->create(
            $room,
            $type,
            $amount,
            $request->date('occurred_at'),
            $request->string('description')->trim()->toString() ?: null,
            PaymentMethod::from($request->string('payment_method')->toString()),
        );

        return back()->with('success', $type === RoomCostType::Labor ? 'تمت إضافة دفعة المصنعية.' : 'تمت إضافة المصروف.');
    }

    public function destroyCost(Room $room, RoomCost $cost): RedirectResponse
    {
        abort_if($cost->room_id !== $room->id, 404);

        $this->roomCostService->delete($cost);

        return back()->with('success', 'تم الحذف.');
    }
}
