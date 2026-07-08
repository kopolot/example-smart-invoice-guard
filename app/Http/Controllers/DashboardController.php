<?php

namespace App\Http\Controllers;

use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardStatsService $dashboardStatsService) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->dashboardStatsService->forUser($request->user()));
    }
}
