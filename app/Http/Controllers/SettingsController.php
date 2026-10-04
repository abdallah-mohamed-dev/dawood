<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMaterialTypeRequest;
use App\Models\MaterialType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
        ]);
    }

    public function updateMaterialType(UpdateMaterialTypeRequest $request, MaterialType $materialType): RedirectResponse
    {
        $materialType->update(['name' => $request->validated('name')]);

        return back()->with('success', 'تم حفظ اسم النوع.');
    }
}
