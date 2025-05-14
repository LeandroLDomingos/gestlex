<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


Route::resource('contacts', ContactController::class);
Route::resource('cases', CaseController::class);

require __DIR__.'/contacts.php';

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
