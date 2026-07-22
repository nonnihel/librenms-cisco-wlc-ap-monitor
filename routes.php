<?php

declare(strict_types=1);

use Averna\CiscoWlcApMonitor\Http\Controllers\AccessPointController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AccessPointController::class, 'index'])->name('index');
Route::get('/widget', [AccessPointController::class, 'widget'])->name('widget');
Route::post('/access-points/{id}/action', [AccessPointController::class, 'action'])
    ->whereNumber('id')
    ->name('action');
