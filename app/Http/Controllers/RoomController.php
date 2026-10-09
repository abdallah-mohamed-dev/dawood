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
use App\Exceptions\RoomLockedException;
use App\Http\Requests\DestroyRoomRequest;
use App\Http\Requests\IssueRoomMaterialRequest;
use App\Http\Requests\SaveRoomPricingRequest;
use App\Http\Requests\StoreRoomCostRequest;
use App\Http\Requests\StoreRoomMaterialRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomMaterialRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Services\InventoryService;
use App\Services\ProfitService;
use App\Services\RoomCostService;
use App\Services\RoomMaterialService;
use App\Services\RoomPricingService;
use App\Services\RoomService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        private readonly RoomPricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => trim($request->string('status')->toString()),
            'customer_id' => $request->integer('customer_id'),
            'from' => trim($request->string('from')->toString()),
            'to' => trim($request->string('to')->toString()),
            // 'open' by default: sealed rooms are history, shown only when asked for.
            'season' => trim($request->string('season', 'open')->toString()),
        ];

        $matching = Room::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('room_type', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$filters['q'].'%'))))
            ->when(RoomStatus::tryFrom($filters['status']) !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['customer_id'] > 0, fn ($query) => $query->where('customer_id', $filters['customer_id']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['season'] === 'open', fn ($query) => $query->whereNull('season_id'))
            ->when(ctype_digit($filters['season']), fn ($query) => $query->where('season_id', (int) $filters['season']));

        // Paid total comes from one grouped subquery, not paidAmount() per row —
        // that would be one extra query for every room on the page.
        $rooms = (clone $matching)
            ->with('customer')
            ->withSum('customerPayments as paid_total', 'amount')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // One row per status, over the full filtered set (not just this page),
        // for the two summary charts below the filters.
        $byStatus = (clone $matching)
            ->selectRaw('status, COUNT(*) as cnt, SUM(sale_price) as sale_total')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status->value);

        $paidByStatus = (clone $matching)
            ->withSum('customerPayments as paid_total', 'amount')
            ->get(['id', 'status'])
            ->groupBy(fn ($room) => $room->status->value)
            ->map(fn ($rooms) => (int) $rooms->sum('paid_total'));

        return view('rooms.index', [
            'rooms' => $rooms,
            'filters' => $filters,
            'filtersActive' => $filters['q'] !== '' || $filters['status'] !== '' || $filters['customer_id'] > 0 || $filters['from'] !== '' || $filters['to'] !== '' || $filters['season'] !== 'open',
            'seasons' => Season::query()->orderByDesc('number')->get(),
            'statuses' => RoomStatus::cases(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'byStatus' => $byStatus,
            'paidByStatus' => $paidByStatus,
        ]);
    }

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

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        if ($room->status === RoomStatus::Completed) {
            return back()->with('error', 'الغرفة مكتملة، ما ينفعش تتعدل بياناتها الأساسية.');
        }

        try {
            $salePrice = MoneyCast::toScaledInt($request->string('sale_price')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['sale_price' => 'قيمة سعر البيع غير صالحة.']);
        }

        $room->update([
            'room_type' => $request->string('room_type')->toString(),
            'sale_price' => $salePrice,
        ]);

        return back()->with('success', 'تم تحديث بيانات الغرفة.');
    }

    public function show(Room $room): View
    {
        $room->load([
            'customer',
            'roomMaterials.material.materialType',
            'customerPayments' => fn ($query) => $query->latest('paid_at')->latest('id'),
            'roomCosts' => fn ($query) => $query->latest('occurred_at')->latest('id'),
        ]);

        return view('rooms.show', [
            'room' => $room,
            'profit' => $this->profit->forRoom($room),
            'stockByMaterial' => $this->inventory->stockByMaterialIds($room->roomMaterials->pluck('material_id')->all()),
            'availableMaterials' => Material::query()->orderBy('name')->get(),
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
            'pricing' => $this->profit->pricingComparison($room),
            'duration' => $this->profit->durationComparison($room),
            'activityLogs' => $this->roomActivity($room),
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

        $this->roomService->changeStatus($room, RoomStatus::from($validated['status']));

        return back()->with('success', 'تم تحديث حالة الغرفة.');
    }

    /**
     * The room's own history plus its materials, costs and payments — one
     * query on subject type and id, not one per entity.
     *
     * @return Collection<int, ActivityLog>
     */
    private function roomActivity(Room $room): Collection
    {
        $subjects = [
            [Room::class, [$room->id]],
            [RoomMaterial::class, $room->roomMaterials->pluck('id')->all()],
            [RoomCost::class, $room->roomCosts->pluck('id')->all()],
            [CustomerPayment::class, $room->customerPayments->pluck('id')->all()],
        ];

        return ActivityLog::query()
            ->where(function ($query) use ($subjects) {
                foreach ($subjects as [$type, $ids]) {
                    if ($ids === []) {
                        continue;
                    }

                    $query->orWhere(fn ($inner) => $inner->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            })
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function savePricing(SaveRoomPricingRequest $request, Room $room): RedirectResponse
    {
        // One try/catch per amount, so a bad value is reported on its own field.
        $amounts = [];

        foreach (['materials' => 'estimated_materials', 'accessories' => 'estimated_accessories', 'labor' => 'estimated_labor', 'other' => 'estimated_other'] as $key => $field) {
            try {
                $amounts[$key] = $request->filled($field) ? MoneyCast::toScaledInt($request->string($field)->toString()) : null;
            } catch (InvalidArgumentException) {
                return back()->withInput()->withErrors([$field => 'قيمة التقدير غير صالحة.']);
            }
        }

        try {
            $this->pricing->save(
                $room,
                $amounts,
                $request->filled('expected_duration_days') ? $request->integer('expected_duration_days') : null,
            );
        } catch (RoomLockedException) {
            return back()->with('error', 'الغرفة مكتملة، التسعير مقفول.');
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['estimated_materials' => $exception->getMessage()]);
        }

        return back()->with('success', 'تم حفظ التسعير.');
    }

    public function storeMaterial(StoreRoomMaterialRequest $request, Room $room): RedirectResponse
    {
        $material = Material::query()->findOrFail($request->integer('material_id'));

        try {
            $quantity = QuantityCast::toScaledInt($request->string('required_quantity')->toString());
            $this->roomMaterialService->addRequirement($room, $material, $quantity);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['required_quantity' => 'قيمة الكمية غير صالحة.']);
        } catch (RoomLockedException) {
            return back()->with('error', 'هذه الغرفة مكتملة ومقفولة، ما ينفعش تتعدل خاماتها.');
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
            return back()->with('error', 'الكمية المتاحة في المخزن غير كافية لصرف هذه الكمية.');
        } catch (ExceedsRequiredQuantityException) {
            return back()->with('error', 'لا يمكن صرف كمية أكبر من المطلوب.');
        } catch (RoomLockedException) {
            return back()->with('error', 'هذه الغرفة مكتملة ومقفولة، ما ينفعش تتعدل خاماتها.');
        }

        return back()->with('success', 'تم صرف الكمية.');
    }

    public function destroyMaterial(Room $room, RoomMaterial $roomMaterial): RedirectResponse
    {
        abort_if($roomMaterial->room_id !== $room->id, 404);

        try {
            $this->roomMaterialService->removeRequirement($roomMaterial);
        } catch (RoomLockedException) {
            return back()->with('error', 'هذه الغرفة مكتملة ومقفولة، ما ينفعش تتعدل خاماتها.');
        }

        return back()->with('success', 'تم حذف الاحتياج.');
    }

    public function updateMaterial(UpdateRoomMaterialRequest $request, Room $room, RoomMaterial $roomMaterial): RedirectResponse
    {
        abort_if($roomMaterial->room_id !== $room->id, 404);

        try {
            $quantity = QuantityCast::toScaledInt($request->string('required_quantity')->toString());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['required_quantity' => 'قيمة الكمية غير صالحة.'], 'edit_'.$roomMaterial->id);
        }

        try {
            $this->roomMaterialService->updateRequirement($roomMaterial, $quantity);
        } catch (ExceedsRequiredQuantityException $exception) {
            return back()->withInput()->withErrors([
                'required_quantity' => 'الخامة دي اتصرف منها '.QuantityCast::toDisplayString($exception->attempted).' بالفعل، ومينفعش المطلوب يبقى أقل من كده.',
            ], 'edit_'.$roomMaterial->id);
        } catch (RoomLockedException) {
            return back()->with('error', 'هذه الغرفة مكتملة ومقفولة، ما ينفعش تتعدل خاماتها.');
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['required_quantity' => 'الكمية لازم تكون أكبر من صفر.'], 'edit_'.$roomMaterial->id);
        }

        return back()->with('success', 'تم تعديل الاحتياج.');
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
