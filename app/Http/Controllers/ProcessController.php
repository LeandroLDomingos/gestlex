<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\User;
use App\Models\Contact;
// Removido: use App\Models\Workflow; // Não estamos usando um modelo Workflow separado
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class ProcessController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $search = $request->input('search', '');
        $workflowFilter = $request->input('workflow');
        $stageFilter = $request->input('stage'); // Assumindo que stage é o ID/key do estágio

        $directSortableColumns = ['title', 'origin', 'negotiated_value', 'workflow', 'stage', 'priority', 'status', 'due_date', 'created_at', 'updated_at'];
        $relationSortableColumns = ['contact.name', 'responsible.name']; // Corrigido para responsible_id -> users.name
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
            ->when($search, function ($query, $searchTerm) {
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
            })
            ->when($workflowFilter, function ($query, $workflowKey) {
                return $query->where('workflow', $workflowKey);
            })
            ->when($stageFilter && $workflowFilter, function ($query, $stageKey) { // stageFilter é a key do estágio
                return $query->where('stage', $stageKey);
            });

        if (in_array($sortBy, ['title', 'origin', 'workflow'])) {
            $processesQuery->orderByRaw("LOWER({$sortBy}) {$sortDirection}");
        } elseif ($sortBy === 'contact.name') {
            $processesQuery->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')
                           ->orderBy('contacts.name', $sortDirection)
                           ->select('processes.*');
        } elseif ($sortBy === 'responsible.name') { // Corrigido para responsible.name
            $processesQuery->leftJoin('users', 'processes.responsible_id', '=', 'users.id') // Assumindo que a FK é responsible_id
                           ->orderBy('users.name', $sortDirection)
                           ->select('processes.*');
        } elseif (in_array($sortBy, $directSortableColumns)) {
            $processesQuery->orderBy($sortBy, $sortDirection);
        }

        $processes = $processesQuery->paginate(15)->withQueryString();

        // Preparar dados de workflows e estágios para os filtros do frontend
        $availableWorkflowsForFilter = collect(Process::WORKFLOWS)->map(function ($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values()->all();

        $allStagesForFilter = [];
        if ($workflowFilter && array_key_exists($workflowFilter, Process::WORKFLOWS)) {
            $allStagesForFilter = collect(Process::getStagesForWorkflow($workflowFilter))
                ->map(function ($label, $key) {
                    return ['key' => $key, 'label' => $label];
                })->values()->all();
        }


        return Inertia::render('processes/Index', [
            'processes' => $processes,
            'filters' => $request->only(['search', 'workflow', 'stage', 'sort_by', 'sort_direction']),
            'availableWorkflows' => $availableWorkflowsForFilter,
            'currentWorkflowStages' => $allStagesForFilter, // Estágios do workflow atualmente filtrado
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

        // Preparar dados de workflows e estágios para o formulário de criação
        $availableWorkflows = collect(Process::WORKFLOWS)->map(function ($label, $key) {
            return ['key' => $key, 'label' => $label];
        })->values()->all();

        $allStages = [];
        foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
            $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                ->map(function ($label, $key) {
                    return ['key' => (int)$key, 'label' => $label]; // Garante que a chave do estágio seja integer
                })->values()->all();
        }

        return Inertia::render('processes/Create', [
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => $contact ? ($contact->name ?: $contact->business_name) : null,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows, // Passa os workflows formatados
            'allStages' => $allStages, // Passa todos os estágios agrupados por workflow
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id', // Corrigido para responsible_id
            'workflow' => ['required', 'string', Rule::in(array_keys(Process::WORKFLOWS))],
            'stage' => ['required', 'integer'], // Valida que 'stage' é um inteiro
            'due_date' => 'nullable|date_format:Y-m-d',
            'priority' => 'required|in:low,medium,high',
            'origin' => 'nullable|string|max:100',
            'negotiated_value' => 'nullable|numeric|min:0',
        ]);

        // Validação adicional para garantir que o estágio pertença ao workflow selecionado
        $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
        if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
            return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
        }
        
        // Adicionar status padrão se não for enviado ou se depender do estágio/workflow
        // $validatedData['status'] = $validatedData['status'] ?? 'Aberto';


        DB::beginTransaction();
        try {
            $process = Process::create($validatedData);
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

        // Os acessores getWorkflowLabelAttribute e getStageLabelAttribute no modelo Process
        // já devem adicionar 'workflow_label' e 'stage_label' à serialização do modelo.
        // Se não estiverem aparecendo, certifique-se que estão no array $appends do modelo Process.

        return Inertia::render('processes/Show', [
            'process' => $process,
            'users' => User::orderBy('name')->get(['id', 'name']),
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

        return Inertia::render('processes/Edit', [
            'process' => $process,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
        ]);
    }

    public function update(Request $request, Process $process)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id', // Corrigido para responsible_id
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
            $process->update($validatedData);
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

    public function destroy(Process $process)
    {
        DB::beginTransaction();
        try {
            $processTitle = $process->title;
            // Adicionar lógica para deletar/desassociar itens relacionados se necessário
            // $process->annotations()->delete();
            // $process->tasks()->delete();
            // $process->documents()->each(function($doc){ /* delete file and record */ });
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
