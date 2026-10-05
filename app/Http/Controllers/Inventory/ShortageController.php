<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MaterialType;
use App\Models\Room;
use App\Services\ShortageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShortageController extends Controller
{
    public function __construct(private readonly ShortageService $shortages) {}

    public function index(Request $request): View
    {
        $filters = [
            'room_id' => $request->integer('room_id'),
            'customer_id' => $request->integer('customer_id'),
            'material_type_id' => $request->integer('material_type_id'),
            'q' => trim($request->string('q')->toString()),
        ];

        $rooms = $this->shortages->forActiveRooms($filters);

        return view('inventory.shortages.index', [
            'rooms' => $rooms,
            'totalCost' => $rooms->sum('total'),
            'lineCount' => $rooms->sum(fn (array $group) => $group['lines']->count()),
            'filters' => $filters,
            'activeRooms' => Room::query()->whereIn('status', [RoomStatus::Draft, RoomStatus::InProgress])->orderBy('id')->get(['id', 'room_type']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
        ]);
    }

    public function print(Room $room): View
    {
        $group = $this->shortages->forActiveRooms()->firstWhere('room.id', $room->id);

        return view('inventory.shortages.print', [
            'room' => $room->load('customer'),
            'lines' => $group['lines'] ?? collect(),
            'total' => $group['total'] ?? 0,
        ]);
    }
}
