<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Process;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder; // Importar Builder

class TaskController extends Controller
{
    /**
     * Display a listing of the resource for Kanban view.
     */
    public function index(Request $request): Response
    {
        $tasksQuery = Task::query()
            ->with([
                'responsibleUser:id,name',
                'responsibles:id,name',
                'process:id,title',
                'contact:id,name,business_name,type' // Contato principal da tarefa
            ])
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc');

        // Filtros existentes
        if ($request->filled('status')) {
            $tasksQuery->where('status', $request->input('status'));
        }
        if ($request->filled('responsible_user_id')) {
            $tasksQuery->where('responsible_user_id', $request->input('responsible_user_id'));
        }

        // NOVO: Filtro por Contato (contact_id da tarefa)
        if ($request->filled('contact_id')) {
            $tasksQuery->where('contact_id', $request->input('contact_id'));
        }

        // NOVO: Filtro por Período (usando due_date como exemplo)
        if ($request->filled('due_date_from')) {
            try {
                $tasksQuery->whereDate('due_date', '>=', Carbon::parse($request->input('due_date_from'))->startOfDay());
            } catch (\Exception $e) {
                Log::warning("Data 'due_date_from' inválida recebida: " . $request->input('due_date_from'));
            }
        }
        if ($request->filled('due_date_to')) {
             try {
                $tasksQuery->whereDate('due_date', '<=', Carbon::parse($request->input('due_date_to'))->endOfDay());
            } catch (\Exception $e) {
                Log::warning("Data 'due_date_to' inválida recebida: " . $request->input('due_date_to'));
            }
        }
        
        // Outra opção para filtro de período: data de criação da tarefa
        // if ($request->filled('created_at_from')) {
        //     $tasksQuery->whereDate('created_at', '>=', Carbon::parse($request->input('created_at_from'))->startOfDay());
        // }
        // if ($request->filled('created_at_to')) {
        //     $tasksQuery->whereDate('created_at', '<=', Carbon::parse($request->input('created_at_to'))->endOfDay());
        // }


        $tasks = $tasksQuery->get();

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'taskStatuses' => Task::STATUSES,
            'taskPriorities' => Task::PRIORITIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'processes' => Process::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'contacts' => Contact::orderBy('name') // Lista de contatos para o filtro
                                ->select('id', 'name', 'business_name', 'type') // Selecionar campos necessários
                                ->get()
                                ->map(function ($contact) {
                                    return [
                                        'id' => $contact->id,
                                        // Para exibir nome fantasia ou nome da pessoa física
                                        'display_name' => $contact->type === 'legal' ? $contact->business_name : $contact->name,
                                    ];
                                }),
            'filters' => $request->only([
                'status', 
                'responsible_user_id', 
                'contact_id', // Adicionar novo filtro
                'due_date_from', // Adicionar novo filtro
                'due_date_to' // Adicionar novo filtro
                // 'created_at_from', // Se usar filtro por data de criação
                // 'created_at_to',   // Se usar filtro por data de criação
            ])
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('tasks/Create', [
            'taskStatuses' => Task::STATUSES,
            'taskPriorities' => Task::PRIORITIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'processes' => Process::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'contacts' => Contact::orderBy('name')->get(['id', 'name', 'business_name']),
        ]);
    }


    /**
     * Store a newly created task.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date_format:Y-m-d',
            'responsible_user_id' => 'nullable|exists:users,id',
            'responsible_ids' => 'nullable|array',
            'responsible_ids.*' => 'exists:users,id',
            'status' => ['required', 'string', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['required', 'string', Rule::in(array_keys(Task::PRIORITIES))],
            'process_id' => 'nullable|exists:processes,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        if (!empty($validatedData['process_id']) && !empty($validatedData['contact_id'])) {
            return back()->withErrors(['general' => 'Uma tarefa não pode ser associada a um Processo e a um Contato simultaneamente.'])->withInput();
        }
        
        if (!empty($validatedData['process_id'])) {
            $process = Process::find($validatedData['process_id']);
            if ($process && $process->isArchived()) {
                 return back()->with('error', 'Não é possível adicionar tarefas a um caso arquivado.')->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $taskData = $validatedData;
            $responsibleIds = $validatedData['responsible_ids'] ?? [];
            unset($taskData['responsible_ids']);
            
            if (!isset($taskData['responsible_user_id']) && !empty($responsibleIds)) {
                $taskData['responsible_user_id'] = $responsibleIds[0];
            }

            $task = Task::create($taskData);

            if (!empty($responsibleIds) && method_exists($task, 'responsibles')) {
                $task->responsibles()->sync($responsibleIds);
            }

            if ($task->process_id) {
                $task->process->historyEntries()->create([
                    'action' => 'Tarefa Adicionada ao Caso',
                    'description' => "Tarefa \"{$task->title}\" foi adicionada ao caso.",
                    'user_id' => auth()->id(),
                ]);
            }
            
            DB::commit();

            if ($request->inertia()) { 
                if ($task->process_id) {
                    return redirect()->route('processes.show', $task->process_id)->with('success', 'Tarefa adicionada com sucesso!');
                } elseif ($task->contact_id) {
                   return redirect()->route('tasks.index')->with('success', 'Tarefa criada com sucesso!');
                }
                return redirect()->route('tasks.index')->with('success', 'Tarefa criada com sucesso!');
            }
            return response()->json($task, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar tarefa: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            if ($request->inertia()) {
                return back()->with('error', 'Falha ao criar tarefa.')->withInput();
            }
            return response()->json(['error' => 'Falha ao criar tarefa.'], 500);
        }
    }

    /**
     * Update the specified task in storage.
     */
    public function update(Request $request, Task $task)
    {
        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date_format:Y-m-d',
            'responsible_user_id' => 'nullable|exists:users,id',
            'responsible_ids' => 'nullable|array',
            'responsible_ids.*' => 'exists:users,id',
            'status' => ['sometimes','required', 'string', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['sometimes','required', 'string', Rule::in(array_keys(Task::PRIORITIES))],
            'process_id' => 'nullable|exists:processes,id', 
            'contact_id' => 'nullable|exists:contacts,id',
        ]);
        
        if (isset($validatedData['process_id']) && isset($validatedData['contact_id']) && $validatedData['process_id'] && $validatedData['contact_id']) {
             return back()->withErrors(['general' => 'Uma tarefa não pode ser associada a um Processo e a um Contato simultaneamente.'])->withInput();
        }

        $targetProcessId = $validatedData['process_id'] ?? $task->process_id;
        if ($targetProcessId) {
            $process = Process::find($targetProcessId);
            if ($process && $process->isArchived()) {
                 return back()->with('error', 'Não é possível modificar tarefas de um caso arquivado.')->withInput();
            }
        }

        DB::beginTransaction();
        try {
            if (isset($validatedData['status'])) {
                if ($validatedData['status'] === Task::STATUS_COMPLETED && !$task->completed_at) {
                    $validatedData['completed_at'] = now();
                } elseif ($validatedData['status'] !== Task::STATUS_COMPLETED) {
                    $validatedData['completed_at'] = null;
                }
            }
            
            $responsibleIds = $validatedData['responsible_ids'] ?? null;
            if(array_key_exists('responsible_ids', $validatedData)) {
                // Se responsible_ids está presente na request, mesmo que seja um array vazio,
                // precisamos removê-lo de $validatedData para não tentar atribuir a uma coluna que não existe.
                // A sincronização será feita abaixo.
                unset($validatedData['responsible_ids']);
            }


            $task->update($validatedData);

            if ($responsibleIds !== null && method_exists($task, 'responsibles')) {
                $task->responsibles()->sync($responsibleIds);
            }

            if ($task->process_id && $task->wasChanged()) {
                 $task->process->historyEntries()->create([
                    'action' => 'Tarefa Atualizada no Caso',
                    'description' => "Tarefa \"{$task->title}\" foi atualizada.",
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();
            if ($request->inertia()) {
                return back()->with('success', 'Tarefa atualizada com sucesso!');
            }
            return response()->json($task);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar tarefa {$task->id}: " . $e->getMessage());
             if ($request->inertia()) {
                return back()->with('error', 'Falha ao atualizar tarefa.')->withInput();
            }
            return response()->json(['error' => 'Falha ao atualizar tarefa.'], 500);
        }
    }


    /**
     * Remove the specified task from storage.
     */
    public function destroy(Request $request, Task $task)
    {
        if ($task->process_id) {
            $process = $task->process; 
            if ($process && $process->isArchived()) {
                 return back()->with('error', 'Não é possível excluir tarefas de um caso arquivado.');
            }
        }

        DB::beginTransaction();
        try {
            $taskTitle = $task->title;
            $processId = $task->process_id;

            if (method_exists($task, 'responsibles')) {
                $task->responsibles()->detach();
            }
            if (method_exists($task, 'associatedContacts')) { 
                $task->associatedContacts()->detach();
            }
            $task->delete();

            if ($processId) {
                Process::find($processId)->historyEntries()->create([
                    'action' => 'Tarefa Excluída do Caso',
                    'description' => "Tarefa \"{$taskTitle}\" foi excluída do caso.",
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();
            if ($request->inertia()) {
                return back()->with('success', 'Tarefa excluída com sucesso!');
            }
            return response()->json(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir tarefa {$task->id}: " . $e->getMessage());
            if ($request->inertia()) {
                return back()->with('error', 'Falha ao excluir tarefa.');
            }
            return response()->json(['error' => 'Falha ao excluir tarefa.'], 500);
        }
    }
}
