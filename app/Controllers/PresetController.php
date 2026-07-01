<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\PresetService;

class PresetController extends Controller
{
    public function installStarterKit(Request $request): void
    {
        $service = new PresetService(app()->db());
        $result = $service->installStarterKit((int) auth()->id());

        $this->redirectWith(
            '/',
            success: sprintf(
                'Starter kit đã sẵn sàng: +%d label, +%d template.',
                $result['created_labels'],
                $result['created_templates']
            )
        );
    }
}
