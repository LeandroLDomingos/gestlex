<?php

use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {

    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');


    // Route::redirect('settings', '/settings/profile');

    // Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    // Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Route::get('settings/appearance', function () {
    //     return Inertia::render('settings/Appearance');
    // })->name('appearance');
});
