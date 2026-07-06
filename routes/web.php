<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomEmojiController;
use App\Controllers\DashboardController;
use App\Controllers\ExpiredAccessController;
use App\Controllers\LabelController;
use App\Controllers\LogController;
use App\Controllers\MessageTemplateController;
use App\Controllers\PresetController;
use App\Controllers\ScheduleController;
use App\Controllers\SuperAdminController;
use App\Controllers\SystemController;
use App\Controllers\TelegramAccountController;
use App\Controllers\TelegramGroupController;

$router = app()->router();

$router->get('/health', [SystemController::class, 'health']);
$router->get('/cron/run', [SystemController::class, 'cron']);
$router->get('/system/migrate', [SystemController::class, 'migrate']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);

$router->get('/', [DashboardController::class, 'index'], ['auth', 'subscription_active']);
$router->get('/expired', [ExpiredAccessController::class, 'show'], ['auth']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->post('/presets/starter-kit', [PresetController::class, 'installStarterKit'], ['auth', 'subscription_active']);

$router->get('/accounts', [TelegramAccountController::class, 'index'], ['auth', 'subscription_active']);
$router->post('/accounts', [TelegramAccountController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/accounts/toggle-active', [TelegramAccountController::class, 'toggleActive'], ['auth', 'subscription_active']);
$router->post('/accounts/send-code', [TelegramAccountController::class, 'sendCode'], ['auth', 'subscription_active']);
$router->post('/accounts/verify-code', [TelegramAccountController::class, 'verifyCode'], ['auth', 'subscription_active']);
$router->post('/accounts/verify-password', [TelegramAccountController::class, 'verifyPassword'], ['auth', 'subscription_active']);

$router->get('/groups', [TelegramGroupController::class, 'index'], ['auth', 'subscription_active']);
$router->get('/groups/dialogs', [TelegramGroupController::class, 'dialogs'], ['auth', 'subscription_active']);
$router->get('/groups/topics', [TelegramGroupController::class, 'topics'], ['auth', 'subscription_active']);
$router->post('/groups/import', [TelegramGroupController::class, 'import'], ['auth', 'subscription_active']);
$router->post('/groups', [TelegramGroupController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/groups/update', [TelegramGroupController::class, 'update'], ['auth', 'subscription_active']);
$router->post('/groups/delete', [TelegramGroupController::class, 'delete'], ['auth', 'subscription_active']);

$router->get('/labels', [LabelController::class, 'index'], ['auth', 'subscription_active']);
$router->post('/labels', [LabelController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/labels/update', [LabelController::class, 'update'], ['auth', 'subscription_active']);
$router->post('/labels/delete', [LabelController::class, 'delete'], ['auth', 'subscription_active']);

$router->get('/templates', [MessageTemplateController::class, 'index'], ['auth', 'subscription_active']);
$router->post('/templates/preview', [MessageTemplateController::class, 'preview'], ['auth', 'subscription_active']);
$router->post('/templates', [MessageTemplateController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/templates/update', [MessageTemplateController::class, 'update'], ['auth', 'subscription_active']);
$router->post('/templates/delete', [MessageTemplateController::class, 'delete'], ['auth', 'subscription_active']);

$router->get('/custom-emojis', [CustomEmojiController::class, 'index'], ['auth', 'subscription_active']);
$router->post('/custom-emojis', [CustomEmojiController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/custom-emojis/import-bulk', [CustomEmojiController::class, 'bulkImport'], ['auth', 'subscription_active']);
$router->post('/custom-emojis/update', [CustomEmojiController::class, 'update'], ['auth', 'subscription_active']);
$router->post('/custom-emojis/delete', [CustomEmojiController::class, 'delete'], ['auth', 'subscription_active']);

$router->get('/schedules', [ScheduleController::class, 'index'], ['auth', 'subscription_active']);
$router->get('/schedules/preview', [ScheduleController::class, 'preview'], ['auth', 'subscription_active']);
$router->post('/schedules', [ScheduleController::class, 'store'], ['auth', 'subscription_active']);
$router->post('/schedules/send-now', [ScheduleController::class, 'sendNow'], ['auth', 'subscription_active']);
$router->post('/schedules/update', [ScheduleController::class, 'update'], ['auth', 'subscription_active']);
$router->post('/schedules/toggle', [ScheduleController::class, 'toggle'], ['auth', 'subscription_active']);
$router->post('/schedules/delete', [ScheduleController::class, 'delete'], ['auth', 'subscription_active']);

$router->get('/logs', [LogController::class, 'index'], ['auth', 'subscription_active']);

$router->get('/admin/users', [SuperAdminController::class, 'users'], ['auth', 'super_admin']);
$router->post('/admin/users', [SuperAdminController::class, 'storeUser'], ['auth', 'super_admin']);
$router->post('/admin/users/status', [SuperAdminController::class, 'toggleUserStatus'], ['auth', 'super_admin']);
$router->post('/admin/users/limits', [SuperAdminController::class, 'updateLimits'], ['auth', 'super_admin']);
$router->get('/admin/subscriptions', [SuperAdminController::class, 'subscriptions'], ['auth', 'super_admin']);
$router->post('/admin/subscriptions/adjust', [SuperAdminController::class, 'adjustSubscription'], ['auth', 'super_admin']);
$router->get('/admin/settings', [SuperAdminController::class, 'settings'], ['auth', 'super_admin']);
$router->post('/admin/settings', [SuperAdminController::class, 'updateSettings'], ['auth', 'super_admin']);
