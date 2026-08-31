<?php

namespace App\Http\Controllers;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\RoomStatus;
use App\Exceptions\ExceedsRequiredQuantityException;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\DestroyRoomRequest;
use App\Http\Requests\IssueRoomMaterialRequest;
use App\Http\Requests\StoreRoomMaterialRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Room;
use App\Models\RoomMaterial;
use App\Services\InventoryService;
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
        private readonly InventoryService $inventory,
    ) {}

    public function create(): View
    {
        return view('rooms.create', ['customers' => Customer::query()->orderBy('name')->get()]);
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        try {
            $salePrice = MoneyCast::toScaledInt($request->string('sale_price')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['sale_price' => 'قيمة سعر البيع غير صالحة.']);
        }

        $room = Room::query()->create([
            'customer_id' => $request->integer('customer_id'),
            'room_type' => $request->string('room_type')->toString(),
            'sale_price' => $salePrice,
            'status' => RoomStatus::Draft,
        ]);

        return redirect()->route('rooms.show', $room)->with('success', 'تم إنشاء الغرفة.');
    }

    public function show(Room $room): View
    {
        $room->load(['customer', 'roomMaterials.material', 'customerPayments' => fn ($query) => $query->latest('paid_at')->latest('id')]);

        return view('rooms.show', [
            'room' => $room,
            'stockByMaterial' => $this->inventory->stockByMaterialIds($room->roomMaterials->pluck('material_id')->all()),
            'availableMaterials' => Material::query()->orderBy('name')->get(),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function destroy(DestroyRoomRequest $request, Room $room): RedirectResponse
    {
        $customerId = $room->customer_id;

        $this->roomService->deleteRoom($room, $request->boolean('return_materials'));

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

        try {
            $quantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
        } catch (InvalidArgumentException) {
            return back()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.']);
        }

        if ($quantity <= 0) {
            return back()->withErrors(['quantity' => 'يجب أن تكون الكمية أكبر من صفر.']);
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
}
