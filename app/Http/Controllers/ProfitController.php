<?php

namespace App\Http\Controllers;

use App\Services\CashboxService;
use App\Services\ProfitService;
use Illuminate\View\View;

class ProfitController extends Controller
{
    public function __construct(
        private readonly ProfitService $profit,
        private readonly CashboxService $cashbox,
    ) {}

    public function index(): View
    {
        $summary = $this->profit->summary();

        return view('reports.profit', [
            'revenue' => $summary['revenue'],
            'costOfMaterials' => $summary['cost_of_materials'],
            'adminExpenses' => $summary['admin_expenses'],
            'netProfit' => $summary['net_profit'],
            'workInProgress' => $summary['work_in_progress'],
            'stockValue' => $summary['stock_value'],
            'cashboxBalance' => $this->cashbox->summary()['balance'],
        ]);
    }
}
