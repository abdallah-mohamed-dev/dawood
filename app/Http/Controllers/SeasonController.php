<?php

namespace App\Http\Controllers;

use App\Enums\SeasonStatus;
use App\Models\Room;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use App\Services\SeasonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SeasonController extends Controller
{
    public function __construct(private readonly SeasonService $seasons) {}

    public function index(Request $request): View
    {
        $all = Season::query()->orderByDesc('number')->get();
        $archived = Room::query()->whereNotNull('season_id')->selectRaw('season_id, count(*) as total')->groupBy('season_id')->pluck('total', 'season_id');
        $distributed = SeasonPartnerShare::query()->selectRaw('season_id, sum(share_amount) as total')->groupBy('season_id')->pluck('total', 'season_id');

        $selected = $request->integer('season') > 0
            ? $all->firstWhere('id', $request->integer('season'))
            : null;

        return view('seasons.index', [
            'seasons' => $all,
            'archived' => $archived,
            'distributed' => $distributed,
            'openSeason' => $all->firstWhere('status', SeasonStatus::Open),
            'latestClosed' => $all->firstWhere('status', SeasonStatus::Closed),
            'canReopen' => $all->firstWhere('status', SeasonStatus::Closed) !== null
                && $this->seasons->canReopen($all->firstWhere('status', SeasonStatus::Closed)),
            'selected' => $selected,
            'shares' => $selected ? SeasonPartnerShare::query()->with('partner')->where('season_id', $selected->id)->get() : collect(),
            'names' => $all->mapWithKeys(fn (Season $season) => [$season->id => $this->seasons->displayName($season)]),
        ]);
    }

    public function preview(Request $request): View
    {
        $validated = $request->validate(['ends_at' => ['nullable', 'date']]);

        try {
            $data = $this->seasons->preview($validated['ends_at'] ?? null);
        } catch (RuntimeException) {
            return view('seasons.preview', ['data' => null]);
        }

        return view('seasons.preview', [
            'data' => $data,
            'season' => Season::query()->where('status', SeasonStatus::Open)->first(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ends_at' => ['required', 'date']], [
            'ends_at.required' => 'لازم تحدد تاريخ نهاية الموسم.',
            'ends_at.date' => 'تاريخ نهاية الموسم غير صالح.',
        ]);

        try {
            $season = $this->seasons->close($validated['ends_at']);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('seasons.index', ['season' => $season->id])->with('success', 'تم إقفال الموسم وبدأ موسم جديد.');
    }

    public function reopen(Season $season): RedirectResponse
    {
        try {
            $this->seasons->reopen($season);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('seasons.index')->with('success', 'تم فتح الموسم تاني.');
    }
}
