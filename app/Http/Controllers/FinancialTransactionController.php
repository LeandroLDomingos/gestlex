<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\ProcessPayment;
use App\Enums\PaymentType;
use App\Enums\TransactionNature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str; // Importar Str para UUIDs

class FinancialTransactionController extends Controller
{
    /**
     * Helper para inferir a natureza da transação (receita/despesa)
     * baseado no tipo de pagamento.
     *
     * @param string|null $paymentTypeValue O valor string do tipo de pagamento.
     * @return string|null 'income', 'expense', ou null.
     */
    private function inferTransactionNature(?string $paymentTypeValue): ?string
    {
        if (!$paymentTypeValue) {
            return null;
        }

        $expensePaymentTypes = [
            'despesa_operacional',
            'compra_material',
            'pagamento_fornecedor',
            'custas_processuais',
            'adiantamento_despesa'
        ];
        if (in_array($paymentTypeValue, $expensePaymentTypes)) {
            return TransactionNature::EXPENSE->value;
        }

        $incomePaymentTypes = [
            PaymentType::A_VISTA->value,
            PaymentType::PARCELADO->value, 
            PaymentType::HONORARIO->value,
        ];
        if (in_array($paymentTypeValue, $incomePaymentTypes)) {
            return TransactionNature::INCOME->value;
        }
        return null;
    }

    public function index(Request $request): InertiaResponse
    {
        $request->validate([
            'sort_by' => 'nullable|string|in:created_at,first_installment_due_date,total_amount,status,payment_type,process.title,process.contact.name,total_value_with_interest,transaction_nature,transaction_group_id',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'search_process' => 'nullable|string|max:255',
            'search_contact' => 'nullable|string|max:255',
            'search_description' => 'nullable|string|max:255',
            'payment_type_filter' => 'nullable|string',
            'status_filter' => 'nullable|string',
            'transaction_nature_filter' => 'nullable|string|in:income,expense',
            'summary_date_from' => 'nullable|date_format:Y-m-d',
            'summary_date_to' => 'nullable|date_format:Y-m-d|after_or_equal:summary_date_from',
        ]);

        $today = Carbon::today();
        $hasTransactionNatureColumn = DB::getSchemaBuilder()->hasColumn((new ProcessPayment)->getTable(), 'transaction_nature');

        $applyNatureFilter = function ($query, string $targetNature) use ($hasTransactionNatureColumn) {
            $paymentTypesForTargetNature = collect(PaymentType::cases())
                ->filter(fn($ptEnum) => $this->inferTransactionNature($ptEnum->value) === $targetNature)
                ->pluck('value')->all();

            if ($hasTransactionNatureColumn) {
                $query->where(function ($subQuery) use ($targetNature, $paymentTypesForTargetNature) {
                    $subQuery->where('transaction_nature', $targetNature);
                    if (!empty($paymentTypesForTargetNature)) {
                        $subQuery->orWhere(function ($fallbackQuery) use ($paymentTypesForTargetNature) {
                            $fallbackQuery->whereNull('transaction_nature')
                                          ->whereIn('payment_type', $paymentTypesForTargetNature);
                        });
                    }
                });
            } else {
                if (!empty($paymentTypesForTargetNature)) {
                    $query->whereIn('payment_type', $paymentTypesForTargetNature);
                } else {
                    $query->whereRaw('1=0');
                }
            }
        };

        $transactionsQuery = ProcessPayment::query()
            ->with(['process:id,title,contact_id', 'process.contact:id,name,business_name', 'supplierContact:id,name,business_name']);

        if ($request->filled('search_process')) {
            $transactionsQuery->whereHas('process', fn($q) => $q->where('title', 'like', '%' . $request->input('search_process') . '%'));
        }
        if ($request->filled('search_contact')) {
            $searchTerm = '%' . $request->input('search_contact') . '%';
            $transactionsQuery->where(function ($query) use ($searchTerm) {
                $query->whereHas('process.contact', fn($q) => $q->where('name', 'like', $searchTerm)->orWhere('business_name', 'like', $searchTerm))
                      ->orWhereHas('supplierContact', fn($q) => $q->where('name', 'like', $searchTerm)->orWhere('business_name', 'like', $searchTerm));
            });
        }
        if ($request->filled('search_description')) {
            $transactionsQuery->where('notes', 'like', '%' . $request->input('search_description') . '%');
        }
        if ($request->filled('payment_type_filter')) {
            $transactionsQuery->where('payment_type', $request->input('payment_type_filter'));
        }
        if ($request->filled('status_filter')) {
            $transactionsQuery->where('status', $request->input('status_filter'));
        }
        if ($request->filled('transaction_nature_filter')) {
            $applyNatureFilter($transactionsQuery, $request->input('transaction_nature_filter'));
        }

        $tableDateFrom = $request->input('summary_date_from') ? Carbon::parse($request->input('summary_date_from'))->startOfDay() : null;
        $tableDateTo = $request->input('summary_date_to') ? Carbon::parse($request->input('summary_date_to'))->endOfDay() : null;
        if ($tableDateFrom) {
            $transactionsQuery->where(function ($q) use ($tableDateFrom) {
                $q->whereDate('first_installment_due_date', '>=', $tableDateFrom)->orWhereDate('down_payment_date', '>=', $tableDateFrom); });
        }
        if ($tableDateTo) {
            $transactionsQuery->where(function ($q) use ($tableDateTo) {
                $q->whereDate('first_installment_due_date', '<=', $tableDateTo)->orWhereDate('down_payment_date', '<=', $tableDateTo); });
        }

        $tableSortBy = $request->input('sort_by', 'first_installment_due_date');
        $tableSortDirection = $request->input('sort_direction', 'desc');
        
        if ($tableSortBy === 'process.title') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')->orderBy('processes.title', $tableSortDirection)->select('process_payments.*');
        } elseif ($tableSortBy === 'process.contact.name') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')->orderBy('contacts.name', $tableSortDirection)->select('process_payments.*');
        } elseif ($tableSortBy === 'total_value_with_interest') {
            $transactionsQuery->orderBy(DB::raw('process_payments.total_amount + IFNULL(process_payments.interest_amount, 0)'), $tableSortDirection);
        } elseif (in_array($tableSortBy, ['total_amount', 'created_at', 'first_installment_due_date', 'down_payment_date', 'status', 'payment_type', 'transaction_nature', 'transaction_group_id'])) {
            $transactionsQuery->orderBy("process_payments.$tableSortBy", $tableSortDirection);
        } else {
            $transactionsQuery->orderBy('process_payments.first_installment_due_date', 'desc');
        }

        $paginatedTransactions = $transactionsQuery->paginate(10)->withQueryString();
        $paginatedTransactions->getCollection()->each(function ($transaction) use ($hasTransactionNatureColumn) {
            $transaction->append('status_label');
            if (!$hasTransactionNatureColumn && empty($transaction->transaction_nature) && !property_exists($transaction, 'transaction_nature_already_set')) {
                $paymentTypeValue = is_object($transaction->payment_type) && property_exists($transaction->payment_type, 'value')
                                    ? $transaction->payment_type->value
                                    : (is_string($transaction->payment_type) ? $transaction->payment_type : null);
                $inferredNature = $this->inferTransactionNature($paymentTypeValue);
                if ($inferredNature) {
                    $transaction->transaction_nature = $inferredNature;
                }
                $transaction->transaction_nature_already_set = true;
            }
        });

        $summaryCardsDateFromFixed = $today->copy()->startOfMonth();
        $summaryCardsDateToFixed = $today->copy()->endOfMonth();
        $weeklyCardsDateFromFixed = $today->copy()->startOfWeek(Carbon::SUNDAY);
        $weeklyCardsDateToFixed = $today->copy()->endOfWeek(Carbon::SATURDAY);

        $totalReceivedInPeriod = ProcessPayment::query()->where('status', ProcessPayment::STATUS_PAID)->where(fn($q) => $applyNatureFilter($q, TransactionNature::INCOME->value))->whereNotNull('down_payment_date')->whereBetween('down_payment_date', [$summaryCardsDateFromFixed, $summaryCardsDateToFixed])->sum(DB::raw('total_amount + IFNULL(interest_amount, 0)'));
        $totalExpensesInPeriod = ProcessPayment::query()->where('status', ProcessPayment::STATUS_PAID)->where(fn($q) => $applyNatureFilter($q, TransactionNature::EXPENSE->value))->whereNotNull('down_payment_date')->whereBetween('down_payment_date', [$summaryCardsDateFromFixed, $summaryCardsDateToFixed])->sum(DB::raw('total_amount + IFNULL(interest_amount, 0)'));
        $totalLifetimeReceived = ProcessPayment::query()->where('status', ProcessPayment::STATUS_PAID)->where(fn($q) => $applyNatureFilter($q, TransactionNature::INCOME->value))->sum(DB::raw('total_amount + IFNULL(interest_amount, 0)'));
        $totalLifetimeExpenses = ProcessPayment::query()->where('status', ProcessPayment::STATUS_PAID)->where(fn($q) => $applyNatureFilter($q, TransactionNature::EXPENSE->value))->sum(DB::raw('total_amount + IFNULL(interest_amount, 0)'));
        $overallBalance = $totalLifetimeReceived - $totalLifetimeExpenses;
        $accountsReceivableOverdueWeekly = ProcessPayment::query()->where(fn($q) => $applyNatureFilter($q, TransactionNature::INCOME->value))->whereIn('status', [ProcessPayment::STATUS_PENDING, ProcessPayment::STATUS_OVERDUE])->whereNotNull('first_installment_due_date')->whereBetween('first_installment_due_date', [$weeklyCardsDateFromFixed, $weeklyCardsDateToFixed])->sum('total_amount');
        $accountsPayableOverdueWeekly = ProcessPayment::query()->where(fn($q) => $applyNatureFilter($q, TransactionNature::EXPENSE->value))->whereIn('status', [ProcessPayment::STATUS_PENDING, ProcessPayment::STATUS_OVERDUE])->whereNotNull('first_installment_due_date')->whereBetween('first_installment_due_date', [$weeklyCardsDateFromFixed, $weeklyCardsDateToFixed])->sum('total_amount');

        $latestPaidExpenses = ProcessPayment::query()
            ->with(['supplierContact:id,name,business_name', 'process:id,title'])
            ->where('status', ProcessPayment::STATUS_PAID)
            ->where(fn($q) => $applyNatureFilter($q, TransactionNature::EXPENSE->value))
            ->whereNotNull('down_payment_date')
            ->orderBy('down_payment_date', 'desc')->limit(5)->get();
        $latestPaidExpenses->each->append('status_label');

        $upcomingDueExpenses = ProcessPayment::query()
            ->with(['supplierContact:id,name,business_name', 'process:id,title'])
            ->whereIn('status', [ProcessPayment::STATUS_PENDING, ProcessPayment::STATUS_OVERDUE])
            ->where(fn($q) => $applyNatureFilter($q, TransactionNature::EXPENSE->value))
            ->whereNotNull('first_installment_due_date')
            ->whereBetween('first_installment_due_date', [$today, $today->copy()->addDays(30)->endOfDay()])
            ->orderBy('first_installment_due_date', 'asc')->limit(5)->get();
        $upcomingDueExpenses->each->append('status_label');

        $latestReceivedTransactions = ProcessPayment::query()->with(['process:id,title', 'process.contact:id,name,business_name'])->where(fn($q) => $applyNatureFilter($q, TransactionNature::INCOME->value))->where('status', ProcessPayment::STATUS_PAID)->whereNotNull('down_payment_date')->orderBy('down_payment_date', 'desc')->limit(5)->get();
        $latestReceivedTransactions->each->append('status_label');

        $upcomingDueTransactions = ProcessPayment::query()->with(['process:id,title', 'process.contact:id,name,business_name'])->where(fn($q) => $applyNatureFilter($q, TransactionNature::INCOME->value))->whereIn('status', [ProcessPayment::STATUS_PENDING, ProcessPayment::STATUS_OVERDUE])->whereNotNull('first_installment_due_date')->whereBetween('first_installment_due_date', [$today, $today->copy()->addDays(30)->endOfDay()])->orderBy('first_installment_due_date', 'asc')->limit(5)->get();
        $upcomingDueTransactions->each->append('status_label');

        return Inertia::render('Financial/Index', [
            'transactions' => $paginatedTransactions,
            'filters' => $request->only(['search_process', 'search_contact', 'search_description', 'payment_type_filter', 'status_filter', 'transaction_nature_filter', 'sort_by', 'sort_direction', 'summary_date_from', 'summary_date_to']),
            'paymentTypes' => PaymentType::forFrontend(),
            'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
            'dashboardSummary' => [
                'totalReceivedInPeriod' => $totalReceivedInPeriod,
                'totalExpensesInPeriod' => $totalExpensesInPeriod,
                'balanceInPeriod' => $overallBalance,
                'accountsReceivableOverdueWeekly' => $accountsReceivableOverdueWeekly,
                'accountsPayableOverdueWeekly' => $accountsPayableOverdueWeekly,
                'summaryCardsDateFrom' => $summaryCardsDateFromFixed->toDateString(),
                'summaryCardsDateTo' => $summaryCardsDateToFixed->toDateString(),
            ],
            'latestReceivedTransactions' => $latestReceivedTransactions,
            'upcomingDueTransactions' => $upcomingDueTransactions,
            'latestPaidExpenses' => $latestPaidExpenses,
            'upcomingDueExpenses' => $upcomingDueExpenses,
        ]);
    }

    public function store(Request $request)
    {
        $validStatuses = array_keys(ProcessPayment::$statuses);
        $validPaymentTypes = array_map(fn($pt) => $pt['value'], PaymentType::forFrontend()); 
        $installmentPaymentTypeValue = PaymentType::PARCELADO->value; 

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_type' => ['required', 'string', Rule::in($validPaymentTypes)],
            'first_installment_due_date' => 'required|date_format:Y-m-d',
            'transaction_nature' => ['required', Rule::in(array_map(fn($case) => $case->value, TransactionNature::cases()))],
            'status' => ['required', 'string', Rule::in($validStatuses)],
            'process_id' => 'nullable|exists:processes,id',
            'supplier_contact_id' => 'nullable|exists:contacts,id',
            'number_of_installments' => 'nullable|required_if:payment_type,'.$installmentPaymentTypeValue.'|integer|min:1',
            'value_of_installment' => 'nullable|required_if:payment_type,'.$installmentPaymentTypeValue.'|numeric|min:0.01',
            'down_payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'interest_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            if ($request->inertia()) {
                return back()->withErrors($validator)->withInput();
            }
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $paymentData = $request->only([
                'notes', 'transaction_nature', 'status', 'process_id', 
                'supplier_contact_id', 'payment_method', 'interest_amount', 'down_payment_amount'
            ]);

            if ($request->filled('process_id')) {
                $process = Process::find($request->input('process_id'));
                if ($process && (method_exists($process, 'isArchived') ? $process->isArchived() : !is_null($process->archived_at))) {
                     DB::rollBack();
                     if ($request->inertia()) {
                         return back()->with('error', 'Não é possível adicionar transações a um caso arquivado.')->withInput();
                     }
                     return response()->json(['error' => 'Não é possível adicionar transações a um caso arquivado.'], 403);
                }
                $paymentData['process_id'] = $request->input('process_id');
            } else {
                $paymentData['process_id'] = null;
            }
            
            if ($request->filled('supplier_contact_id') && $request->input('transaction_nature') === TransactionNature::EXPENSE->value && !$request->filled('process_id')) {
                $paymentData['supplier_contact_id'] = $request->input('supplier_contact_id');
            }

            $firstDueDate = Carbon::parse($request->input('first_installment_due_date'));

            if ($request->input('payment_type') === $installmentPaymentTypeValue) {
                $numberOfInstallments = (int) $request->input('number_of_installments');
                $valueOfInstallment = (float) $request->input('value_of_installment');
                $transactionGroupId = (string) Str::uuid(); 

                for ($i = 0; $i < $numberOfInstallments; $i++) {
                    $installment = new ProcessPayment();
                    $installment->fill($paymentData);
                    $installment->transaction_group_id = $transactionGroupId; 
                    $installment->payment_type = $request->input('payment_type');
                    $installment->total_amount = $valueOfInstallment;
                    $installment->number_of_installments = $numberOfInstallments;
                    $installment->value_of_installment = $valueOfInstallment;
                    $installment->first_installment_due_date = ($i === 0) ? $firstDueDate->copy() : $firstDueDate->copy()->addMonthsNoOverflow($i);
                    $installment->status = $request->input('status', ProcessPayment::STATUS_PENDING); 
                    
                    if ($i === 0 && $installment->status === ProcessPayment::STATUS_PAID) {
                        $installment->down_payment_date = now()->format('Y-m-d');
                    }
                    $installment->save();
                }
            } else {
                $payment = new ProcessPayment();
                $payment->fill($paymentData);
                $payment->transaction_group_id = null; 
                $payment->total_amount = $request->input('total_amount');
                $payment->payment_type = $request->input('payment_type');
                $payment->first_installment_due_date = $firstDueDate;
                $payment->number_of_installments = null;
                $payment->value_of_installment = null;
                
                if ($payment->status === ProcessPayment::STATUS_PAID) { 
                    $payment->down_payment_date = now()->format('Y-m-d');
                }
                $payment->save();
            }

            DB::commit();

            $successMessage = $request->input('transaction_nature') === TransactionNature::EXPENSE->value ? 'Despesa adicionada com sucesso!' : 'Receita adicionada com sucesso!';
             if ($request->input('payment_type') === $installmentPaymentTypeValue) {
                $successMessage = $request->input('transaction_nature') === TransactionNature::EXPENSE->value ? 'Despesa parcelada adicionada com sucesso!' : 'Receita parcelada adicionada com sucesso!';
            }

            if ($request->inertia()) {
                if ($request->filled('process_id')) {
                    return redirect()->route('processes.show', $request->input('process_id'))
                                     ->with('success', $successMessage);
                }
                return redirect()->route('financial-transactions.index') 
                                 ->with('success', $successMessage);
            }
            
            return response()->json(['message' => $successMessage], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao salvar transação financeira: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            $errorMessage = 'Ocorreu um erro ao tentar adicionar a transação. Tente novamente.';

            if ($request->inertia()) {
                return back()->with('error', $errorMessage)->withInput();
            }
            return response()->json(['error' => $errorMessage], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProcessPayment  $financialTransaction
     * @return \Inertia\Response
     */
    public function edit(Request $request, ProcessPayment $financialTransaction): InertiaResponse
    {
        $financialTransaction->load(['process:id,title,contact_id', 'process.contact:id,name,business_name', 'supplierContact:id,name,business_name']);

        $groupedInstallments = null;
        if ($financialTransaction->transaction_group_id) {
            $groupedInstallments = ProcessPayment::where('transaction_group_id', $financialTransaction->transaction_group_id)
                ->orderBy('first_installment_due_date', 'asc')
                ->get()
                ->load(['process:id,title', 'supplierContact:id,name,business_name']);
        }

        // Formatar TransactionNatures para o frontend
        $transactionNatures = array_map(function($case) {
            return ['value' => $case->value, 'label' => ucfirst($case->value)];
        }, TransactionNature::cases());

        return Inertia::render('Financial/Edit', [
            'transaction' => $financialTransaction,
            'groupedInstallments' => $groupedInstallments,
            'paymentTypes' => PaymentType::forFrontend(),
            'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
            'transactionNatures' => $transactionNatures,
            // Adicionar outras listas necessárias para dropdowns (ex: processos, contatos)
            // 'processes' => Process::select('id', 'title')->orderBy('title')->get(),
            // 'contacts' => Contact::select('id', 'name', 'business_name')->orderBy('name')->get(), // Supondo que Contact model existe
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProcessPayment  $financialTransaction
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ProcessPayment $financialTransaction)
    {
        $validStatuses = array_keys(ProcessPayment::$statuses);
        $validPaymentTypes = array_map(fn($pt) => $pt['value'], PaymentType::forFrontend());
        $installmentPaymentTypeValue = PaymentType::PARCELADO->value;

        // Validação para edição - pode precisar ser mais granular dependendo do que pode ser editado
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0.01', // Para uma parcela, este é o valor da parcela
            'payment_type' => ['required', 'string', Rule::in($validPaymentTypes)],
            'first_installment_due_date' => 'required|date_format:Y-m-d',
            'transaction_nature' => ['required', Rule::in(array_map(fn($case) => $case->value, TransactionNature::cases()))],
            'status' => ['required', 'string', Rule::in($validStatuses)],
            'process_id' => 'nullable|exists:processes,id',
            'supplier_contact_id' => 'nullable|exists:contacts,id',
            // Se for editar um parcelamento, estes campos podem ser relevantes
            'number_of_installments' => 'nullable|required_if:payment_type,'.$installmentPaymentTypeValue.'|integer|min:1',
            // 'value_of_installment' => 'nullable|required_if:payment_type,'.$installmentPaymentTypeValue.'|numeric|min:0.01', // O total_amount da parcela é o value_of_installment
            'down_payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'interest_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            if ($request->inertia()) {
                return back()->withErrors($validator)->withInput();
            }
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Lógica de atualização:
            // Por agora, vamos assumir que estamos a editar os campos da $financialTransaction individual.
            // Editar um grupo de parcelas (ex: mudar o número de parcelas) é mais complexo
            // e poderia envolver excluir e recriar as parcelas.

            $financialTransaction->notes = $request->input('notes');
            $financialTransaction->total_amount = $request->input('total_amount'); // Este é o valor da parcela individual
            $financialTransaction->payment_type = $request->input('payment_type');
            $financialTransaction->first_installment_due_date = Carbon::parse($request->input('first_installment_due_date'));
            $financialTransaction->transaction_nature = $request->input('transaction_nature');
            $financialTransaction->status = $request->input('status');
            
            if ($request->filled('process_id')) {
                $financialTransaction->process_id = $request->input('process_id');
            } else {
                $financialTransaction->process_id = null;
            }

            if ($request->filled('supplier_contact_id') && $financialTransaction->transaction_nature === TransactionNature::EXPENSE->value && !$request->filled('process_id')) {
                $financialTransaction->supplier_contact_id = $request->input('supplier_contact_id');
            } else if (!$request->filled('supplier_contact_id') || $request->filled('process_id')) {
                $financialTransaction->supplier_contact_id = null;
            }

            // Se o tipo de pagamento for parcelado, atualiza os campos de parcelamento para ESTE registo
            if ($request->input('payment_type') === $installmentPaymentTypeValue) {
                $financialTransaction->number_of_installments = $request->input('number_of_installments'); // Número total de parcelas do grupo
                $financialTransaction->value_of_installment = $request->input('total_amount'); // O valor desta parcela é o seu total_amount
            } else {
                $financialTransaction->number_of_installments = null;
                $financialTransaction->value_of_installment = null;
            }

            $financialTransaction->down_payment_amount = $request->input('down_payment_amount');
            $financialTransaction->payment_method = $request->input('payment_method');
            $financialTransaction->interest_amount = $request->input('interest_amount');

            // Atualizar data de pagamento se o status for 'paid' e ainda não tiver data
            if ($financialTransaction->status === ProcessPayment::STATUS_PAID && is_null($financialTransaction->down_payment_date)) {
                $financialTransaction->down_payment_date = now()->format('Y-m-d');
            } elseif ($financialTransaction->status !== ProcessPayment::STATUS_PAID && !is_null($financialTransaction->down_payment_date)) {
                // Se o status mudou de 'paid' para outra coisa, talvez limpar a data de pagamento?
                // $financialTransaction->down_payment_date = null; // Decida sobre esta lógica
            }

            $financialTransaction->save();
            DB::commit();

            return redirect()->route('financial-transactions.index')->with('success', 'Transação atualizada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar transação financeira: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Erro ao atualizar transação. Tente novamente.')->withInput();
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProcessPayment  $financialTransaction
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, ProcessPayment $financialTransaction)
    {
        DB::beginTransaction();
        try {
            if ($financialTransaction->transaction_group_id) {
                ProcessPayment::where('transaction_group_id', $financialTransaction->transaction_group_id)->delete();
                $successMessage = 'Grupo de transações parceladas excluído com sucesso!';
            } else {
                $financialTransaction->delete(); 
                $successMessage = 'Transação financeira excluída com sucesso!';
            }

            DB::commit();

            if ($request->inertia()) {
                return back()->with('success', $successMessage);
            }

            return response()->json(['message' => $successMessage], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir transação financeira: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            $errorMessage = 'Ocorreu um erro ao tentar excluir a transação. Tente novamente.';

            if ($request->inertia()) {
                return back()->with('error', $errorMessage);
            }
            return response()->json(['error' => $errorMessage], 500);
        }
    }
}
