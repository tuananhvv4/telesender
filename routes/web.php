<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomEmojiController;
use App\Controllers\DashboardController;
use App\Controllers\LabelController;
use App\Controllers\LogController;
use App\Controllers\MessageTemplateController;
use App\Controllers\PresetController;
use App\Controllers\ScheduleController;
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

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->post('/presets/starter-kit', [PresetController::class, 'installStarterKit'], ['auth']);

$router->get('/accounts', [TelegramAccountController::class, 'index'], ['auth']);
$router->post('/accounts', [TelegramAccountController::class, 'store'], ['auth']);
$router->post('/accounts/send-code', [TelegramAccountController::class, 'sendCode'], ['auth']);
$router->post('/accounts/verify-code', [TelegramAccountController::class, 'verifyCode'], ['auth']);
$router->post('/accounts/verify-password', [TelegramAccountController::class, 'verifyPassword'], ['auth']);

$router->get('/groups', [TelegramGroupController::class, 'index'], ['auth']);
$router->get('/groups/topics', [TelegramGroupController::class, 'topics'], ['auth']);
$router->post('/groups', [TelegramGroupController::class, 'store'], ['auth']);
$router->post('/groups/update', [TelegramGroupController::class, 'update'], ['auth']);
$router->post('/groups/delete', [TelegramGroupController::class, 'delete'], ['auth']);

$router->get('/labels', [LabelController::class, 'index'], ['auth']);
$router->post('/labels', [LabelController::class, 'store'], ['auth']);
$router->post('/labels/update', [LabelController::class, 'update'], ['auth']);
$router->post('/labels/delete', [LabelController::class, 'delete'], ['auth']);

$router->get('/templates', [MessageTemplateController::class, 'index'], ['auth']);
$router->post('/templates/preview', [MessageTemplateController::class, 'preview'], ['auth']);
$router->post('/templates', [MessageTemplateController::class, 'store'], ['auth']);
$router->post('/templates/update', [MessageTemplateController::class, 'update'], ['auth']);
$router->post('/templates/delete', [MessageTemplateController::class, 'delete'], ['auth']);

$router->get('/custom-emojis', [CustomEmojiController::class, 'index'], ['auth']);
$router->post('/custom-emojis', [CustomEmojiController::class, 'store'], ['auth']);
$router->post('/custom-emojis/update', [CustomEmojiController::class, 'update'], ['auth']);
$router->post('/custom-emojis/delete', [CustomEmojiController::class, 'delete'], ['auth']);

$router->get('/schedules', [ScheduleController::class, 'index'], ['auth']);
$router->get('/schedules/preview', [ScheduleController::class, 'preview'], ['auth']);
$router->post('/schedules', [ScheduleController::class, 'store'], ['auth']);
$router->post('/schedules/send-now', [ScheduleController::class, 'sendNow'], ['auth']);
$router->post('/schedules/update', [ScheduleController::class, 'update'], ['auth']);
$router->post('/schedules/toggle', [ScheduleController::class, 'toggle'], ['auth']);
$router->post('/schedules/delete', [ScheduleController::class, 'delete'], ['auth']);

$router->get('/logs', [LogController::class, 'index'], ['auth']);
