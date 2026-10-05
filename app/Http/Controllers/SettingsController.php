<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMaterialTypeRequest;
use App\Models\MaterialType;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): View
    {
        return view('settings.index', [
            'materialTypes' => MaterialType::query()->orderBy('position')->get(),
            'seasonLengthMonths' => $this->settings->getInt('season_length_months'),
            'backupReminderDays' => $this->settings->getInt('backup_reminder_days'),
        ]);
    }

    public function updateReminders(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'season_length_months' => ['required', 'integer', 'min:1'],
            'backup_reminder_days' => ['required', 'integer', 'min:1'],
        ], [
            'season_length_months.required' => 'مدة الموسم لازم تتكتب.',
            'season_length_months.integer' => 'مدة الموسم لازم تكون رقم صحيح بالشهور.',
            'season_length_months.min' => 'مدة الموسم لازم تكون شهر واحد على الأقل.',
            'backup_reminder_days.required' => 'مدة تذكير النسخ الاحتياطي لازم تتكتب.',
            'backup_reminder_days.integer' => 'مدة تذكير النسخ الاحتياطي لازم تكون رقم صحيح بالأيام.',
            'backup_reminder_days.min' => 'مدة تذكير النسخ الاحتياطي لازم تكون يوم واحد على الأقل.',
        ]);

        $this->settings->set('season_length_months', $validated['season_length_months']);
        $this->settings->set('backup_reminder_days', $validated['backup_reminder_days']);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    public function updateMaterialType(UpdateMaterialTypeRequest $request, MaterialType $materialType): RedirectResponse
    {
        $materialType->update(['name' => $request->validated('name')]);

        return back()->with('success', 'تم حفظ اسم النوع.');
    }
}
