<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\ProcessDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Contact;
use App\Models\ProcessAnnotation;
use App\Models\ProcessPayment; // Adicione a importação do novo model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
    /**
     * Aplica os filtros de busca comuns à query de Processos.
     */
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
                    // Busca também na soma dos pagamentos ou em pagamentos individuais
                    // Se você quiser buscar por um valor exato em algum pagamento:
                    $q->orWhereHas('payments', function($paymentQuery) use ($searchTerm) {
                        $paymentQuery->where('amount', '=', $searchTerm);
                    });
                    // Se você quiser buscar pela SOMA dos pagamentos (requer que a soma já esteja calculada na query principal, o que pode ser complexo para o filtro aqui)
                    // Para simplificar, a busca por valor numérico aqui se concentrará em pagamentos individuais.
                }
            });
        });
    }

    /**
     * Display a listing of the resource.
     */
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
        $showArchived = $request->boolean('archived', false);

        // Atualize 'negotiated_value' para 'payments_sum_amount' se for usar para ordenação
        $directSortableColumns = ['title', 'origin', 'payments_sum_amount', 'workflow', 'stage', 'priority', 'status', 'due_date', 'created_at', 'updated_at', 'archived_at', 'pending_tasks_count'];
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
            ])
            // Adiciona a soma dos valores dos pagamentos
            ->withSum('payments as payments_sum_amount', 'amount')
            ->withCount([
                'tasks as pending_tasks_count' => function (Builder $query) {
                    $query->whereNotIn('status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED]);
                }
            ])
            ->when($showArchived, function ($query) {
                $query->whereNotNull('archived_at');
            }, function ($query) {
                $query->whereNull('archived_at');
            });

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
                } catch (\Exception $e) {
                    return $query;
                }
            })
            ->when($dateToFilter, function (Builder $query, $dateTo) {
                try {
                    return $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                } catch (\Exception $e) {
                    return $query;
                }
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

        $baseCountQueryForSidebar = function () use ($search, $responsibleFilter, $priorityFilter, $statusFilter, $dateFromFilter, $dateToFilter) {
            $query = Process::query();
            $query = $this->applySearchFilters($query, $search);
            $query
                ->when($responsibleFilter, fn($q, $val) => $q->where('responsible_id', $val))
                ->when($priorityFilter, fn($q, $val) => $q->where('priority', $val))
                ->when($statusFilter, fn($q, $val) => $q->where('status', $val))
                ->when($dateFromFilter, function (Builder $q, $dateFrom) {
                    try {
                        return $q->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    } catch (\Exception $e) {
                        return $q;
                    }
                })
                ->when($dateToFilter, function (Builder $q, $dateTo) {
                    try {
                        return $q->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    } catch (\Exception $e) {
                        return $q;
                    }
                });
            return $query;
        };

        $workflowsData = collect(Process::WORKFLOWS)->map(function ($label, $key) use ($baseCountQueryForSidebar) {
            $countQueryForWorkflow = (clone $baseCountQueryForSidebar())->where('workflow', $key)->whereNull('archived_at');
            return [
                'key' => $key,
                'label' => $label,
                'count' => $countQueryForWorkflow->count(),
                'stages' => collect(Process::getStagesForWorkflow($key))
                    ->map(fn($stageLabel, $stageKey) => ['key' => (int) $stageKey, 'label' => $stageLabel])
                    ->values()->all(),
            ];
        })->values()->all();

        $allProcessesCount = (clone $baseCountQueryForSidebar())->whereNull('archived_at')->count();
        $archivedProcessesCount = (clone $baseCountQueryForSidebar())->whereNotNull('archived_at')->count();

        $currentWorkflowStages = [];
        if ($workflowFilter && array_key_exists($workflowFilter, Process::WORKFLOWS)) {
            $currentWorkflowStages = collect(Process::getStagesForWorkflow($workflowFilter))
                ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])->values()->all();
        }

        $usersForFilter = User::orderBy('name')->get(['id', 'name']);
        $statusesForFilter = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();

        $prioritiesForFilter = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => 'low', 'label' => 'Baixa'], ['key' => 'medium', 'label' => 'Média'], ['key' => 'high', 'label' => 'Alta']];

        return Inertia::render('processes/Index', [
            'processes' => $processes,
            'filters' => $request->only(['search', 'workflow', 'stage', 'responsible_id', 'priority', 'status', 'date_from', 'date_to', 'sort_by', 'sort_direction', 'archived']),
            'workflows' => $workflowsData,
            'currentWorkflowStages' => $currentWorkflowStages,
            'allProcessesCount' => $allProcessesCount,
            'archivedProcessesCount' => $archivedProcessesCount,
            'usersForFilter' => $usersForFilter,
            'statusesForFilter' => $statusesForFilter,
            'prioritiesForFilter' => $prioritiesForFilter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $contactId = $request->query('contact_id');
        $contact = null;
        if ($contactId) {
            $contact = Contact::find($contactId);
        }

        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);

        $availableWorkflows = collect(Process::WORKFLOWS)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
        $allStages = [];
        foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
            $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])->values()->all();
        }

        $availableStatuses = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();

        $availablePriorities = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW, 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM, 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH, 'label' => 'Alta']];
        
        // Você pode querer passar uma lista de métodos de pagamento para o formulário
        $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência', 'Outro']; // Exemplo

        return Inertia::render('processes/Create', [
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => $contact ? ($contact->name ?: $contact->business_name) : null,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'availableStatuses' => $availableStatuses,
            'availablePriorities' => $availablePriorities,
            'paymentMethods' => $paymentMethods, // Envia para o Vue
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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
            'priority' => ['required', Rule::in(array_keys(Process::PRIORITIES ?? [Process::PRIORITY_LOW, Process::PRIORITY_MEDIUM, Process::PRIORITY_HIGH]))],
            'origin' => 'nullable|string|max:100',
            // 'negotiated_value' => 'nullable|numeric|min:0', // REMOVIDO
            'status' => ['nullable', 'string', Rule::in(array_keys(Process::STATUSES ?? []))],
            // Validação para os dados do pagamento (espera que venham como um objeto 'payment' no request)
            'payment.amount' => 'nullable|numeric|min:0|required_with:payment.method', // Se houver método, o valor é obrigatório
            'payment.method' => 'nullable|string|max:100|required_with:payment.amount', // Se houver valor, o método é obrigatório
            'payment.date' => 'nullable|date_format:Y-m-d',
            'payment.notes' => 'nullable|string|max:1000',
        ]);

        $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
        if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
            return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
        }

        $validatedData['status'] = $validatedData['status'] ?? Process::STATUS_OPEN;

        DB::beginTransaction();
        try {
            $processData = collect($validatedData)->except('payment')->all();
            $paymentInput = $validatedData['payment'] ?? null;

            $process = Process::create($processData);

            // Cria o pagamento se um valor ou método foi informado
            if ($paymentInput && (!empty($paymentInput['amount']) || !empty($paymentInput['method']))) {
                $process->payments()->create([
                    'amount' => $paymentInput['amount'] ?? 0, // Default para 0 se não informado mas método sim
                    'payment_method' => $paymentInput['method'] ?? 'Não informado',
                    'payment_date' => $paymentInput['date'] ?? null,
                    'notes' => $paymentInput['notes'] ?? null,
                    'status' => 'pending', // Ou outro status padrão desejado
                ]);
            }

            $process->historyEntries()->create([
                'action' => 'Caso Criado',
                'description' => "O caso \"{$process->title}\" foi criado.",
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

    /**
     * Display the specified resource.
     */
    public function show(Process $process): Response
    {
        $process->load([
            'responsible:id,name',
            'contact:id,name,business_name,type',
            'annotations' => function ($query) {
                $query->with('user:id,name')->latest();
            },
            'historyEntries' => function ($query) {
                $query->with('user:id,name')->latest();
            },
            'tasks' => function ($query) {
                $query->with('responsibleUser:id,name')->orderBy('due_date');
            },
            'documents' => function ($query) {
                $query->with('uploader:id,name')->orderBy('created_at', 'desc');
            },
            'payments' => function ($query) { // Carrega os pagamentos
                $query->orderBy('created_at', 'desc'); // Ou 'payment_date'
            }
        ]);
        $process->append(['workflow_label', 'stage_label', 'priority_label', 'status_label']);

        $availableStages = [];
        if ($process->workflow) {
            $availableStages = collect(Process::getStagesForWorkflow($process->workflow))
                ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])
                ->values()->all();
        }

        $availablePriorities = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW, 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM, 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH, 'label' => 'Alta']];

        $availableStatuses = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();
        
        // Você pode querer passar uma lista de métodos de pagamento para o formulário de edição de pagamento (se houver)
        $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência', 'Outro']; // Exemplo

        return Inertia::render('processes/Show', [
            'process' => $process,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'availableStages' => $availableStages,
            'availablePriorities' => $availablePriorities,
            'availableStatuses' => $availableStatuses,
            'paymentMethods' => $paymentMethods, // Para um possível modal de edição/adição de pagamento
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Process $process): Response
    {
        $process->load(['contact:id,name,business_name,type', 'responsible:id,name', 'payments']); // Carrega o primeiro pagamento para preencher o form
        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);
        $availableWorkflows = collect(Process::WORKFLOWS)->map(function ($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values()->all();
        $allStages = [];
        foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
            $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                ->map(function ($label, $key) {
                    return ['key' => (int) $key, 'label' => $label];
                })->values()->all();
        }

        $statusesForForm = defined('App\Models\Process::STATUSES') ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();

        $prioritiesForForm = defined('App\Models\Process::PRIORITIES') ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => 'low', 'label' => 'Baixa'], ['key' => 'medium', 'label' => 'Média'], ['key' => 'high', 'label' => 'Alta']];

        // Para preencher o formulário de edição com o primeiro pagamento (ou um pagamento principal)
        $currentPayment = $process->payments->first();
         $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência', 'Outro']; // Exemplo


        return Inertia::render('processes/Edit', [
            'process' => $process,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'statusesForForm' => $statusesForForm,
            'prioritiesForForm' => $prioritiesForForm,
            'currentPayment' => $currentPayment, // Envia o pagamento atual para o formulário
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
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
            'priority' => ['required', Rule::in(array_keys(Process::PRIORITIES ?? [Process::PRIORITY_LOW, Process::PRIORITY_MEDIUM, Process::PRIORITY_HIGH]))],
            'origin' => 'nullable|string|max:100',
            // 'negotiated_value' => 'nullable|numeric|min:0', // REMOVIDO
            'status' => ['nullable', 'string', Rule::in(array_keys(Process::STATUSES ?? []))],
            // Validação para os dados do pagamento
            'payment.amount' => 'nullable|numeric|min:0|required_with:payment.method',
            'payment.method' => 'nullable|string|max:100|required_with:payment.amount',
            'payment.date' => 'nullable|date_format:Y-m-d',
            'payment.notes' => 'nullable|string|max:1000',
        ]);

        $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
        if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
            return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $oldData = [
                'stage_label' => $process->stage_label,
                'priority_label' => $process->priority_label,
                'status_label' => $process->status_label,
                // Adicionar outros campos que você quer logar se mudarem
            ];

            $processData = collect($validatedData)->except('payment')->all();
            $paymentInput = $validatedData['payment'] ?? null;

            $process->update($processData);

            if ($paymentInput && (!empty($paymentInput['amount']) || !empty($paymentInput['method']))) {
                // Atualiza o primeiro pagamento ou cria um novo se não existir nenhum.
                // Se você quiser permitir múltiplos pagamentos e ter um formulário para gerenciar eles,
                // a lógica aqui seria mais complexa (ex: sincronizar um array de pagamentos).
                // Para um único "pagamento principal" associado ao processo, updateOrCreate é adequado.
                $process->payments()->updateOrCreate(
                    ['process_id' => $process->id], // Condição para encontrar o pagamento (geralmente o primeiro)
                                                    // Se você tiver apenas um pagamento por processo, isso funciona.
                                                    // Se puder ter múltiplos, você precisaria de um ID do pagamento vindo do form.
                    [
                        'amount' => $paymentInput['amount'] ?? 0,
                        'payment_method' => $paymentInput['method'] ?? 'Não informado',
                        'payment_date' => $paymentInput['date'] ?? null,
                        'notes' => $paymentInput['notes'] ?? null,
                        // O status do pagamento pode precisar de lógica própria para ser atualizado
                    ]
                );
            } elseif (empty($paymentInput['amount']) && empty($paymentInput['method'])) {
                // Se o usuário limpou os campos de pagamento, podemos optar por deletar o pagamento associado
                $process->payments()->delete(); // Isso deletará TODOS os pagamentos. Cuidado se permitir múltiplos.
            }


            $process->refresh();

            $historyDescription = "Caso atualizado por " . auth()->user()->name . ".";
            $specificChanges = [];

            if ($process->wasChanged('stage')) {
                $specificChanges[] = "Estágio alterado de \"{$oldData['stage_label']}\" para \"{$process->stage_label}\".";
            }
            // ... (outros logs de alteração)

            if (!empty($specificChanges)) {
                $historyDescription .= "\nDetalhes:\n- " . implode("\n- ", $specificChanges);
            }

            if ($process->wasChanged() || ($paymentInput && $process->payments->count() > 0 && $process->payments->first()->wasChanged())) { // Verifica se o processo ou o pagamento mudou
                $process->historyEntries()->create([
                    'action' => 'Caso Editado',
                    'description' => $historyDescription,
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
            Log::error("Erro ao atualizar caso/processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao atualizar o caso.');
        }
    }

    /**
     * Remove o Processo (Caso) especificado do banco de dados.
     */
    public function destroy(Process $process)
    {
        DB::beginTransaction();
        try {
            $processTitle = $process->title;

            // Se ProcessPayment usa SoftDeletes e você quer que eles sejam soft-deletados com o processo:
            $process->payments()->delete(); // Adicionado para deletar pagamentos associados

            $process->annotations()->delete();
            $process->historyEntries()->delete();
            $process->tasks()->delete();
            $process->documents()->each(function ($doc) {
                if ($doc->path && Storage::disk('public')->exists($doc->path)) {
                    Storage::disk('public')->delete($doc->path);
                }
                $doc->delete();
            });
            $process->delete(); // Se estiver usando SoftDeletes, isso marcará como deletado

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

    // --- MÉTODOS PARA ATUALIZAÇÕES RÁPIDAS (STAGE, PRIORITY, STATUS) ---
    // Estes métodos permanecem os mesmos, pois não lidam diretamente com o valor negociado.
    public function updateStage(Request $request, Process $process)
    {
        $validated = $request->validate([
            'stage' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($process) {
                    $stagesForWorkflow = Process::getStagesForWorkflow($process->workflow);
                    if (!array_key_exists($value, $stagesForWorkflow)) {
                        $fail("O estágio selecionado não é válido para o workflow '{$process->workflow_label}'.");
                    }
                }
            ],
        ]);

        DB::beginTransaction();
        try {
            $oldStageLabel = $process->stage_label;
            $process->stage = $validated['stage'];
            $process->save();
            $newStageLabel = $process->fresh()->stage_label;

            $process->historyEntries()->create([
                'action' => 'Estágio Alterado',
                'description' => "De \"{$oldStageLabel}\" para \"{$newStageLabel}\".",
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return Redirect::route('processes.show', $process->id)
                ->with('success', 'Estágio do caso atualizado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar estágio do processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao atualizar o estágio.');
        }
    }

    public function updatePriority(Request $request, Process $process)
    {
        $validated = $request->validate([
            'priority' => ['required', 'string', Rule::in(array_keys(Process::PRIORITIES))],
        ]);

        DB::beginTransaction();
        try {
            $oldPriorityLabel = $process->priority_label;
            $process->priority = $validated['priority'];
            $process->save();
            $newPriorityLabel = $process->fresh()->priority_label;

            $process->historyEntries()->create([
                'action' => 'Prioridade Alterada',
                'description' => "De \"{$oldPriorityLabel}\" para \"{$newPriorityLabel}\".",
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return Redirect::route('processes.show', $process->id)
                ->with('success', 'Prioridade do caso atualizada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar prioridade do processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao atualizar a prioridade.');
        }
    }

    public function updateStatus(Request $request, Process $process)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Process::STATUSES))],
        ]);

        DB::beginTransaction();
        try {
            $oldStatusLabel = $process->status_label;
            $process->status = $validated['status'];
            $process->save();
            $newStatusLabel = $process->fresh()->status_label;

            $process->historyEntries()->create([
                'action' => 'Status Alterado',
                'description' => "De \"{$oldStatusLabel}\" para \"{$newStatusLabel}\".",
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return Redirect::route('processes.show', $process->id)
                ->with('success', 'Status do caso atualizado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar status do processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao atualizar o status.');
        }
    }
    
    // --- MÉTODOS DE ARQUIVAMENTO ---
    // Estes métodos permanecem os mesmos.
    public function archive(Request $request, Process $process)
    {
        DB::beginTransaction();
        try {
            if (!$process->isArchived()) {
                $process->archived_at = now();
                $process->save();

                $process->historyEntries()->create([
                    'action' => 'Caso Arquivado',
                    'description' => "O caso \"{$process->title}\" foi arquivado.",
                    'user_id' => auth()->id(),
                ]);
                DB::commit();
                return Redirect::route('processes.index')->with('success', 'Caso arquivado com sucesso.');
            }
            DB::rollBack(); // Se já está arquivado, não há o que fazer, então rollback é apropriado.
            return Redirect::route('processes.show', $process->id)->with('info', 'Este caso já está arquivado.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao arquivar processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao arquivar o caso.');
        }
    }

    public function unarchive(Request $request, Process $process)
    {
        DB::beginTransaction();
        try {
            if ($process->isArchived()) {
                $process->archived_at = null;
                $process->save();

                $process->historyEntries()->create([
                    'action' => 'Caso Restaurado',
                    'description' => "O caso \"{$process->title}\" foi restaurado (desarquivado).",
                    'user_id' => auth()->id(),
                ]);
                DB::commit();
                return Redirect::route('processes.show', $process->id)->with('success', 'Caso restaurado com sucesso.');
            }
            DB::rollBack(); // Se não está arquivado, não há o que fazer.
            return Redirect::route('processes.show', $process->id)->with('info', 'Este caso não está arquivado.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao restaurar processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao restaurar o caso.');
        }
    }

    // --- MÉTODOS PARA ANOTAÇÕES, DOCUMENTOS, TAREFAS ---
    // Estes métodos permanecem os mesmos, pois não lidam diretamente com o valor negociado.
    // Se precisar de ajustes neles devido a outras lógicas, me avise.
    public function storeProcessAnnotation(Request $request, Process $process)
    {
        $data = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            $process->annotations()->create([
                'content' => $data['content'],
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Anotação adicionada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao adicionar anotação ao processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao adicionar anotação.');
        }
    }

    public function destroyProcessAnnotation(Request $request, Process $process, ProcessAnnotation $annotation)
    {
        if ($annotation->process_id !== $process->id) {
            return Redirect::back()->with('error', 'A anotação não pertence a este processo ou não foi encontrada.');
        }
        try {
            $annotation->delete();
            return Redirect::route('processes.show', $process->id)
                ->with('success', 'Anotação excluída com sucesso!');
        } catch (\Exception $e) {
            Log::error("Erro ao excluir anotação {$annotation->id} do processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao excluir a anotação.');
        }
    }
    
    public function storeProcessDocument(Request $request, Process $process)
    {
        $data = $request->validate([
            'file' => 'required|file|max:20480', // Max 20MB
            'description' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("process_documents/{$process->id}", $fileName, 'public');

            if (!$path)
                throw new \Exception("Falha ao armazenar o arquivo.");

            $process->documents()->create([
                'uploader_user_id' => auth()->id(),
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'description' => $data['description'],
            ]);

            $process->historyEntries()->create([
                'action' => 'Documento Adicionado',
                'description' => "Documento \"{$file->getClientOriginalName()}\" foi adicionado ao caso.",
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Documento enviado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao enviar documento para o processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            if (isset($path) && $path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            return back()->with('error', 'Falha ao enviar documento. Verifique o tipo e tamanho do arquivo.');
        }
    }

    public function destroyProcessDocument(Request $request, Process $process, ProcessDocument $document)
    {
        if ($document->process_id !== $process->id) {
            return Redirect::back()->with('error', 'Documento não pertence a este processo.');
        }
        DB::beginTransaction();
        try {
            $documentName = $document->name;
            if ($document->path && Storage::disk('public')->exists($document->path)) {
                Storage::disk('public')->delete($document->path);
            }
            $document->delete();

            $process->historyEntries()->create([
                'action' => 'Documento Excluído',
                'description' => "Documento \"{$documentName}\" foi excluído do caso.",
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Documento excluído com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir documento {$document->id} do processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir documento.');
        }
    }
    
    public function storeProcessTask(Request $request, Process $process)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date_format:Y-m-d',
            'responsible_user_id' => 'nullable|exists:users,id',
            'status' => ['nullable', 'string', Rule::in(array_keys(Task::STATUSES ?? ['Pendente', 'Em Andamento', 'Concluída', 'Cancelada']))],
        ]);

        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível adicionar tarefas a um caso arquivado.');
        }

        DB::beginTransaction();
        try {
            $taskData = $validatedData;
            $taskData['status'] = $validatedData['status'] ?? Task::STATUS_PENDING; // Use a constante do Model Task

            $task = $process->tasks()->create($taskData);

            $description = "Tarefa \"{$task->title}\" adicionada";
            if ($task->responsibleUser) {
                $description .= " e atribuída a {$task->responsibleUser->name}";
            }
            if ($task->due_date) {
                $dueDate = $task->due_date instanceof Carbon ? $task->due_date : Carbon::parse($task->due_date);
                $description .= " com vencimento em " . $dueDate->format('d/m/Y');
            }
            $description .= ".";

            $process->historyEntries()->create([
                'action' => 'Tarefa Adicionada',
                'description' => $description,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Tarefa adicionada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao adicionar tarefa ao processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Falha ao adicionar tarefa.');
        }
    }

    public function updateProcessTask(Request $request, Process $process, Task $task)
    {
        if ($task->process_id !== $process->id) {
            return back()->with('error', 'Tarefa não pertence a este processo.');
        }
        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível modificar tarefas de um caso arquivado.');
        }

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date_format:Y-m-d',
            'responsible_user_id' => 'nullable|exists:users,id',
            'status' => ['required', 'string', Rule::in(array_keys(Task::STATUSES ?? ['Pendente', 'Em Andamento', 'Concluída', 'Cancelada']))],
        ]);

        $validatedData['completed_at'] = ($validatedData['status'] === Task::STATUS_COMPLETED && !$task->completed_at) ? now() : $task->completed_at;
        if ($validatedData['status'] !== Task::STATUS_COMPLETED) {
            $validatedData['completed_at'] = null;
        }

        DB::beginTransaction();
        try {
            $task->update($validatedData);
            $task->refresh();

            $process->historyEntries()->create([
                'action' => 'Tarefa Atualizada',
                'description' => "Tarefa \"{$task->title}\" foi atualizada.",
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Tarefa atualizada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar tarefa {$task->id} do processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao atualizar tarefa.');
        }
    }

    public function destroyProcessTask(Request $request, Process $process, Task $task)
    {
        if ($task->process_id !== $process->id) {
            return back()->with('error', 'Tarefa não pertence a este processo.');
        }
        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível excluir tarefas de um caso arquivado.');
        }

        DB::beginTransaction();
        try {
            $taskTitle = $task->title;
            $task->delete();

            $process->historyEntries()->create([
                'action' => 'Tarefa Excluída',
                'description' => "Tarefa \"{$taskTitle}\" foi excluída do caso.",
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Tarefa excluída com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir tarefa {$task->id} do processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir tarefa.');
        }
    }
}
