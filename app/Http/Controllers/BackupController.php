<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CapitalItem;
use App\Models\CashboxTransaction;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Partner;
use App\Models\PartnerWithdrawal;
use App\Models\Room;
use App\Models\RoomCost;
use App\Models\RoomMaterial;
use App\Models\Season;
use App\Models\SeasonPartnerShare;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BackupController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /** @var array<class-string> */
    private const EXPORTABLE_MODELS = [
        Customer::class,
        Room::class,
        Material::class,
        InventoryMovement::class,
        RoomMaterial::class,
        RoomCost::class,
        CustomerPayment::class,
        Expense::class,
        ExpenseCategory::class,
        CashboxTransaction::class,
        Partner::class,
        PartnerWithdrawal::class,
        CapitalItem::class,
        Season::class,
        SeasonPartnerShare::class,
        Debt::class,
        MaterialType::class,
        Setting::class,
        ActivityLog::class,
    ];

    public function index(): View
    {
        return view('backup.index');
    }

    public function downloadDatabase(): BinaryFileResponse
    {
        $this->settings->set('last_backup_at', now()->toDateString());

        return response()->download(
            database_path('database.sqlite'),
            'dawood-backup-'.now()->format('Y-m-d-His').'.sqlite',
        );
    }

    public function downloadCsvArchive(): BinaryFileResponse
    {
        $this->settings->set('last_backup_at', now()->toDateString());

        $tempDir = storage_path('app/backup-tmp');
        File::ensureDirectoryExists($tempDir);

        $zipPath = $tempDir.DIRECTORY_SEPARATOR.uniqid('dawood-backup-').'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $csvPaths = [];

        foreach (self::EXPORTABLE_MODELS as $modelClass) {
            $model = new $modelClass;
            $table = $model->getTable();
            $columns = Schema::getColumnListing($table);

            $csvPath = $tempDir.DIRECTORY_SEPARATOR.uniqid('dawood-csv-');
            $handle = fopen($csvPath, 'w');
            fputcsv($handle, $columns);

            $modelClass::query()->orderBy('id')->cursor()->each(function ($record) use ($handle, $columns) {
                fputcsv($handle, array_map(
                    fn (string $column) => $record->getRawOriginal($column),
                    $columns,
                ));
            });

            fclose($handle);
            $zip->addFile($csvPath, "{$table}.csv");
            $csvPaths[] = $csvPath;
        }

        $zip->close();

        foreach ($csvPaths as $csvPath) {
            unlink($csvPath);
        }

        return response()->download($zipPath, 'dawood-backup-csv-'.now()->format('Y-m-d-His').'.zip')
            ->deleteFileAfterSend(true);
    }
}
