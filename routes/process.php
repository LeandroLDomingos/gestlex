<?php

use App\Http\Controllers\ProcessController;
use Illuminate\Support\Facades\Route;

Route::resource('processes', ProcessController::class);