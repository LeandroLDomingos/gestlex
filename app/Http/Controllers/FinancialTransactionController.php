<?php

namespace App\Http\Controllers;

use App\Models\ProcessPayment;
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            // Filtros para a tabela principal de transações (quando visível)
            'sort_by' => 'nullable|string|in:created_at,first_installment_due_date,total_amount,status,payment_type,process.title,process.contact.name,total_value_with_interest',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'search_process' => 'nullable|string|max:255',
            'search_contact' => 'nullable|string|max:255',
            'payment_type_filter' => 'nullable|string',
            'status_filter' => 'nullable|string', // Para filtrar a tabela principal

            // Filtros de data globais para o dashboard (sumário e listas)
            'summary_date_from' => 'nullable|date_format:Y-m-d',
            'summary_date_to' => 'nullable|date_format:Y-m-d|after_or_equal:summary_date_from',
        ]);

        // --- Configurações para a TABELA PRINCIPAL de transações ---
        $tableSortBy = $request->input('sort_by', 'first_installment_due_date');
        $tableSortDirection = $request->input('sort_direction', 'desc');

        $transactionsQuery = ProcessPayment::query()
            ->with(['process:id,title,contact_id', 'process.contact:id,name,business_name']);

        // Aplicar filtros da tabela principal
        if ($request->filled('search_process')) {
            $transactionsQuery->whereHas('process', fn ($q) => $q->where('title', 'like', '%' . $request->input('search_process') . '%'));
        }
        if ($request->filled('search_contact')) {
            $transactionsQuery->whereHas('process.contact', fn ($q) => 
                $q->where('name', 'like', '%' . $request->input('search_contact') . '%')
                  ->orWhere('business_name', 'like', '%' . $request->input('search_contact') . '%')
            );
        }
        if ($request->filled('payment_type_filter')) {
            $transactionsQuery->where('payment_type', $request->input('payment_type_filter'));
        }
        if ($request->filled('status_filter')) {
            $transactionsQuery->where('status', $request->input('status_filter'));
        }

        // Aplicar ordenação à tabela principal
        if ($tableSortBy === 'process.title') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')
                  ->orderBy('processes.title', $tableSortDirection)
                  ->select('process_payments.*');
        } elseif ($tableSortBy === 'process.contact.name') {
            $transactionsQuery->leftJoin('processes', 'process_payments.process_id', '=', 'processes.id')
                  ->leftJoin('contacts', 'processes.contact_id', '=', 'contacts.id')
                  ->orderBy('contacts.name', $tableSortDirection)
                  ->select('process_payments.*');
        } elseif ($tableSortBy === 'total_value_with_interest') {
            $transactionsQuery->orderBy(DB::raw('process_payments.total_amount + IFNULL(process_payments.interest_amount, 0)'), $tableSortDirection);
        } elseif (in_array($tableSortBy, ['total_amount', 'created_at', 'first_installment_due_date', 'down_payment_date', 'status', 'payment_type'])) {
             $transactionsQuery->orderBy($tableSortBy, $tableSortDirection);
        } else {
            $transactionsQuery->orderBy('first_installment_due_date', 'desc');
        }

        $paginatedTransactions = $transactionsQuery->paginate(10)->withQueryString(); // Menos itens por página para dashboard
        $paginatedTransactions->getCollection()->each->append('status_label');

        // --- Dados para o DASHBOARD ---
        $summaryDateFrom = $request->input('summary_date_from') ? Carbon::parse($request->input('summary_date_from'))->startOfDay() : null;
        $summaryDateTo = $request->input('summary_date_to') ? Carbon::parse($request->input('summary_date_to'))->endOfDay() : null;
        $today = Carbon::today();

        // Query base para sumários do dashboard (afetada pelo período)
        $dashboardSummaryQueryBase = ProcessPayment::query()
            ->when($summaryDateFrom, fn($q) => $q->where(fn($sq) => $sq->whereDate('down_payment_date', '>=', $summaryDateFrom)->orWhereDate('first_installment_due_date', '>=', $summaryDateFrom)))
            ->when($summaryDateTo, fn($q) => $q->where(fn($sq) => $sq->whereDate('down_payment_date', '<=', $summaryDateTo)->orWhereDate('first_installment_due_date', '<=', $summaryDateTo)));

        $totalReceivedInPeriod = (clone $dashboardSummaryQueryBase)
            ->where('status', ProcessPayment::STATUS_PAID)
            // Considera pagamentos efetivados DENTRO do período
            ->whereNotNull('down_payment_date') 
            ->when($summaryDateFrom, fn($q) => $q->whereDate('down_payment_date', '>=', $summaryDateFrom))
            ->when($summaryDateTo, fn($q) => $q->whereDate('down_payment_date', '<=', $summaryDateTo))
            ->sum(DB::raw('total_amount + IFNULL(interest_amount, 0)'));

        // Contas a Receber (Vencidas/Hoje) - Não depende do filtro de período do dashboard
        $accountsReceivableOverdueToday = ProcessPayment::query()
            ->where('status', ProcessPayment::STATUS_PENDING)
            ->whereNotNull('first_installment_due_date')
            ->whereDate('first_installment_due_date', '<=', $today)
            ->sum('total_amount'); // Juros não são somados para pendentes por padrão

        $balanceInPeriod = $totalReceivedInPeriod; // Será totalReceived - totalExpenses (quando despesas forem implementadas)

        // Últimos Recebimentos (dentro do período do dashboard)
        $latestReceivedTransactions = ProcessPayment::query()
            ->with(['process:id,title', 'process.contact:id,name,business_name'])
            ->where('status', ProcessPayment::STATUS_PAID)
            ->whereNotNull('down_payment_date')
            ->when($summaryDateFrom, fn($q) => $q->whereDate('down_payment_date', '>=', $summaryDateFrom))
            ->when($summaryDateTo, fn($q) => $q->whereDate('down_payment_date', '<=', $summaryDateTo))
            ->orderBy('down_payment_date', 'desc')
            ->limit(5)
            ->get();
        $latestReceivedTransactions->each->append('status_label');
        
        // Cobranças Vencendo (Próximos 30 dias) - Não depende do filtro de período do dashboard
        $upcomingDueDateLimit = Carbon::today()->copy()->addDays(30)->endOfDay();
        $upcomingDueTransactions = ProcessPayment::query()
            ->with(['process:id,title', 'process.contact:id,name,business_name'])
            ->where('status', ProcessPayment::STATUS_PENDING)
            ->whereNotNull('first_installment_due_date')
            ->whereBetween('first_installment_due_date', [$today->copy()->startOfDay(), $upcomingDueDateLimit])
            ->orderBy('first_installment_due_date', 'asc')
            ->limit(5)
            ->get();
        $upcomingDueTransactions->each->append('status_label');


        return Inertia::render('Financial/Index', [
            'transactions' => $paginatedTransactions, // Para a tabela principal
            'filters' => $request->only(['search_process', 'search_contact', 'payment_type_filter', 'status_filter', 'sort_by', 'sort_direction', 'summary_date_from', 'summary_date_to']),
            'paymentTypes' => PaymentType::forFrontend(),
            'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
            'dashboardSummary' => [ // Renomeado para clareza
                'totalReceivedInPeriod' => $totalReceivedInPeriod,
                'accountsReceivableOverdueToday' => $accountsReceivableOverdueToday,
                'balanceInPeriod' => $balanceInPeriod,
                // 'totalExpensesInPeriod' => 0, // Placeholder
            ],
            'latestReceivedTransactions' => $latestReceivedTransactions,
            'upcomingDueTransactions' => $upcomingDueTransactions,
        ]);
    }
    // ... outros métodos ...
}
