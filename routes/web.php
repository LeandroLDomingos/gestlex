<?php

use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\ACLMiddleware;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard')->middleware('auth');

Route::middleware([ACLMiddleware::class, 'auth', 'verified'])->group(function () {

    // Rotas para Contatos
    Route::resource('contacts', ContactController::class);

    // Rotas para Tarefas
    Route::resource('tasks', TaskController::class);

    Route::post('/tasks/store', [TaskController::class, 'store'])->name('tasks.store.general'); // Rota geral para criar tarefa

    Route::post('/contacts/{contact}/tasks', [TaskController::class, 'store'])->name('contacts.tasks.store');

    // Anotações de Contato (assumindo que estão no ContactController)
    Route::post('/contacts/{contact}/annotations', [ContactController::class, 'storeAnnotation'])
        ->name('contacts.annotations.store');
    Route::delete('/contacts/{contact}/annotations/{annotation}', [ContactController::class, 'destroyAnnotation'])
        ->name('contacts.annotations.destroy');

    // Documentos de Contato (assumindo que estão no ContactController)
    Route::post('/contacts/{contact}/documents', [ContactController::class, 'storeDocument'])
        ->name('contacts.documents.store');
    Route::delete('/contacts/{contact}/documents/{document}', [ContactController::class, 'destroyDocument'])
        ->name('contacts.documents.destroy');

    // Rotas para Processos (Casos)
    Route::resource('processes', ProcessController::class);

    // Rotas específicas para atualizar partes de um Processo
    Route::patch('/processes/{process}/update-stage', [ProcessController::class, 'updateStage'])->name('processes.updateStage');
    Route::patch('/processes/{process}/update-status', [ProcessController::class, 'updateStatus'])->name('processes.updateStatus');
    Route::patch('/processes/{process}/update-priority', [ProcessController::class, 'updatePriority'])->name('processes.updatePriority');
    // Adicionar rota para arquivar/desarquivar se necessário
    // Rota para arquivar um processo específico
    Route::patch('/processes/{process}/archive', [ProcessController::class, 'archive'])
        ->name('processes.archive');

    // Rota para restaurar (desarquivar) um processo específico
    Route::patch('/processes/{process}/unarchive', [ProcessController::class, 'unarchive'])
        ->name('processes.unarchive');


    // Anotações de Processo (assumindo que estão no ProcessController)
    Route::post('/processes/{process}/annotations', [ProcessController::class, 'storeProcessAnnotation'])->name('processes.annotations.store');
    Route::delete('/processes/{process}/annotations/{annotation}', [ProcessController::class, 'destroyProcessAnnotation'])->name('processes.annotations.destroy');

    Route::post('/processes/{process}/documents', [ProcessController::class, 'storeProcessDocument'])->name('processes.documents.store');
    Route::delete('/processes/{process}/documents/{document}', [ProcessController::class, 'destroyProcessDocument'])->name('processes.documents.destroy');
    Route::put('/processes/{process}/payments/{payment}', [ProcessController::class, 'updateProcessPayment'])->name('processes.payments.update');

    // --- ROTAS PARA TAREFAS DE PROCESSO ---
    Route::post('/processes/{process}/tasks', [ProcessController::class, 'storeProcessTask'])
        ->name('processes.tasks.store');
    Route::put('/processes/{process}/tasks/{task}', [ProcessController::class, 'updateProcessTask']) // Ou PATCH
        ->name('processes.tasks.update');
    Route::delete('/processes/{process}/tasks/{task}', [ProcessController::class, 'destroyProcessTask'])
        ->name('processes.tasks.destroy');
    Route::get('/processos/{process}/documentos/{document}/download', [ProcessController::class, 'documentDownload'])
        ->name('process-documents.download');

    Route::post('/processes/{process}/fees', [App\Http\Controllers\ProcessController::class, 'storeFee'])
        ->name('processes.fees.store');
    Route::put('/processes/{process}/fees/{fee}', [App\Http\Controllers\ProcessController::class, 'updateFee'])
        ->name('processes.fees.update');
    Route::delete('/processes/{process}/fees/{fee}', [App\Http\Controllers\ProcessController::class, 'destroyFee'])
        ->name('processes.fees.destroy');

    Route::resource('expenses', ExpenseController::class);

    Route::resource('financial-transactions', FinancialTransactionController::class);

    Route::prefix('admin')->name('admin.')->group(function () {
        // $this->authorizeResource(Role::class, 'role'); // Se estiver usando Policies
        Route::resource('roles', RoleController::class)->except(['show']);
        Route::resource('users', RoleController::class)->except(['show']);
        // A rota 'show' para um papel individual pode não ser necessária se a edição já mostra os detalhes.

    });
    
    Route::put('/admin/roles/{role}/permissions', [RoleController::class, 'syncRolePermissions'])
->name('admin.roles.permissions.sync');
});

// Rotas de Autenticação (geralmente já incluídas pelo Breeze/Jetstream)
require __DIR__ . '/auth.php';

// Se você tiver um arquivo settings.php para rotas de configuração, pode mantê-lo aqui,
// mas geralmente é melhor agrupá-las dentro do middleware de autenticação se exigirem login.
require __DIR__ . '/settings.php';
