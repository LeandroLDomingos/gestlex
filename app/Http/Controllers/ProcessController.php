<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\ProcessPayment;
use App\Models\ProcessDocument;
use App\Models\Task;
use App\Models\User;
use App\Models\Contact;
use App\Models\ProcessAnnotation;
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
                    $q->orWhereHas('payments', function ($paymentQuery) use ($searchTerm) {
                        $paymentQuery->where('total_amount', '=', $searchTerm)
                            ->orWhere('down_payment_amount', '=', $searchTerm);
                    });
                }
            });
        });
    }

    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'updated_at');
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

        $directSortableColumns = ['title', 'origin', 'payments_sum_total_amount', 'workflow', 'stage', 'priority', 'status', 'due_date', 'created_at', 'updated_at', 'archived_at', 'pending_tasks_count'];
        $relationSortableColumns = ['contact.name', 'responsible.name'];
        $allowedSortColumns = array_merge($directSortableColumns, $relationSortableColumns);

        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'updated_at';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $processesQuery = Process::query()
            ->with([
                'responsible:id,name',
                'contact:id,name,business_name,type',
                'payments'
            ])
            ->withSum('payments as payments_sum_total_amount', 'total_amount')
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
            ->when($stageFilter && $workflowFilter, fn(Builder $query) => $query->where('stage', $stageFilter))
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

        if (in_array($sortBy, ['title', 'origin', 'workflow', 'stage', 'priority', 'status', 'due_date'])) {
            $processesQuery->orderByRaw("LOWER(CAST({$sortBy} AS TEXT)) {$sortDirection}");
        } elseif ($sortBy === 'contact.name') {
            $processesQuery->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')
                ->orderBy('contacts.name', $sortDirection)
                ->select('processes.*');
        } elseif ($sortBy === 'responsible.name') {
            $processesQuery->leftJoin('users', 'processes.responsible_id', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('processes.*');
        } elseif (in_array($sortBy, $directSortableColumns)) {
            if ($sortBy === 'payments_sum_total_amount') {
                $processesQuery->orderBy('payments_sum_total_amount', $sortDirection);
            } else {
                $processesQuery->orderBy($sortBy, $sortDirection);
            }
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

        $workflowsData = [];
        if (defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS)) {
            $workflowsData = collect(Process::WORKFLOWS)->map(function ($label, $key) use ($baseCountQueryForSidebar) {
                $countQueryForWorkflow = (clone $baseCountQueryForSidebar())
                    ->where('workflow', $key)
                    ->whereNull('archived_at');
                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => $countQueryForWorkflow->count(),
                    'stages' => method_exists(Process::class, 'getStagesForWorkflow') ?
                        collect(Process::getStagesForWorkflow($key))
                            ->map(fn($stageLabel, $stageKey) => ['key' => (int) $stageKey, 'label' => $stageLabel])
                            ->values()->all() : [],
                ];
            })->values()->all();
        }

        $allProcessesCount = (clone $baseCountQueryForSidebar())->whereNull('archived_at')->count();
        $archivedProcessesCount = (clone $baseCountQueryForSidebar())->whereNotNull('archived_at')->count();

        $currentWorkflowStages = [];
        if ($workflowFilter && defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS) && array_key_exists($workflowFilter, Process::WORKFLOWS) && method_exists(Process::class, 'getStagesForWorkflow')) {
            $currentWorkflowStages = collect(Process::getStagesForWorkflow($workflowFilter))
                ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])->values()->all();
        }

        $usersForFilter = User::orderBy('name')->get(['id', 'name']);
        $statusesForFilter = (defined('App\Models\Process::STATUSES') && is_array(Process::STATUSES)) ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();

        $prioritiesForFilter = (defined('App\Models\Process::PRIORITIES') && is_array(Process::PRIORITIES)) ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW ?? 'low', 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM ?? 'medium', 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH ?? 'high', 'label' => 'Alta']];

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

    public function create(Request $request): Response
    {
        $contactId = $request->query('contact_id');
        $contact = null;
        if ($contactId) {
            $contact = Contact::find($contactId);
        }

        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);

        $availableWorkflows = (defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS)) ?
            collect(Process::WORKFLOWS)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() : [];

        $allStages = [];
        if (defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS) && method_exists(Process::class, 'getStagesForWorkflow')) {
            foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
                $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                    ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])->values()->all();
            }
        }

        $availableStatuses = (defined('App\Models\Process::STATUSES') && is_array(Process::STATUSES)) ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();

        $availablePriorities = (defined('App\Models\Process::PRIORITIES') && is_array(Process::PRIORITIES)) ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW ?? 'low', 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM ?? 'medium', 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH ?? 'high', 'label' => 'Alta']];

        $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência Bancária', 'Dinheiro', 'Cheque', 'Outro'];
        $paymentTypes = method_exists(PaymentType::class, 'forFrontend') ?
            collect(PaymentType::cases())
                ->filter(fn($case) => $case->value !== PaymentType::HONORARIO->value)
                ->map(fn($case) => ['value' => $case->value, 'label' => $case->label()])
                ->values()->all() :
            collect(PaymentType::cases())
                ->filter(fn($case) => $case->value !== PaymentType::HONORARIO->value)
                ->map(fn($case) => ['value' => $case->value, 'label' => str_replace('_', ' ', ucfirst(strtolower($case->name)))])
                ->values()->all();

        return Inertia::render('processes/Create', [
            'contact_id' => $contact ? $contact->id : null,
            'contact_name' => $contact ? ($contact->name ?: $contact->business_name) : null,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'availableStatuses' => $availableStatuses,
            'availablePriorities' => $availablePriorities,
            'paymentMethods' => $paymentMethods,
            'paymentTypes' => $paymentTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id',
            'workflow' => ['required', 'string', Rule::in(array_keys(Process::WORKFLOWS ?? []))],
            'stage' => ['required', 'integer'],
            'due_date' => 'nullable|date_format:Y-m-d',
            'priority' => ['required', Rule::in(array_keys(Process::PRIORITIES ?? []))],
            'origin' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in(array_keys(Process::STATUSES ?? []))],
            'payment.total_amount' => 'nullable|numeric|min:0.01|required_with:payment.payment_type',
            'payment.advance_payment_amount' => 'nullable|numeric|min:0|lte:payment.total_amount',
            'payment.payment_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn() => !empty($request->input('payment.total_amount')) && (float) $request->input('payment.total_amount') > 0),
                new EnumRule(PaymentType::class),
                Rule::notIn([PaymentType::HONORARIO->value])
            ],
            'payment.payment_method' => 'nullable|string|max:100',
            'payment.single_payment_date' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    $paymentType = $payment['payment_type'] ?? null;
                    $totalAmount = isset($payment['total_amount']) ? (float) $payment['total_amount'] : 0;
                    $advanceAmount = isset($payment['advance_payment_amount']) ? (float) $payment['advance_payment_amount'] : 0;
                    $isFullPaymentAtOnce = ($paymentType === PaymentType::A_VISTA->value && $totalAmount > 0 && $advanceAmount == 0);
                    $hasAdvancePayment = ($advanceAmount > 0);
                    return $isFullPaymentAtOnce || $hasAdvancePayment;
                }),
            ],
            'payment.number_of_installments' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    return ($payment['payment_type'] ?? null) === PaymentType::PARCELADO->value &&
                        ((float) ($payment['total_amount'] ?? 0) - (float) ($payment['advance_payment_amount'] ?? 0)) > 0;
                }),
            ],
            'payment.first_installment_due_date' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    return ($payment['payment_type'] ?? null) === PaymentType::PARCELADO->value &&
                        ((float) ($payment['total_amount'] ?? 0) - (float) ($payment['advance_payment_amount'] ?? 0)) > 0;
                }),
            ],
            'payment.notes' => 'nullable|string|max:1000',
        ]);

        if (isset(Process::WORKFLOWS[$validatedData['workflow']])) {
            $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
            if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
                return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
            }
        } else {
            return back()->withErrors(['workflow' => 'O workflow selecionado é inválido.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $processData = collect($validatedData)->except('payment')->all();
            $process = Process::create($processData);
            $paymentInput = $validatedData['payment'] ?? null;

            if ($paymentInput && isset($paymentInput['total_amount']) && (float) $paymentInput['total_amount'] > 0 && isset($paymentInput['payment_type'])) {
                $purchaseTotalAmount = (float) $paymentInput['total_amount'];
                $downPaymentAmountFromInput = isset($paymentInput['advance_payment_amount']) ? (float) $paymentInput['advance_payment_amount'] : 0;
                $paymentTypeFromInput = PaymentType::from($paymentInput['payment_type']);
                $paymentMethodFromInput = $paymentInput['payment_method'] ?? null;
                $notesFromInput = $paymentInput['notes'] ?? null;
                $dateForEntryOrSinglePayment = $paymentInput['single_payment_date'] ?? null;
                $amountToFinance = $purchaseTotalAmount - $downPaymentAmountFromInput;

                if ($downPaymentAmountFromInput > 0) {
                    $process->payments()->create([
                        'id' => Str::uuid()->toString(),
                        'total_amount' => $downPaymentAmountFromInput,
                        'down_payment_amount' => $downPaymentAmountFromInput,
                        'payment_type' => $paymentTypeFromInput,
                        'payment_method' => $paymentMethodFromInput,
                        'down_payment_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                        'number_of_installments' => ($paymentTypeFromInput === PaymentType::PARCELADO && $amountToFinance > 0) ? (int) ($paymentInput['number_of_installments'] ?? 1) : 1,
                        'value_of_installment' => $downPaymentAmountFromInput,
                        'status' => ProcessPayment::STATUS_PAID,
                        'first_installment_due_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                        'notes' => $notesFromInput ? $notesFromInput . ' (Entrada)' : 'Entrada do pagamento',
                    ]);
                }

                if ($amountToFinance > 0) {
                    if ($paymentTypeFromInput === PaymentType::A_VISTA) {
                        $process->payments()->create([
                            'id' => Str::uuid()->toString(),
                            'total_amount' => $amountToFinance,
                            'down_payment_amount' => 0,
                            'payment_type' => $paymentTypeFromInput,
                            'payment_method' => $paymentMethodFromInput,
                            'down_payment_date' => null,
                            'number_of_installments' => 1,
                            'value_of_installment' => $amountToFinance,
                            'status' => ProcessPayment::STATUS_PENDING,
                            'first_installment_due_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                            'notes' => $notesFromInput ?? 'Pagamento à vista (restante)',
                        ]);
                    } elseif ($paymentTypeFromInput === PaymentType::PARCELADO) {
                        $numberOfInstallmentsForFinancing = (int) ($paymentInput['number_of_installments'] ?? 1);
                        if ($numberOfInstallmentsForFinancing <= 0)
                            $numberOfInstallmentsForFinancing = 1;
                        $baseInstallmentValue = round($amountToFinance / $numberOfInstallmentsForFinancing, 2);
                        $currentDueDate = Carbon::parse($paymentInput['first_installment_due_date']);

                        for ($i = 1; $i <= $numberOfInstallmentsForFinancing; $i++) {
                            $currentInstallmentValue = $baseInstallmentValue;
                            if ($i === $numberOfInstallmentsForFinancing) {
                                $sumOfPreviousInstallments = round($baseInstallmentValue * ($numberOfInstallmentsForFinancing - 1), 2);
                                $currentInstallmentValue = round($amountToFinance - $sumOfPreviousInstallments, 2);
                            }
                            $parcelSpecificNotes = "Parcela {$i} de {$numberOfInstallmentsForFinancing}";
                            if ($notesFromInput) {
                                $parcelSpecificNotes = $notesFromInput . " ({$parcelSpecificNotes})";
                            }
                            $process->payments()->create([
                                'id' => Str::uuid()->toString(),
                                'total_amount' => $currentInstallmentValue,
                                'down_payment_amount' => 0,
                                'payment_type' => $paymentTypeFromInput,
                                'payment_method' => $paymentMethodFromInput,
                                'down_payment_date' => null,
                                'number_of_installments' => $numberOfInstallmentsForFinancing,
                                'value_of_installment' => $currentInstallmentValue,
                                'status' => ProcessPayment::STATUS_PENDING,
                                'first_installment_due_date' => $currentDueDate->copy()->toDateString(),
                                'notes' => $parcelSpecificNotes,
                            ]);
                            if ($i < $numberOfInstallmentsForFinancing) {
                                $currentDueDate->addMonthNoOverflow();
                            }
                        }
                    }
                } elseif ($purchaseTotalAmount > 0 && $downPaymentAmountFromInput === $purchaseTotalAmount) {
                    $entryPaymentRecord = $process->payments()
                        ->where('down_payment_amount', $downPaymentAmountFromInput)
                        ->where('total_amount', $downPaymentAmountFromInput)
                        ->latest()->first();
                    if ($entryPaymentRecord && $entryPaymentRecord->number_of_installments > 1) {
                        $entryPaymentRecord->update([
                            'number_of_installments' => 1,
                            'notes' => ($entryPaymentRecord->notes ?? '') . ' (Quitado integralmente com entrada)'
                        ]);
                    }
                }
            }

            $process->historyEntries()->create([
                'action' => 'Caso Criado',
                'description' => "O caso \"{$process->title}\" foi criado.",
                'user_id' => Auth::id(),
            ]);
            DB::commit();
            return Redirect::route('processes.show', $process->id)->with('success', 'Caso criado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao criar caso/processo: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao criar o caso: ' . $e->getMessage());
        }
    }

    public function storeFee(Request $request, Process $process)
    {
        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível adicionar honorários a um caso arquivado.');
        }

        $validatedData = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'fee_date' => 'required|date_format:Y-m-d',      // Data de Vencimento do Honorário
            'payment_method' => 'nullable|string|max:100',
            'is_paid' => 'required|boolean',                  // Se o honorário foi pago
            'payment_date' => 'nullable|date_format:Y-m-d|required_if:is_paid,true', // Data do Pagamento (obrigatória se is_paid for true)
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $status = $validatedData['is_paid'] ? ProcessPayment::STATUS_PAID : ProcessPayment::STATUS_PENDING;
            $paymentDate = $validatedData['is_paid'] && !empty($validatedData['payment_date']) ? Carbon::parse($validatedData['payment_date']) : null;

            // Concatenar descrição e notas, se houver notas adicionais
            $finalNotes = $validatedData['description'];
            if (!empty($validatedData['notes'])) {
                $finalNotes .= "\nObservações Adicionais: " . $validatedData['notes'];
            }

            $process->payments()->create([
                'id' => Str::uuid()->toString(),
                'total_amount' => (float) $validatedData['amount'],
                'down_payment_amount' => 0, // Honorários não têm "entrada" neste contexto
                'payment_type' => PaymentType::HONORARIO,
                'payment_method' => $validatedData['payment_method'],
                'down_payment_date' => $paymentDate, // Usamos down_payment_date para a data de pagamento efetivo do honorário
                'number_of_installments' => 1,
                'value_of_installment' => (float) $validatedData['amount'],
                'status' => $status,
                'first_installment_due_date' => Carbon::parse($validatedData['fee_date']), // Data de Vencimento do honorário
                'notes' => $finalNotes,
            ]);

            $process->historyEntries()->create([
                'action' => 'Honorários Adicionados',
                'description' => "Honorários '{$validatedData['description']}' no valor de " . number_format($validatedData['amount'], 2, ',', '.') . " adicionados.",
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            return Redirect::route('processes.show', $process->id)->with('success', 'Honorários adicionados com sucesso!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Para Inertia, é melhor retornar com erros para o modal
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao adicionar honorários ao processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Ocorreu um erro inesperado ao adicionar os honorários: ' . $e->getMessage());
        }
    }

    // NOVO MÉTODO PARA ATUALIZAR HONORÁRIOS
    public function updateFee(Request $request, Process $process, ProcessPayment $fee) // Route Model Binding para o ProcessPayment
    {
        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível editar honorários de um caso arquivado.');
        }

        // Garantir que o "pagamento" que estamos editando é de fato um honorário deste processo
        if ($fee->process_id !== $process->id || $fee->payment_type !== PaymentType::HONORARIO) {
            Log::warning("Tentativa de editar honorário inválido. Processo ID: {$process->id}, Fee ID: {$fee->id}, Fee Type: {$fee->payment_type}");
            return back()->with('error', 'Honorário não encontrado ou inválido para este processo.');
        }

        $validatedData = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'fee_date' => 'required|date_format:Y-m-d',      // Data de Vencimento do Honorário
            'payment_method' => 'nullable|string|max:100',
            'is_paid' => 'required|boolean',                  // Se o honorário foi pago
            'payment_date' => 'nullable|date_format:Y-m-d|required_if:is_paid,true', // Data do Pagamento
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $status = $validatedData['is_paid'] ? ProcessPayment::STATUS_PAID : ProcessPayment::STATUS_PENDING;
            $paymentDate = $validatedData['is_paid'] && !empty($validatedData['payment_date']) ? Carbon::parse($validatedData['payment_date']) : null;

            $finalNotes = $validatedData['description'];
            if (!empty($validatedData['notes'])) {
                // Se a descrição original já estava nas notas, evitamos duplicar.
                // Uma lógica mais robusta poderia ser necessária se as notas originais fossem complexas.
                if (strpos($fee->notes ?? '', $validatedData['description']) === false) {
                    $finalNotes = $validatedData['description'];
                } else {
                    $finalNotes = $fee->notes; // Mantém as notas originais se a descrição já estiver lá
                }
                // Adiciona as novas notas se houver
                if (!empty($validatedData['notes']) && $validatedData['notes'] !== $validatedData['description']) {
                    $finalNotes = $validatedData['description'] . "\nObservações Adicionais: " . $validatedData['notes'];
                } elseif (empty($validatedData['notes'])) {
                    $finalNotes = $validatedData['description'];
                }
            }


            $fee->update([
                'total_amount' => (float) $validatedData['amount'],
                'value_of_installment' => (float) $validatedData['amount'],
                'payment_method' => $validatedData['payment_method'],
                'down_payment_date' => $paymentDate, // Data do pagamento efetivo
                'status' => $status,
                'first_installment_due_date' => Carbon::parse($validatedData['fee_date']), // Data de Vencimento
                'notes' => $finalNotes,
            ]);

            $process->historyEntries()->create([
                'action' => 'Honorários Atualizados',
                'description' => "Honorários '{$validatedData['description']}' (ID: {$fee->id}) atualizados para o valor de " . number_format($validatedData['amount'], 2, ',', '.'),
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            return Redirect::route('processes.show', $process->id)->with('success', 'Honorários atualizados com sucesso!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar honorários (ID: {$fee->id}) do processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Ocorreu um erro inesperado ao atualizar os honorários: ' . $e->getMessage());
        }
    }

    // NOVO MÉTODO PARA EXCLUIR HONORÁRIOS
    public function destroyFee(Request $request, Process $process, ProcessPayment $fee)
    {
        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível excluir honorários de um caso arquivado.');
        }

        if ($fee->process_id !== $process->id || $fee->payment_type !== PaymentType::HONORARIO) {
            Log::warning("Tentativa de excluir honorário inválido. Processo ID: {$process->id}, Fee ID: {$fee->id}, Fee Type: {$fee->payment_type}");
            return back()->with('error', 'Honorário não encontrado ou inválido para este processo.');
        }

        DB::beginTransaction();
        try {
            $feeDescription = $fee->notes ?? "Honorário ID {$fee->id}"; // Usa a nota como descrição ou o ID
            $feeAmount = $fee->total_amount;

            $fee->delete(); // Soft delete, se configurado no model, ou forceDelete() para remover permanentemente

            $process->historyEntries()->create([
                'action' => 'Honorários Excluídos',
                'description' => "Honorários '{$feeDescription}' no valor de " . number_format($feeAmount, 2, ',', '.') . " foram excluídos.",
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            return Redirect::route('processes.show', $process->id)->with('success', 'Honorários excluídos com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir honorários (ID: {$fee->id}) do processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Ocorreu um erro ao excluir os honorários.');
        }
    }


    public function edit(Process $process): Response
    {
        $process->load([
            'contact:id,name,business_name,type',
            'responsible:id,name',
            'payments' => fn($query) => $query->orderBy('first_installment_due_date', 'asc')->orderBy('created_at', 'asc')
        ]);
        $process->payments->each->append('status_label');

        $users = User::orderBy('name')->get(['id', 'name']);
        $contacts = Contact::orderBy('name')->get(['id', 'name', 'business_name', 'type']);
        $availableWorkflows = (defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS)) ?
            collect(Process::WORKFLOWS)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() : [];
        $allStages = [];
        if (defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS) && method_exists(Process::class, 'getStagesForWorkflow')) {
            foreach (array_keys(Process::WORKFLOWS) as $workflowKey) {
                $allStages[$workflowKey] = collect(Process::getStagesForWorkflow($workflowKey))
                    ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])->values()->all();
            }
        }
        $statusesForForm = (defined('App\Models\Process::STATUSES') && is_array(Process::STATUSES)) ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            Process::select('status')->distinct()->whereNotNull('status')->where('status', '!=', '')->orderBy('status')->get()->pluck('status')->map(fn($s) => ['key' => $s, 'label' => ucfirst((string) $s)])->all();
        $prioritiesForForm = (defined('App\Models\Process::PRIORITIES') && is_array(Process::PRIORITIES)) ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW ?? 'low', 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM ?? 'medium', 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH ?? 'high', 'label' => 'Alta']];

        $paymentDataForForm = [
            'total_amount' => null,
            'advance_payment_amount' => null,
            'payment_type' => 'a_vista',
            'payment_method' => null,
            'single_payment_date' => null,
            'number_of_installments' => null,
            'first_installment_due_date' => null,
            'notes' => null,
        ];

        if ($process->payments && $process->payments->isNotEmpty()) {
            $regularPayments = $process->payments->filter(fn($p) => $p->payment_type !== PaymentType::HONORARIO->value);
            if ($regularPayments->isNotEmpty()) {
                $totalContractValue = $regularPayments->sum('total_amount');
                $entryPayment = $regularPayments->firstWhere('down_payment_amount', '>', 0);

                $paymentDataForForm['total_amount'] = $totalContractValue;
                if ($entryPayment) {
                    $paymentDataForForm['advance_payment_amount'] = $entryPayment->down_payment_amount;
                    $paymentDataForForm['single_payment_date'] = $entryPayment->down_payment_date ? Carbon::parse($entryPayment->down_payment_date)->toDateString() : ($entryPayment->first_installment_due_date ? Carbon::parse($entryPayment->first_installment_due_date)->toDateString() : null);
                    $paymentDataForForm['payment_method'] = $entryPayment->payment_method;
                    $paymentDataForForm['notes'] = $entryPayment->notes;
                }

                $firstInstallment = $regularPayments->where('down_payment_amount', 0)->sortBy('first_installment_due_date')->first();
                if ($firstInstallment) {
                    $paymentDataForForm['payment_type'] = $firstInstallment->payment_type instanceof PaymentType ? $firstInstallment->payment_type->value : $firstInstallment->payment_type;
                    $paymentDataForForm['number_of_installments'] = $firstInstallment->number_of_installments;
                    $paymentDataForForm['first_installment_due_date'] = $firstInstallment->first_installment_due_date ? Carbon::parse($firstInstallment->first_installment_due_date)->toDateString() : null;
                    if (!$entryPayment) {
                        $paymentDataForForm['payment_method'] = $firstInstallment->payment_method;
                        if (is_null($paymentDataForForm['notes']))
                            $paymentDataForForm['notes'] = $firstInstallment->notes;
                    }
                } elseif ($entryPayment && !$firstInstallment) {
                    $paymentDataForForm['payment_type'] = $entryPayment->payment_type instanceof PaymentType ? $entryPayment->payment_type->value : $entryPayment->payment_type;
                    if ($paymentDataForForm['payment_type'] === PaymentType::A_VISTA->value) {
                        $paymentDataForForm['number_of_installments'] = 1;
                    }
                }
            }
        }

        $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência Bancária', 'Dinheiro', 'Cheque', 'Outro'];
        $paymentTypes = method_exists(PaymentType::class, 'forFrontend') ?
            collect(PaymentType::cases())
                ->filter(fn($case) => $case->value !== PaymentType::HONORARIO->value)
                ->map(fn($case) => ['value' => $case->value, 'label' => $case->label()])
                ->values()->all() :
            collect(PaymentType::cases())
                ->filter(fn($case) => $case->value !== PaymentType::HONORARIO->value)
                ->map(fn($case) => ['value' => $case->value, 'label' => str_replace('_', ' ', ucfirst(strtolower($case->name)))])
                ->values()->all();

        return Inertia::render('processes/Edit', [
            'process' => $process,
            'users' => $users,
            'contactsList' => $contacts,
            'availableWorkflows' => $availableWorkflows,
            'allStages' => $allStages,
            'statusesForForm' => $statusesForForm,
            'prioritiesForForm' => $prioritiesForForm,
            'currentPaymentData' => $paymentDataForForm,
            'paymentMethods' => $paymentMethods,
            'paymentTypes' => $paymentTypes,
        ]);
    }

    public function update(Request $request, Process $process)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'contact_id' => 'required|exists:contacts,id',
            'responsible_id' => 'nullable|exists:users,id',
            'workflow' => ['required', 'string', Rule::in(array_keys(Process::WORKFLOWS ?? []))],
            'stage' => ['required', 'integer'],
            'due_date' => 'nullable|date_format:Y-m-d',
            'priority' => ['required', Rule::in(array_keys(Process::PRIORITIES ?? []))],
            'origin' => 'nullable|string|max:100',
            'status' => ['nullable', 'string', Rule::in(array_keys(Process::STATUSES ?? []))],
            'payment.total_amount' => 'nullable|numeric|min:0.01|required_with:payment.payment_type',
            'payment.advance_payment_amount' => 'nullable|numeric|min:0|lte:payment.total_amount',
            'payment.payment_type' => [
                'nullable',
                'string',
                Rule::requiredIf(fn() => !empty($request->input('payment.total_amount')) && (float) $request->input('payment.total_amount') > 0),
                new EnumRule(PaymentType::class),
                Rule::notIn([PaymentType::HONORARIO->value])
            ],
            'payment.payment_method' => 'nullable|string|max:100',
            'payment.single_payment_date' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    $paymentType = $payment['payment_type'] ?? null;
                    $totalAmount = isset($payment['total_amount']) ? (float) $payment['total_amount'] : 0;
                    $advanceAmount = isset($payment['advance_payment_amount']) ? (float) $payment['advance_payment_amount'] : 0;
                    $isFullPaymentAtOnce = ($paymentType === PaymentType::A_VISTA->value && $totalAmount > 0 && $advanceAmount == 0);
                    $hasAdvancePayment = ($advanceAmount > 0);
                    return $isFullPaymentAtOnce || $hasAdvancePayment;
                }),
            ],
            'payment.number_of_installments' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    return ($payment['payment_type'] ?? null) === PaymentType::PARCELADO->value &&
                        ((float) ($payment['total_amount'] ?? 0) - (float) ($payment['advance_payment_amount'] ?? 0)) > 0;
                }),
            ],
            'payment.first_installment_due_date' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::requiredIf(function () use ($request) {
                    $payment = $request->input('payment', []);
                    return ($payment['payment_type'] ?? null) === PaymentType::PARCELADO->value &&
                        ((float) ($payment['total_amount'] ?? 0) - (float) ($payment['advance_payment_amount'] ?? 0)) > 0;
                }),
            ],
            'payment.notes' => 'nullable|string|max:1000',
        ]);

        if (isset(Process::WORKFLOWS[$validatedData['workflow']])) {
            $stagesForSelectedWorkflow = Process::getStagesForWorkflow($validatedData['workflow']);
            if (!array_key_exists($validatedData['stage'], $stagesForSelectedWorkflow)) {
                return back()->withErrors(['stage' => 'O estágio selecionado não é válido para o workflow escolhido.'])->withInput();
            }
        } else {
            return back()->withErrors(['workflow' => 'O workflow selecionado é inválido.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $processData = collect($validatedData)->except('payment')->all();
            $process->update($processData);
            $process->payments()->where('payment_type', '!=', PaymentType::HONORARIO->value)->forceDelete();
            $paymentInput = $validatedData['payment'] ?? null;

            if ($paymentInput && isset($paymentInput['total_amount']) && (float) $paymentInput['total_amount'] > 0 && isset($paymentInput['payment_type'])) {
                $purchaseTotalAmount = (float) $paymentInput['total_amount'];
                $downPaymentAmountFromInput = isset($paymentInput['advance_payment_amount']) ? (float) $paymentInput['advance_payment_amount'] : 0;
                $paymentTypeFromInput = PaymentType::from($paymentInput['payment_type']);
                $paymentMethodFromInput = $paymentInput['payment_method'] ?? null;
                $notesFromInput = $paymentInput['notes'] ?? null;
                $dateForEntryOrSinglePayment = $paymentInput['single_payment_date'] ?? null;
                $amountToFinance = $purchaseTotalAmount - $downPaymentAmountFromInput;

                if ($downPaymentAmountFromInput > 0) {
                    $process->payments()->create([
                        'id' => Str::uuid()->toString(),
                        'total_amount' => $downPaymentAmountFromInput,
                        'down_payment_amount' => $downPaymentAmountFromInput,
                        'payment_type' => $paymentTypeFromInput,
                        'payment_method' => $paymentMethodFromInput,
                        'down_payment_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                        'number_of_installments' => ($paymentTypeFromInput === PaymentType::PARCELADO && $amountToFinance > 0) ? (int) ($paymentInput['number_of_installments'] ?? 1) : 1,
                        'value_of_installment' => $downPaymentAmountFromInput,
                        'status' => ProcessPayment::STATUS_PAID,
                        'first_installment_due_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                        'notes' => $notesFromInput ? $notesFromInput . ' (Entrada)' : 'Entrada do pagamento (Atualizado)',
                    ]);
                }

                if ($amountToFinance > 0) {
                    if ($paymentTypeFromInput === PaymentType::A_VISTA) {
                        $process->payments()->create([
                            'id' => Str::uuid()->toString(),
                            'total_amount' => $amountToFinance,
                            'down_payment_amount' => 0,
                            'payment_type' => $paymentTypeFromInput,
                            'payment_method' => $paymentMethodFromInput,
                            'down_payment_date' => null,
                            'number_of_installments' => 1,
                            'value_of_installment' => $amountToFinance,
                            'status' => ProcessPayment::STATUS_PENDING,
                            'first_installment_due_date' => $dateForEntryOrSinglePayment ? Carbon::parse($dateForEntryOrSinglePayment) : null,
                            'notes' => $notesFromInput ?? 'Pagamento à vista (restante - Atualizado)',
                        ]);
                    } elseif ($paymentTypeFromInput === PaymentType::PARCELADO) {
                        $numberOfInstallmentsForFinancing = (int) ($paymentInput['number_of_installments'] ?? 1);
                        if ($numberOfInstallmentsForFinancing <= 0)
                            $numberOfInstallmentsForFinancing = 1;
                        $baseInstallmentValue = round($amountToFinance / $numberOfInstallmentsForFinancing, 2);
                        $currentDueDate = Carbon::parse($paymentInput['first_installment_due_date']);
                        for ($i = 1; $i <= $numberOfInstallmentsForFinancing; $i++) {
                            $currentInstallmentValue = $baseInstallmentValue;
                            if ($i === $numberOfInstallmentsForFinancing) {
                                $sumOfPreviousInstallments = round($baseInstallmentValue * ($numberOfInstallmentsForFinancing - 1), 2);
                                $currentInstallmentValue = round($amountToFinance - $sumOfPreviousInstallments, 2);
                            }
                            $parcelSpecificNotes = "Parcela {$i} de {$numberOfInstallmentsForFinancing}";
                            if ($notesFromInput) {
                                $parcelSpecificNotes = $notesFromInput . " ({$parcelSpecificNotes})";
                            }
                            $process->payments()->create([
                                'id' => Str::uuid()->toString(),
                                'total_amount' => $currentInstallmentValue,
                                'down_payment_amount' => 0,
                                'payment_type' => $paymentTypeFromInput,
                                'payment_method' => $paymentMethodFromInput,
                                'down_payment_date' => null,
                                'number_of_installments' => $numberOfInstallmentsForFinancing,
                                'value_of_installment' => $currentInstallmentValue,
                                'status' => ProcessPayment::STATUS_PENDING,
                                'first_installment_due_date' => $currentDueDate->copy()->toDateString(),
                                'notes' => $parcelSpecificNotes . ' (Atualizado)',
                            ]);
                            if ($i < $numberOfInstallmentsForFinancing) {
                                $currentDueDate->addMonthNoOverflow();
                            }
                        }
                    }
                } elseif ($purchaseTotalAmount > 0 && $downPaymentAmountFromInput === $purchaseTotalAmount) {
                    $entryPaymentRecord = $process->payments()
                        ->where('down_payment_amount', $downPaymentAmountFromInput)
                        ->where('total_amount', $downPaymentAmountFromInput)
                        ->latest()->first();
                    if ($entryPaymentRecord && $entryPaymentRecord->number_of_installments > 1) {
                        $entryPaymentRecord->update([
                            'number_of_installments' => 1,
                            'notes' => ($entryPaymentRecord->notes ?? '') . ' (Quitado integralmente com entrada - Atualizado)'
                        ]);
                    }
                }
            }

            if ($process->wasChanged() || !empty($paymentInput)) {
                $process->historyEntries()->create([
                    'action' => 'Caso Editado',
                    'description' => "O caso \"{$process->title}\" foi atualizado.",
                    'user_id' => Auth::id(),
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
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao atualizar o caso: ' . $e->getMessage());
        }
    }

    public function show(Process $process): Response
    {
        $process->load([
            'responsible:id,name',
            'contact:id,name,business_name,type',
            'annotations' => fn($query) => $query->with('user:id,name')->latest(),
            'historyEntries' => fn($query) => $query->with('user:id,name')->latest(),
            'tasks' => fn($query) => $query->with('responsibleUser:id,name')->orderBy('due_date'),
            'documents' => fn($query) => $query->with('uploader:id,name')->orderBy('created_at', 'desc'),
            'payments' => fn($query) => $query->orderBy('first_installment_due_date', 'asc')->orderBy('created_at', 'asc')
        ]);
        $process->payments->each->append('status_label');

        if (method_exists($process, 'getWorkflowLabelAttribute'))
            $process->append('workflow_label');
        if (method_exists($process, 'getStageLabelAttribute'))
            $process->append('stage_label');
        if (method_exists($process, 'getPriorityLabelAttribute'))
            $process->append('priority_label');
        if (method_exists($process, 'getStatusLabelAttribute'))
            $process->append('status_label');

        $availableStages = [];
        if ($process->workflow && defined('App\Models\Process::WORKFLOWS') && is_array(Process::WORKFLOWS) && array_key_exists($process->workflow, Process::WORKFLOWS) && method_exists(Process::class, 'getStagesForWorkflow')) {
            $availableStages = collect(Process::getStagesForWorkflow($process->workflow))
                ->map(fn($label, $key) => ['key' => (int) $key, 'label' => $label])
                ->values()->all();
        }
        $users = User::orderBy('name')->get(['id', 'name']);
        $availablePriorities = (defined('App\Models\Process::PRIORITIES') && is_array(Process::PRIORITIES)) ?
            collect(Process::PRIORITIES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() :
            [['key' => Process::PRIORITY_LOW ?? 'low', 'label' => 'Baixa'], ['key' => Process::PRIORITY_MEDIUM ?? 'medium', 'label' => 'Média'], ['key' => Process::PRIORITY_HIGH ?? 'high', 'label' => 'Alta']];
        $availableStatuses = (defined('App\Models\Process::STATUSES') && is_array(Process::STATUSES)) ?
            collect(Process::STATUSES)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all() : [];

        $paymentMethods = ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência Bancária', 'Dinheiro', 'Cheque', 'Outro'];
        $allPaymentTypesForDisplay = method_exists(PaymentType::class, 'forFrontend') ? PaymentType::forFrontend() :
            collect(PaymentType::cases())->map(fn($case) => ['value' => $case->value, 'label' => $case->label()])->values()->all();
        $paymentStatuses = ProcessPayment::getStatusesForFrontend();

        return Inertia::render('processes/Show', [
            'process' => $process,
            'users' => $users,
            'availableStages' => $availableStages,
            'availablePriorities' => $availablePriorities,
            'availableStatuses' => $availableStatuses,
            'paymentMethods' => $paymentMethods,
            'paymentTypes' => $allPaymentTypesForDisplay,
            'paymentStatuses' => $paymentStatuses,
        ]);
    }

    public function destroy(Process $process)
    {
        DB::beginTransaction();
        try {
            $processTitle = $process->title;
            $process->payments()->forceDelete();
            $process->annotations()->delete();
            $process->historyEntries()->delete();
            $process->tasks()->delete();
            $process->documents()->each(function ($doc) {
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

    public function updateStage(Request $request, Process $process)
    {
        $validated = $request->validate([
            'stage' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($process) {
                    if (method_exists(Process::class, 'getStagesForWorkflow') && isset(Process::WORKFLOWS[$process->workflow])) {
                        $stagesForWorkflow = Process::getStagesForWorkflow($process->workflow);
                        if (!array_key_exists($value, $stagesForWorkflow)) {
                            $workflowLabel = Process::WORKFLOWS[$process->workflow] ?? $process->workflow;
                            $fail("O estágio selecionado não é válido para o workflow '{$workflowLabel}'.");
                        }
                    } else {
                        $fail("Configuração de workflow ou estágios inválida.");
                    }
                }
            ],
        ]);

        DB::beginTransaction();
        try {
            $oldStageLabel = $process->stage_label ?? $process->stage;
            $process->stage = $validated['stage'];
            $process->save();
            $newStageLabel = $process->fresh()->stage_label ?? $process->stage;

            $process->historyEntries()->create([
                'action' => 'Estágio Alterado',
                'description' => "De \"{$oldStageLabel}\" para \"{$newStageLabel}\".",
                'user_id' => Auth::id(),
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
            'priority' => ['required', 'string', Rule::in(array_keys(Process::PRIORITIES ?? []))],
        ]);

        DB::beginTransaction();
        try {
            $oldPriorityLabel = $process->priority_label ?? $process->priority;
            $process->priority = $validated['priority'];
            $process->save();
            $newPriorityLabel = $process->fresh()->priority_label ?? $process->priority;

            $process->historyEntries()->create([
                'action' => 'Prioridade Alterada',
                'description' => "De \"{$oldPriorityLabel}\" para \"{$newPriorityLabel}\".",
                'user_id' => Auth::id(),
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
            'status' => ['required', 'string', Rule::in(array_keys(Process::STATUSES ?? []))],
        ]);

        DB::beginTransaction();
        try {
            $oldStatusLabel = $process->status_label ?? $process->status;
            $process->status = $validated['status'];
            $process->save();
            $newStatusLabel = $process->fresh()->status_label ?? $process->status;

            $process->historyEntries()->create([
                'action' => 'Status Alterado',
                'description' => "De \"{$oldStatusLabel}\" para \"{$newStatusLabel}\".",
                'user_id' => Auth::id(),
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
                    'user_id' => Auth::id(),
                ]);
                DB::commit();
                return Redirect::route('processes.index')->with('success', 'Caso arquivado com sucesso.');
            }
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
                    'user_id' => Auth::id(),
                ]);
                DB::commit();
                return Redirect::route('processes.show', $process->id)->with('success', 'Caso restaurado com sucesso.');
            }
            return Redirect::route('processes.show', $process->id)->with('info', 'Este caso não está arquivado.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao restaurar processo {$process->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao restaurar o caso.');
        }
    }

    public function storeProcessAnnotation(Request $request, Process $process)
    {
        $data = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            $process->annotations()->create([
                'content' => $data['content'],
                'user_id' => Auth::id(),
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
            'file' => 'required|file|max:20480',
            'description' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $fileName = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("process_documents/{$process->id}", $fileName, 'public');

            if (!$path) {
                throw new \Exception("Falha ao armazenar o arquivo.");
            }

            $process->documents()->create([
                'uploader_user_id' => Auth::id(),
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'description' => $data['description'],
            ]);

            $process->historyEntries()->create([
                'action' => 'Documento Adicionado',
                'description' => "Documento \"{$file->getClientOriginalName()}\" foi adicionado ao caso.",
                'user_id' => Auth::id(),
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
                'user_id' => Auth::id(),
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
            $taskData['status'] = $validatedData['status'] ?? (defined('App\Models\Task::STATUS_PENDING') ? Task::STATUS_PENDING : 'Pendente');

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
                'user_id' => Auth::id(),
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

        $statusCompleted = defined('App\Models\Task::STATUS_COMPLETED') ? Task::STATUS_COMPLETED : 'Concluída';
        $validatedData['completed_at'] = ($validatedData['status'] === $statusCompleted && !$task->completed_at) ? now() : $task->completed_at;
        if ($validatedData['status'] !== $statusCompleted) {
            $validatedData['completed_at'] = null;
        }

        DB::beginTransaction();
        try {
            $task->update($validatedData);
            $task->refresh();

            $process->historyEntries()->create([
                'action' => 'Tarefa Atualizada',
                'description' => "Tarefa \"{$task->title}\" foi atualizada.",
                'user_id' => Auth::id(),
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
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('processes.show', $process->id)->with('success', 'Tarefa excluída com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir tarefa {$task->id} do processo {$process->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir tarefa.');
        }
    }

    public function updateProcessPayment(Request $request, Process $process, ProcessPayment $payment)
    {

        if ($process->isArchived()) {
            return back()->with('error', 'Não é possível editar pagamentos de um caso arquivado.');
        }

        // Garante que o pagamento pertence ao processo e NÃO é um honorário
        // Honorários são tratados pelo método updateFee
        if ($payment->process_id !== $process->id || $payment->payment_type === PaymentType::HONORARIO) {
            Log::warning("Tentativa de editar pagamento inválido ou honorário por rota incorreta. Processo ID: {$process->id}, Payment ID: {$payment->id}, Payment Type: {$payment->payment_type->value}");
            return back()->with('error', 'Pagamento não encontrado ou tipo inválido para esta ação.');
        }

        $validatedData = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProcessPayment::$statuses))],
            'payment_date' => 'nullable|date_format:Y-m-d|required_if:status,' . ProcessPayment::STATUS_PAID,
            'interest_amount' => 'nullable|numeric|min:0', // Validação para juros
        ]);

        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $validatedData['status'],
            ];

            $oldStatusLabel = $payment->status_label;
            $oldPaymentDate = $payment->down_payment_date ? $payment->down_payment_date->format('d/m/Y') : 'N/A';
            $oldInterestAmount = $payment->interest_amount ?? 0;

            if ($validatedData['status'] === ProcessPayment::STATUS_PAID) {
                // Se o status for 'Pago', a 'payment_date' (data de pagamento efetivo) é obrigatória.
                // No banco, usamos a coluna 'down_payment_date' para armazenar a data de pagamento efetivo.
                $updateData['down_payment_date'] = Carbon::parse($validatedData['payment_date']);

                // Salva o valor dos juros se fornecido e se o status for 'pago'.
                // O frontend controla a visibilidade do campo de juros. Se ele não estiver visível,
                // 'interest_amount' não deve ser enviado ou enviado como null.
                if ($request->filled('interest_amount')) {
                    $updateData['interest_amount'] = (float) $validatedData['interest_amount'];
                } elseif (is_null($request->input('interest_amount')) && $request->has('interest_amount')) {
                    // Se o campo foi explicitamente enviado como nulo (ex: usuário limpou)
                    $updateData['interest_amount'] = null;
                }
                // Se 'interest_amount' não estiver no request (ex: campo não visível), não o alteramos,
                // a menos que a lógica abaixo para status não pago o limpe.

            } else {
                // Se o status não for 'Pago' (ex: 'Pendente', 'Cancelado'),
                // a data de pagamento efetivo e os juros devem ser nulos.
                $updateData['down_payment_date'] = null;
                $updateData['interest_amount'] = null;
            }

            $payment->update($updateData);
            $payment->refresh(); // Recarrega o modelo para obter o novo status_label e interest_amount

            // Construção da descrição do histórico
            $paymentDescription = $payment->notes ?? "Parcela/Entrada ID {$payment->id}";
            if (strpos($paymentDescription, "Parcela") === false && $payment->payment_type !== PaymentType::A_VISTA && $payment->down_payment_amount > 0 && $payment->total_amount == $payment->down_payment_amount) {
                $paymentDescription = "Entrada ID {$payment->id}";
            }

            $historyParts = [];
            if ($oldStatusLabel !== $payment->status_label) {
                $historyParts[] = "status alterado de '{$oldStatusLabel}' para '{$payment->status_label}'";
            }

            $newPaymentDate = $payment->down_payment_date ? $payment->down_payment_date->format('d/m/Y') : 'N/A';
            if ($newPaymentDate !== $oldPaymentDate) {
                if ($newPaymentDate !== 'N/A') {
                    $historyParts[] = "data de pagamento definida para {$newPaymentDate}";
                } else {
                    $historyParts[] = "data de pagamento removida (era {$oldPaymentDate})";
                }
            }

            $newInterestAmount = $payment->interest_amount ?? 0;
            if ((float) $newInterestAmount != (float) $oldInterestAmount) {
                if ($newInterestAmount > 0) {
                    $historyParts[] = "juros definidos como " . number_format($newInterestAmount, 2, ',', '.');
                } else {
                    $historyParts[] = "juros removidos (eram " . number_format($oldInterestAmount, 2, ',', '.') . ")";
                }
            }

            if (empty($historyParts)) {
                $historyDescription = "Pagamento '{$paymentDescription}' (Valor: " . number_format($payment->total_amount, 2, ',', '.') . ") verificado (Status: '{$payment->status_label}').";
            } else {
                $historyDescription = "Pagamento '{$paymentDescription}' (Valor: " . number_format($payment->total_amount, 2, ',', '.') . "): " . implode(', ', $historyParts) . ".";
            }


            $process->historyEntries()->create([
                'action' => 'Pagamento Atualizado',
                'description' => $historyDescription,
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            return Redirect::route('processes.show', $process->id)->with('success', 'Pagamento atualizado com sucesso!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar pagamento (ID: {$payment->id}) do processo {$process->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Ocorreu um erro inesperado ao atualizar o pagamento: ' . $e->getMessage());
        }
    }
}
