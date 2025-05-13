<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
Route::get('/contacts/{contact}/show', [ContactController::class, 'show'])->name('contacts.show');
Route::post('/contacts/store', [ContactController::class, 'store'])->name('contacts.store');

