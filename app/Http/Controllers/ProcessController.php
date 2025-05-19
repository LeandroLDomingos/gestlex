<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at'); // Mudar padrão para created_at ou last_update
        $sortDirection = $request->input('sort_direction', 'desc'); // Mudar padrão para desc
        $search = $request->input('search', '');
        $workflowFilter = $request->input('workflow'); // Novo filtro
        $stageFilter = $request->input('stage');       // Novo filtro

        $allowedSortColumns = ['title', 'origin', 'negotiated_value', 'workflow', 'stage', 'created_at', 'last_update', 'contact.name', 'responsible.name'];
        // Para 'contact.name' e 'responsible.name', a ordenação no backend precisa de joins ou lógica customizada.
        // Por simplicidade, vamos permitir as colunas diretas do modelo Process por enquanto.
        // Se quiser ordenar por nome do contato/responsável, precisará de uma abordagem mais complexa no orderBy.
        $directSortableColumns = ['title', 'origin', 'negotiated_value', 'workflow', 'stage', 'created_at', 'updated_at'];


        if (!in_array($sortBy, $directSortableColumns)) {
            // Se for uma coluna de relacionamento, não validamos aqui, mas o orderByRaw deve ser usado com cuidado.
            // Para colunas diretas:
            if (!in_array($sortBy, $allowedSortColumns) && in_array($sortBy, $directSortableColumns)) {
                $sortBy = 'created_at';
            } else if (!in_array($sortBy, $allowedSortColumns) && !in_array($sortBy, $directSortableColumns)) {
                $sortBy = 'created_at'; // Fallback seguro
            }
        }


        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $processesQuery = Process::query()
            ->with(['responsible:id,name', 'contact:id,name,business_name']) // Eager load do contato também
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('origin', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('description', 'LIKE', "%{$searchTerm}%")
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
            // Aplicar filtro de WORKFLOW
            ->when($workflowFilter, function ($query, $workflow) {
                return $query->where('workflow', $workflow);
            })
            // Aplicar filtro de STAGE (se workflow também estiver selecionado)
            // A lógica exata para 'stage' pode depender de como você armazena/identifica estágios.
            // Se 'stage' for um nome de estágio (string) ou um ID numérico, ajuste a query.
            ->when($stageFilter && $workflowFilter, function ($query, $stage) {
                // Se 'stage' for um número, como na sua migration:
                return $query->where('stage', $stage);
                // Se 'stage' for um nome e você tiver uma coluna 'stage_name':
                // return $query->where('stage_name', $stage);
            });

        // Lógica de Ordenação
        // Se sortBy for 'contact.name' ou 'responsible.name', precisaria de JOINs para ordenar corretamente no DB.
        // Exemplo simplificado para colunas diretas:
        if (in_array($sortBy, ['title', 'origin', 'workflow'])) {
            $processesQuery->orderByRaw("LOWER({$sortBy}) {$sortDirection}");
        } else if (in_array($sortBy, $directSortableColumns)) {
            $processesQuery->orderBy($sortBy, $sortDirection);
        }
        // Se quiser ordenar por nome do contato (exemplo, requer que a tabela de contatos seja 'contacts'):
        // else if ($sortBy === 'contact.name') {
        //     $processesQuery->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')
        //                    ->orderBy('contacts.name', $sortDirection)
        //                    ->select('processes.*'); // Evitar ambiguidade de colunas
        // }


        $processes = $processesQuery->paginate(15)->withQueryString();

        // Para os filtros da barra lateral (workflow counts e stages), você pode precisar de queries separadas
        // ou calcular isso de forma eficiente. Por simplicidade, vamos mockar no frontend por enquanto.
        // Em uma aplicação real, você passaria esses dados do controller.
        $workflowsData = collect(Process::WORKFLOWS)->map(function ($label, $key) use ($request) {
            return [
                'key' => $key,
                'label' => $label,
                // A contagem aqui seria para todos os processos, não apenas os filtrados pela paginação atual
                // Para uma contagem precisa baseada nos filtros ATUAIS (exceto workflow), a query seria mais complexa
                'count' => Process::where('workflow', $key)
                    ->when($request->input('search'), function ($q, $s) { /* aplicar busca */})
                    ->count(),
                'stages' => collect(Process::getStagesForWorkflow($key))->map(fn($stageLabel, $stageKey) => ['key' => $stageKey, 'label' => $stageLabel])->values()->all(),
            ];
        })->values()->all();


        return Inertia::render('processes/Index', [
            'processes' => $processes,
            'filters' => $request->only(['search', 'workflow', 'stage', 'sort_by', 'sort_direction']),
            'workflows' => $workflowsData, // Passar os dados dos workflows e estágios
        ]);
    }

    public function show(Process $process): Response
    {
        $process->load([
            'responsible:id,name', // Carrega apenas id e nome do responsável
            'contact:id,name,business_name,type', // Carrega dados do contato
            'annotations' => function ($query) {
                $query->with('user:id,name')->latest(); // Carrega anotações com o usuário que criou, ordenadas
            },
            'tasks' => function ($query) {
                $query->with('responsibleUser:id,name')->orderBy('due_date'); // Carrega tarefas com o responsável, ordenadas
            },
            'documents' => function ($query) {
                $query->orderBy('uploaded_at', 'desc');
            },
            // 'history_entries' => function ($query) { // Se tiver histórico
            //     $query->with('user:id,name')->latest();
            // }
        ]);

        // Adicionar acessores ao array do processo se não forem adicionados automaticamente
        // $process->workflow_label = $process->getWorkflowLabelAttribute();
        // $process->stage_label = $process->getStageLabelAttribute();


        return Inertia::render('processes/Show', [
            'process' => $process,
            // 'users' => User::orderBy('name')->get(['id', 'name']), // Para dropdown de responsável em Nova Tarefa
        ]);
    }

}