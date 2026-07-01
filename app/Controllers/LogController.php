<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\DispatchLog;

class LogController extends Controller
{
    public function __construct(private readonly DispatchLog $logs = new DispatchLog())
    {
    }

    public function index(Request $request): void
    {
        $this->render('logs/index', [
            'title' => 'Dispatch Logs',
            'logs' => $this->logs->recentForUser((int) auth()->id(), 200),
        ]);
    }
}
