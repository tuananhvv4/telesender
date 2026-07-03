<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\SystemSetting;

class ExpiredAccessController extends Controller
{
    public function __construct(private readonly SystemSetting $settings = new SystemSetting())
    {
    }

    public function show(Request $request): void
    {
        $user = auth()->user();

        if ($user === null) {
            redirect('/login');
        }

        if (user_is_super_admin($user) || !user_is_expired($user)) {
            redirect('/');
        }

        $this->render('expired', [
            'title' => 'Hết hạn sử dụng',
            'settings' => $this->settings->resolvedMap(),
            'user' => $user,
        ], 'guest');
    }
}
