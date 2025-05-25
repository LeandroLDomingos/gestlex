<?php

namespace App\Http\Controllers;

use App\Models\ProcessPayment;
use App\Models\Expense; // Nosso novo model
use App\Enums\PaymentType as ProcessPaymentTypeEnum; // Para diferenciar de outros tipos de pagamento
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class FinancialDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'period_start' => 'nullable|date_format:Y-m-d',
            'period_end' => 'nullable|date_format:Y-m-d|after_or_equal:period_start',
        ]);

        $periodStart = $request->input('period_start') ? Carbon::parse($request->input('period_start'))->startOfDay() : Carbon::now()->startOfMonth();
        $periodEnd = $request->input('period_end') ? Carbon::parse($request->input('period_end'))->endOfDay() : Carbon::now()->endOfMonth();

        // --- RECEBIMENTOS (de ProcessPayment) ---
        $receiptsQuery = ProcessPayment::query()
            ->where('status', ProcessPayment::STATUS_PAID) // Apenas os pagos
            ->whereBetween('down_payment_date', [$periodStart, $periodEnd]); // Data de pagamento efetivo

        $totalReceived = $receiptsQuery->sum('total_amount');
        $recentReceipts = (clone $receiptsQuery)->with('process:id,title')->latest('down_payment_date')->take(5)->get()->append('status_label');

        // --- DESPESAS ---
        $expensesQuery = Expense::query()
            ->where('status', Expense::STATUS_PAID) // Apenas pagas
            ->whereBetween('expense_date', [$periodStart, $periodEnd]);

        $totalExpenses = $expensesQuery->sum('amount');
        $recentExpenses = (clone $expensesQuery)->with('process:id,title')->latest('expense_date')->take(5)->get()->append('status_label');

        // --- CONTROLE DE COBRANÇAS (Contas a Receber) ---
        // Pagamentos pendentes (parcelas, honorários) com data de vencimento no futuro ou já vencidos
        $pendingPaymentsQuery = ProcessPayment::query()
            ->where('status', ProcessPayment::STATUS_PENDING)
            ->whereNotNull('first_installment_due_date');

        $totalPendingToReceive = (clone $pendingPaymentsQuery)->whereDate('first_installment_due_date', '<=', Carbon::now()->endOfDay())->sum('total_amount'); // Vencidos ou vencendo hoje
        $upcomingReceivables = (clone $pendingPaymentsQuery)
            ->with('process:id,title', 'process.contact:id,name,business_name')
            ->whereBetween('first_installment_due_date', [Carbon::now()->startOfDay(), Carbon::now()->addMonths(1)->endOfDay()]) // Próximos 30 dias
            ->orderBy('first_installment_due_date', 'asc')
            ->take(10)
            ->get()
            ->append('status_label');

        // --- PATRIMÔNIO (Muito Simplificado por enquanto) ---
        // Poderia ser um cálculo mais complexo ou um valor informado manualmente.
        // Aqui, um exemplo simples: Saldo do Período
        $netBalanceForPeriod = $totalReceived - $totalExpenses;

        return Inertia::render('Financial/Dashboard', [
            'filters' => [
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'kpis' => [
                'totalReceived' => $totalReceived,
                'totalExpenses' => $totalExpenses,
                'netBalanceForPeriod' => $netBalanceForPeriod,
                'totalPendingToReceive' => $totalPendingToReceive,
            ],
            'recentReceipts' => $recentReceipts,
            'recentExpenses' => $recentExpenses,
            'upcomingReceivables' => $upcomingReceivables,
            'paymentTypes' => \App\Enums\PaymentType::forFrontend(), // Para filtros futuros no dashboard
            'paymentStatuses' => \App\Models\ProcessPayment::getStatusesForFrontend(),
            'expenseStatuses' => \App\Models\Expense::getStatusesForFrontend(),
        ]);
    }
}