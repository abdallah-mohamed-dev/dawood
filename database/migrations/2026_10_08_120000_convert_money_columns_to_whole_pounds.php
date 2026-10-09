<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Money stopped being counted in piastres (amount × 100) and became a whole
 * number of pounds — see specs/022. Every stored amount is divided by 100
 * here; nothing about the column types changes, only what the integer means.
 *
 * Rounding is half up on the magnitude, so a stray piastre value cannot turn
 * into a different sign or vanish. On the data this ran against every amount
 * was already an exact multiple of 100, making the conversion lossless.
 */
return new class extends Migration
{
    /**
     * Every money column in the system, by table.
     *
     * @var array<string, list<string>>
     */
    private const MONEY_COLUMNS = [
        'cashbox_transactions' => ['amount'],
        'materials' => ['unit_price'],
        'inventory_movements' => ['cost'],
        'rooms' => ['sale_price', 'estimated_materials', 'estimated_accessories', 'estimated_labor', 'estimated_other'],
        'room_materials' => ['cost'],
        'room_costs' => ['amount'],
        'customer_payments' => ['amount'],
        'expenses' => ['amount'],
        'partner_withdrawals' => ['amount'],
        'debts' => ['amount'],
        'capital_items' => ['amount'],
        'seasons' => [
            'revenue', 'cost_of_materials', 'room_costs', 'cancelled_room_costs', 'admin_expenses',
            'net_profit', 'loss_carried_in', 'loss_carried_out', 'distributable_profit',
            'cashbox_balance_at_close', 'stock_value_at_close', 'wip_carried_forward',
        ],
        'season_partner_shares' => ['share_amount', 'carried_in', 'withdrawn', 'carried_out'],
    ];

    public function up(): void
    {
        $this->backupDatabaseFile();

        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "UPDATE `{$table}`
                     SET `{$column}` = (CASE WHEN `{$column}` < 0 THEN -1 ELSE 1 END) * ((ABS(`{$column}`) + 50) / 100)
                     WHERE `{$column}` IS NOT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        foreach (self::MONEY_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("UPDATE `{$table}` SET `{$column}` = `{$column}` * 100 WHERE `{$column}` IS NOT NULL");
            }
        }
    }

    /**
     * A one-way rewrite of live money deserves a copy of the file it rewrites.
     * Skipped for the in-memory test database and for any non-SQLite setup —
     * there is no file to copy in either case.
     */
    private function backupDatabaseFile(): void
    {
        $path = DB::connection()->getDatabaseName();

        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        $copy = dirname($path).DIRECTORY_SEPARATOR
            .'dawood-before-whole-pounds-'.date('Y-m-d-His').'.sqlite';

        if (@copy($path, $copy)) {
            echo "  نسخة احتياطية قبل التحويل: {$copy}\n";
        }
    }
};
