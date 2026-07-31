<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use App\Services\InvoicePulseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardStatsService $dashboardStatsService,
        private InvoicePulseService $invoicePulseService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            ...$this->dashboardStatsService->forUser($user),
            'hotInvoices' => $this->invoicePulseService->hotForUser($user->id),
        ]);
    }
}
