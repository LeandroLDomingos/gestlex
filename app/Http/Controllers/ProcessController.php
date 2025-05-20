<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ProcessController extends Controller
{
    protected function applySearchFilters(Builder $query, ?string $searchTerm): Builder
    {
        return $query->when($searchTerm, function ($query, $searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('origin', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('workflow', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('responsible', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
                    })
                    ->orWhereHas('contact', function ($contactQuery) use ($searchTerm) {
                        $contactQuery->where('name', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('business_name', 'LIKE', "%{$searchTerm}%");
                    });
                if (is_numeric($searchTerm)) {
                    $q->orWhere('negotiated_value', '=', $searchTerm);
                }
            });
        });
    }

    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $search = $request->input('search', '');
        $workflowFilter = $request->input('workflow');
        $stageFilter = $request->input('stage');
        $responsibleFilter = $request->input('responsible_id');
        $priorityFilter = $request->input('priority');
        $statusFilter = $request->input('status');
        $dateFromFilter = $request->input('date_from');
        $dateToFilter = $request->input('date_to');

        $directSortableColumns = ['title', 'origin', 'negotiated_value', 'workflow', 'stage', 'priority', 'status', 'due_date', 'created_at', 'updated_at'];
        $relationSortableColumns = ['contact.name', 'responsible.name'];
        $allowedSortColumns = array_merge($directSortableColumns, $relationSortableColumns);

        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $processesQuery = Process::query()
            ->with([
                'responsible:id,name',
                'contact:id,name,business_name,type',
            ]);

        $processesQuery = $this->applySearchFilters($processesQuery, $search);

        $processesQuery
            ->when($workflowFilter, fn(Builder $query, $val) => $query->where('workflow', $val))
            ->when($stageFilter && $workflowFilter, fn(Builder $query, $val) => $query->where('stage', $stageFilter))
            ->when($responsibleFilter, fn(Builder $query, $val) => $query->where('responsible_id', $val))
            ->when($priorityFilter, fn(Builder $query, $val) => $query->where('priority', $val))
            ->when($statusFilter, fn(Builder $query, $val) => $query->where('status', $val))
            ->when($dateFromFilter, function (Builder $query, $dateFrom) {
                try {
                    return $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                } catch (\Exception $e) { return $query; }
            })
            ->when($dateToFilter, function (Builder $query, $dateTo) {
                try {
                    return $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                } catch (\Exception $e) { return $query; }
            });

        if (in_array($sortBy, ['title', 'origin', 'workflow'])) {
            $processesQuery->orderByRaw("LOWER({$sortBy}) {$sortDirection}");
        } elseif ($sortBy === 'contact.name') {
            $processesQuery->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')
                           ->orderBy('contacts.name', $sortDirection)
                           ->select('processes.*');
        } elseif ($sortBy === 'responsible.name') {
            $processesQuery->leftJoin('users', 'processes.responsible_id', '=', 'users.id')
                           ->orderBy('users.name', $sortDirection)
                           ->select('processes.*');
        } elseif (in_array($sortBy, $directSortableColumns)) {
            $processesQuery->orderBy($sortBy, $sortDirection);
        }

        $processes = $processesQuery->paginate(15)->withQueryString();

        $workflowsData = collect(Process::WORKFLOWS)->map(function ($label, $key) use ($search, $responsibleFilter, $priorityFilter, $statusFilter, $dateFromFilter, $dateToFilter) {
            $countQuery = Process::query()->where('workflow', $key);
            $countQuery = $this->applySearchFilters($countQuery, $search);
            $countQuery
                ->when($responsibleFilter, fn($q, $val) => $q->where('responsible_id', $val))
                ->when($priorityFilter, fn($q, $val) => $q->where('priority', $val))
                ->when($statusFilter, fn($q, $val) => $q->where('status', $val))
                ->when($dateFromFilter, function (Builder $q, $dateFrom) {
                    try { return $q->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay()); } catch (\Exception $e) { return $q; }
                })
                ->when($dateToFilter, function (Builder $q, $dateTo) {
                    try { return $q->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay()); } catch (\Exception $e) { return $q; }
                });

            return [
                'key' => $key,
                'label' => $label,
                'count' => $countQuery->count(),
                'stages' => collect(Process::getStagesForWorkflow($key))
                                ->map(fn($stageLabel, $stageKey) => ['key' => (int)$stageKey, 'label' => $stageLabel]) // Garante key como int
                                ->values()->all(),
            ];
        })->values()->all();

        $allProcessesCountQuery = Process::query();
        $allProcessesCountQuery = $this->applySearchFilters($allProcessesCountQuery, $search);
        $allProcessesCountQuery
            ->when($responsibleFilter, fn($q, $val) => $q->where('responsible_id', $val))
            ->when($priorityFilter, fn($q, $val) => $q->where('priority', $val))
            ->when($statusFilter, fn($q, $val) => $q->where('status', $val))
            ->when($dateFromFilter, function (Builder $q, $dateFrom) {
                try { return $q->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay()); } catch (\Exception $e) { return $q; }
            })
            ->when($dateToFilter, function (Builder $q, $dateTo) {
                try { return $q->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay()); } catch (\Exception $e) { return $q; }
            });
        $allProcessesCount = $allProcessesCountQuery->count();

        $currentWorkflowStages = [];
        if ($workflowFilter && array_key_exists($workflowFilter, Process::WORKFLOWS)) {
            $currentWorkflowStages = collect(Process::getStagesForWorkflow($workflowFilter))
                ->map(fn($label, $key) => ['key' => (int)$key, 'label' => $label])->values()->all();
        }

        $usersForFilter = User::orderBy('name')->get(['id', 'name']);
        $statusesForFilter = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string)$s)])->all();

        $prioritiesForFilter = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => 'low', 'label' => 'Baixa'], ['key' => 'medium', 'label' => 'Média'], ['key' => 'high', 'label' => 'Alta']];

        return Inertia::render('processes/Index', [
            'processes' => $processes,
            'filters' => $request->only(['search', 'workflow', 'stage', 'responsible_id', 'priority', 'status', 'date_from', 'date_to', 'sort_by', 'sort_direction']),
            'workflows' => $workflowsData,
            'currentWorkflowStages' => $currentWorkflowStages,
            'allProcessesCount' => $allProcessesCount,
            'usersForFilter' => $usersForFilter,
            'statusesForFilter' => $statusesForFilter,
            'prioritiesForFilter' => $prioritiesForFilter,
        ]);
    }

    public function create(Request $request): Response
    {
        $contactId = $request->query('contact_id');
        $contact = null;
        if ($contactId) {
            $contact = Contact::find($contactId);
        }

        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);

        $availableWorkflows = collect(Process::WORKFLOWS)->map(function ($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values()->all();

        $allStages = [];
        foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
            $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                ->map(function ($label, $key) {
                    return ['key' => (int)$key, 'label' => $label];
                })->values()->all();
        }
        
        $statusesForForm = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string)$s)])->all();

        $prioritiesForForm = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => 'low', 'label' => 'Baixa'], ['key' => 'medium', 'label' => 'Média'], ['key' => 'high', 'label' => 'Alta']];

        return Inertia::render('processes/Create', [
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => $contact ? ($contact->name ?: $contact->business_name) : null,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'statusesForForm' => $statusesForForm,
            'prioritiesForForm' => $prioritiesForForm,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id',
            'workflow' => ['required', 'string', Rule::in(array_keys(Process::WORKFLOWS))],
            'stage' => ['required', 'integer'],
            'due_date' => 'nullable|date_format:Y-m-d',
            'priority' => 'required|in:low,medium,high',
            'origin' => 'nullable|string|max:100',
            'negotiated_value' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:50',
        ]);

        $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
        if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
            return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
        }
        
        $validatedData['status'] = $validatedData['status'] ?? Process::STATUS_OPEN; // Usando constante para status padrão

        DB::beginTransaction();
        try {
            $process = Process::create($validatedData);
            // Anotação automática de criação
            $process->annotations()->create([
                'content' => 'Caso criado.',
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return Redirect::route('processes.show', $process->id)
                           ->with('success', 'Caso criado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar caso/processo: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao criar o caso.');
        }
    }

    public function show(Process $process): Response
    {
        $process->load([
            'responsible:id,name',
            'contact:id,name,business_name,type',
            'annotations' => function ($query) {
                $query->with('user:id,name')->latest();
            },
            'tasks' => function ($query) {
                $query->with('responsibleUser:id,name')->orderBy('due_date');
            },
            'documents' => function ($query) {
                $query->with('uploader:id,name')->orderBy('created_at', 'desc');
            },
        ]);
        // Garante que os acessores sejam carregados
        $process->append(['workflow_label', 'stage_label', 'priority_label', 'status_label']);

        // Estágios disponíveis para o workflow atual deste processo
        $availableStages = [];
        if ($process->workflow) {
            $availableStages = collect(Process::getStagesForWorkflow($process->workflow))
                ->map(fn($label, $key) => ['key' => (int)$key, 'label' => $label])
                ->values()->all();
        }

        return Inertia::render('processes/Show', [
            'process' => $process,
            'users' => User::orderBy('name')->get(['id', 'name']), // Para dropdowns na página (ex: mudar responsável)
            'availableStages' => $availableStages, // Passa os estágios para o dropdown de mudança de estágio
        ]);
    }

    public function edit(Process $process): Response
    {
        $process->load(['contact:id,name,business_name,type', 'responsible:id,name']);
        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);
        $availableWorkflows = collect(Process::WORKFLOWS)->map(function ($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values()->all();
        $allStages = [];
        foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
            $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                ->map(function ($label, $key) {
                    return ['key' => (int)$key, 'label' => $label];
                })->values()->all();
        }

        $statusesForForm = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string)$s)])->all();

        $prioritiesForForm = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => 'low', 'label' => 'Baixa'], ['key' => 'medium', 'label' => 'Média'], ['key' => 'high', 'label' => 'Alta']];

        return Inertia::render('processes/Edit', [
            'process' => $process,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'statusesForForm' => $statusesForForm,
            'prioritiesForForm' => $prioritiesForForm,
        ]);
    }

    public function update(Request $request, Process $process)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id',
            'workflow' => ['required', 'string', Rule::in(array_keys(Process::WORKFLOWS))],
            'stage' => ['required', 'integer'],
            'due_date' => 'nullable|date_format:Y-m-d',
            'priority' => 'required|in:low,medium,high',
            'origin' => 'nullable|string|max:100',
            'negotiated_value' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|max:50',
        ]);

        $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
        if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
            return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $oldStageLabel = $process->stage_label; // Pega o label do estágio antigo
            
            $process->update($validatedData);
            
            // Se o estágio mudou, adiciona uma anotação
            if ($process->wasChanged('stage')) {
                 $newStageLabel = $process->fresh()->stage_label; // Pega o label do novo estágio
                 $process->annotations()->create([
                    'content' => "Estágio alterado de \"{$oldStageLabel}\" para \"{$newStageLabel}\".",
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();
            return Redirect::route('processes.show', $process->id)
                           ->with('success', 'Caso atualizado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar caso/processo {$process->id}: " . $e->getMessage());
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao atualizar o caso.');
        }
    }

    /**
     * Atualiza apenas o estágio de um processo.
     */
    public function updateStage(Request $request, Process $process)
    {
        $validated = $request->validate([
            'stage' => ['required', 'integer', function ($attribute, $value, $fail) use ($process) {
                $stagesForWorkflow = Process::getStagesForWorkflow($process->workflow);
                if (!array_key_exists($value, $stagesForWorkflow)) {
                    $fail("O estágio selecionado não é válido para o workflow '{$process->workflow_label}'.");
                }
            }],
        ]);

        DB::beginTransaction();
        try {
            $oldStageLabel = $process->stage_label; // Pega o label do estágio antigo ANTES de atualizar

            $process->stage = $validated['stage'];
            // Opcional: Atualizar o campo 'status' com base no novo estágio, se houver essa lógica.
            // Exemplo: $process->status = $this->determineStatusFromStage($process->workflow, $validated['stage']);
            $process->save();

            $newStageLabel = $process->fresh()->stage_label; // Pega o label do novo estágio DEPOIS de atualizar e salvar

            // Adicionar uma anotação automática sobre a mudança de estágio
            $process->annotations()->create([
                'content' => "Estágio alterado de \"{$oldStageLabel}\" para \"{$newStageLabel}\".",
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            // Retorna para a página de visualização do processo com mensagem de sucesso
            return Redirect::route('processes.show', $process->id)
                           ->with('success', 'Estágio do caso atualizado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput(); // Retorna erros de validação
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar estágio do processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao atualizar o estágio.');
        }
    }


    public function destroy(Process $process)
    {
        DB::beginTransaction();
        try {
            $processTitle = $process->title;
            // Adicionar lógica para deletar/desassociar itens relacionados se necessário
            $process->annotations()->delete();
            $process->tasks()->delete();
            $process->documents()->each(function($doc){
                if ($doc->path && Storage::disk('public')->exists($doc->path)) {
                    Storage::disk('public')->delete($doc->path);
                }
                $doc->delete();
            });
            $process->delete();
            DB::commit();
            return Redirect::route('processes.index')
                           ->with('success', "Caso '{$processTitle}' excluído com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir caso/processo {$process->id}: " . $e->getMessage());
            return Redirect::route('processes.index')
                           ->with('error', 'Ocorreu um erro ao excluir o caso.');
        }
    }
}
