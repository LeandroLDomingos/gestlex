<?php

use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ContactController;
// Se você decidir usar controllers dedicados para sub-recursos, importe-os aqui:
// use App\Http\Controllers\ContactAnnotationController;
// use App\Http\Controllers\ContactDocumentController;
// use App\Http\Controllers\ProcessAnnotationController;
// use App\Http\Controllers\ProcessDocumentController;
// use App\Http\Controllers\TaskController;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Rotas para Contatos
    Route::resource('contacts', ContactController::class);

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

    // Rota para criar um processo a partir de um contato específico (se o método createProcess estiver no ContactController)
    // Se o método create do ProcessController já lida com contact_id opcional via query,
    // a rota de resource 'processes.create' já é suficiente.
    // Exemplo, se o método estiver no ContactController:
    // Route::get('/contacts/{contact}/processes/create', [ContactController::class, 'createProcess'])->name('contacts.processes.create');

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


    // Tarefas de Processo (assumindo que estão no ProcessController ou em um dedicado TaskController)
    // Se você criar um TaskController:
    // Route::post('/processes/{process}/tasks', [TaskController::class, 'storeForProcess'])->name('processes.tasks.store');
    // Route::resource('tasks', TaskController::class)->except(['index', 'create', 'store']); // Para update, show, destroy de tarefas individuais
    // Se estiver no ProcessController:
    // Route::post('/processes/{process}/tasks', [ProcessController::class, 'storeProcessTask'])->name('processes.tasks.store');
    // Route::put('/tasks/{task}', [ProcessController::class, 'updateProcessTask'])->name('tasks.update');
    // Route::delete('/tasks/{task}', [ProcessController::class, 'destroyProcessTask'])->name('tasks.destroy');


    // Rotas de Configurações (se o arquivo settings.php for para isso)
    // require __DIR__ . '/settings.php';

    // Outras rotas autenticadas podem vir aqui
});

// Rotas de Autenticação (geralmente já incluídas pelo Breeze/Jetstream)
require __DIR__ . '/auth.php';

// Se você tiver um arquivo settings.php para rotas de configuração, pode mantê-lo aqui,
// mas geralmente é melhor agrupá-las dentro do middleware de autenticação se exigirem login.
require __DIR__ . '/settings.php';
