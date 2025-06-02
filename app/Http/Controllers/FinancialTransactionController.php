<?php

namespace App\Http\Controllers;

use App\Models\ProcessPayment;
use App\Enums\PaymentType;
use App\Enums\TransactionNature;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\Process;

class FinancialTransactionController extends Controller
{
    /**
     * Helper para inferir a natureza da transação (receita/despesa)
     * baseado no tipo de pagamento, caso a coluna 'transaction_nature' não exista
     * ou não esteja preenchida.
     *
     * @param string|null $paymentTypeValue O valor string do tipo de pagamento.
     * @return string|null 'income', 'expense', ou null.
     */
    private function inferTransactionNature(?string $paymentTypeValue): ?string
    {
        if (!$paymentTypeValue) {
            return null;
        }

        // **IMPORTANTE:** Defina aqui os VALORES dos seus PaymentType Enums que são DESPESAS.
        // Estes são apenas exemplos.
        $expensePaymentTypes = [
            'despesa_operacional', // Exemplo, substitua/adicione os seus
            'compra_material',
            'pagamento_fornecedor',
            'custas_processuais',
            'adiantamento_despesa'
            // Ex: se tivesse um PaymentType::CUSTAS_JUDICIAIS->value, adicionaria aqui.
        ];
        if (in_array($paymentTypeValue, $expensePaymentTypes)) {
            return TransactionNature::EXPENSE->value;
        }

        // **IMPORTANTE:** Defina aqui os VALORES dos seus PaymentType Enums que são RECEITAS.
        $incomePaymentTypes = [
            PaymentType::A_VISTA->value,
            PaymentType::PARCELADO->value,
            PaymentType::HONORARIO->value,
            // Adicione outros tipos específicos de receita se existirem e não forem cobertos acima
            // 'honorario_entrada', // Se estes forem valores string diretos e não do enum
            // 'honorario_parcela',
            // 'receita_servico',
        ];
        if (in_array($paymentTypeValue, $incomePaymentTypes)) {
            return TransactionNature::INCOME->value;
        }

        // Se o tipo de pagamento não estiver claramente definido como receita ou despesa,
        // retorna null. O ideal é que todas as transações tenham uma natureza clara.
        return null;
    }

    public function index(Request $request): Response
    {
        $request->validate([
            'sort_by' => 'nullable|string|in:created_at,first_installment_due_date,total_amount,status,payment_type,process.title,process.contact.name,total_value_with_interest,transaction_nature',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'search_process' => 'nullable|string|max:255',
            'search_contact' => 'nullable|string|max:255',
            'search_description' => 'nullable|string|max:255',
            'payment_type_filter' => 'nullable|string',
            'status_filter' => 'nullable|string',
            'transaction_nature_filter' => 'nullable|string|in:income,expense',
            'summary_date_from' => 'nullable|date_format:Y-m-d', // Para filtros da tabela
            'summary_date_to' => 'nullable|date_format:Y-m-d|after_or_equal:summary_date_from', // Para filtros da tabela
        ]);

        $today = Carbon::today();
        // Verifica se a coluna transaction_nature existe na tabela.
        // Esta verificação é feita uma vez para otimizar.
        $hasTransactionNatureColumn = DB::getSchemaBuilder()->hasColumn((new ProcessPayment)->getTable(), 'transaction_nature');

        // --- Closure para aplicar filtro de natureza ---
        $applyNatureFilter = function ($query, string $targetNature) use ($hasTransactionNatureColumn) {
            if ($hasTransactionNatureColumn) {
                // Usa os scopes do modelo se a coluna existir
                if ($targetNature === TransactionNature::INCOME->value) {
                    $query->income();
                } elseif ($targetNature === TransactionNature::EXPENSE->value) {
                    $query->expense();
                }
            } else {
                // Fallback: infere pela lista de payment_type
                $relevantPaymentTypes = collect(PaymentType::cases())
                    ->filter(fn($ptEnum) => $this->inferTransactionNature($ptEnum->value) === $targetNature)
                    ->pluck('value')->all();
                if (!empty($relevantPaymentTypes)) {
                    $query->whereIn('payment_type', $relevantPaymentTypes);
                } else {
                    $query->whereRaw('1=0'); // Nenhuma transação se não houver tipos correspondentes
                }
            }
        };

        // --- Tabela Principal de Transações ---
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
        // ... (lógica de ordenação da tabela - adaptada para usar select() corretamente com joins)
        if ($tableSortBy === 'process.title') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')->orderBy('processes.title', $tableSortDirection)->select('process_payments.*');
        } elseif ($tableSortBy === 'process.contact.name') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')->orderBy('contacts.name', $tableSortDirection)->select('process_payments.*');
        } elseif ($tableSortBy === 'total_value_with_interest') {
            $transactionsQuery->orderBy(DB::raw('process_payments.total_amount + IFNULL(process_payments.interest_amount, 0)'), $tableSortDirection);
        } elseif (in_array($tableSortBy, ['total_amount', 'created_at', 'first_installment_due_date', 'down_payment_date', 'status', 'payment_type', 'transaction_nature'])) {
            $transactionsQuery->orderBy("process_payments.$tableSortBy", $tableSortDirection); // Adicionado prefixo da tabela
        } else {
            $transactionsQuery->orderBy('process_payments.first_installment_due_date', 'desc');
        }


        $paginatedTransactions = $transactionsQuery->paginate(10)->withQueryString();
        $paginatedTransactions->getCollection()->each(function ($transaction) use ($hasTransactionNatureColumn) {
            $transaction->append('status_label');
            // Se a coluna transaction_nature não existe E a propriedade não foi inferida/setada
            if (!$hasTransactionNatureColumn && empty($transaction->transaction_nature) && !property_exists($transaction, 'transaction_nature_already_set')) {
                $inferredNature = $this->inferTransactionNature($transaction->payment_type?->value);
                if ($inferredNature) {
                    $transaction->transaction_nature = $inferredNature; // Adiciona dinamicamente para o frontend
                }
                $transaction->transaction_nature_already_set = true; // Flag para evitar reprocessamento
            }
        });

        // --- Cards de Resumo ---
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

        // --- Listas Rápidas ---
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
                'balanceInPeriod' => $overallBalance, // Saldo total acumulado
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
        // 1. Validação dos dados recebidos do formulário
        // Adapt validation rules based on your Enums and ProcessPayment model structure.
        $validStatuses = array_keys(ProcessPayment::getStatusesForFrontend()); // Get valid status keys
        $validPaymentTypes = array_map(fn($pt) => $pt['value'], PaymentType::forFrontend()); // Get valid payment type values

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_type' => ['required', 'string', Rule::in($validPaymentTypes)],
            'first_installment_due_date' => 'required|date_format:Y-m-d', // Match frontend format
            'transaction_nature' => ['required', Rule::in(TransactionNature::values())], // Use Enum values
            'status' => ['required', 'string', Rule::in($validStatuses)],
            'process_id' => 'nullable|exists:processes,id',
            'supplier_contact_id' => 'nullable|exists:contacts,id', // If you add this field to your form
            // 'payment_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            if ($request->inertia()) {
                return back()->withErrors($validator)->withInput();
            }
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Criação da Transação Financeira (ProcessPayment)
        try {
            $payment = new ProcessPayment(); // CHANGED to ProcessPayment
            $payment->notes = $request->input('notes');
            $payment->total_amount = $request->input('total_amount');
            $payment->payment_type = $request->input('payment_type'); // Should map to PaymentType Enum value
            $payment->first_installment_due_date = $request->input('first_installment_due_date');
            
            // Ensure transaction_nature is set, preferably using the Enum value passed from frontend
            $payment->transaction_nature = $request->input('transaction_nature');

            $payment->status = $request->input('status'); // Should map to ProcessPayment status constants/keys

            if ($request->filled('process_id')) {
                $payment->process_id = $request->input('process_id');
                $process = Process::find($payment->process_id);
                // Assuming Process model has an isArchived() method or similar logic
                if ($process && (method_exists($process, 'isArchived') ? $process->isArchived() : !is_null($process->archived_at))) {
                     if ($request->inertia()) {
                        return back()->with('error', 'Não é possível adicionar transações a um caso arquivado.')->withInput();
                     }
                     return response()->json(['error' => 'Não é possível adicionar transações a um caso arquivado.'], 403);
                }
            }
            
            // If a supplier_contact_id is provided and it's an expense not linked to a process
            if ($request->filled('supplier_contact_id') && $payment->transaction_nature === TransactionNature::EXPENSE->value && !$request->filled('process_id')) {
                $payment->supplier_contact_id = $request->input('supplier_contact_id');
            }


            // Se o status for 'paid' ou 'received' (usando constantes do modelo se disponíveis)
            if (in_array($payment->status, [ProcessPayment::STATUS_PAID, ProcessPayment::STATUS_RECEIVED])) { // Adjust if STATUS_RECEIVED is not used
                $payment->down_payment_date = now()->format('Y-m-d'); // Ou uma data específica se fornecida
            }
            
            // $payment->user_id = auth()->id(); // If tracking user who created it

            $payment->save();

            // 3. Redirecionamento ou Resposta
            $successMessage = $payment->transaction_nature === TransactionNature::EXPENSE->value ? 'Despesa adicionada com sucesso!' : 'Receita adicionada com sucesso!';

            if ($request->inertia()) {
                if ($payment->process_id) {
                    return redirect()->route('processes.show', $payment->process_id)
                                     ->with('success', $successMessage);
                }
                // Redirect to the financial index page (matching the component name 'Financial/Index')
                return redirect()->route('financial-transactions.index') // Or whatever your index route for Financial/Index is named
                                 ->with('success', $successMessage);
            }
            
            return response()->json($payment->load(['process:id,title', 'supplierContact:id,name,business_name']), 201); // Load relations for API response

        } catch (\Exception $e) {
            Log::error('Erro ao salvar pagamento do processo: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            $errorMessage = 'Ocorreu um erro ao tentar adicionar a transação. Tente novamente.';

            if ($request->inertia()) {
                return back()->with('error', $errorMessage)->withInput();
            }
            return response()->json(['error' => $errorMessage], 500);
        }
    }
}
