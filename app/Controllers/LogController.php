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
        $searchQuery = trim((string) $request->query('q', ''));
        $perPage = pagination_per_page(50, [20, 50, 100, 200]);
        $result = $this->logs->paginateForUser((int) auth()->id(), (int) $request->query('page', 1), $perPage, $searchQuery);

        $this->render('logs/index', [
            'title' => 'Dispatch Logs',
            'logs' => $result['items'],
            'pagination' => $result['pagination'],
            'searchQuery' => $searchQuery,
        ]);
    }
}
