<?php

declare(strict_types=1);
use App\GuideBot\Webhook;
use Illuminate\Support\Facades\Route;

Route::post('/telegram', Webhook::class);
