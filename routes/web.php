<?php

use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// Rota para adicionar anotações a um contato específico
Route::post('/contacts/{contact}/annotations', [ContactController::class, 'storeAnnotation'])
    ->name('contacts.annotations.store');

// Rota para adicionar documentos a um contato específico
Route::post('/contacts/{contact}/documents', [ContactController::class, 'storeDocument'])
    ->name('contacts.documents.store');
Route::delete('/contacts/{contact}/annotations/{annotation}', [ContactController::class, 'destroyAnnotation'])->name('contacts.annotations.destroy');

Route::delete('/contacts/{contact}/documents/{document}', [ContactController::class, 'destroyDocument'])->name('contacts.documents.destroy');

Route::resource('contacts', ContactController::class);
Route::resource('processes', ProcessController::class);

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
