<?php

namespace App\Http\Controllers;

use App\Models\ProcessPayment; // Usaremos este model para buscar todas as transações
use App\Models\Process; // Para buscar informações do processo associado
use App\Models\Contact; // Para buscar informações do contato associado
use App\Enums\PaymentType; // Para filtrar por tipo
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'sort_by' => 'nullable|string|in:created_at,first_installment_due_date,total_amount,status,payment_type',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'search_process' => 'nullable|string|max:255',
            'search_contact' => 'nullable|string|max:255',
            'payment_type_filter' => 'nullable|string',
            'status_filter' => 'nullable|string',
            'date_from_filter' => 'nullable|date_format:Y-m-d',
            'date_to_filter' => 'nullable|date_format:Y-m-d|after_or_equal:date_from_filter',
        ]);

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $paymentsQuery = ProcessPayment::with(['process:id,title', 'process.contact:id,name,business_name'])
            ->select( // Seleciona apenas as colunas necessárias para otimizar
                'id', 'process_id', 'total_amount', 'payment_type', 'payment_method',
                'down_payment_date', 'first_installment_due_date', 'status', 'notes', 'created_at',
                'value_of_installment', 'number_of_installments', 'down_payment_amount' // Incluindo campos relevantes
            )
            ->when($request->input('search_process'), function ($query, $search) {
                $query->whereHas('process', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            })
            ->when($request->input('search_contact'), function ($query, $search) {
                $query->whereHas('process.contact', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('business_name', 'like', "%{$search}%");
                });
            })
            ->when($request->input('payment_type_filter'), function ($query, $type) {
                $query->where('payment_type', $type);
            })
            ->when($request->input('status_filter'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->input('date_from_filter'), function ($query, $dateFrom) {
                // Considerar a data de vencimento (first_installment_due_date) ou criação (created_at)
                $query->whereDate('first_installment_due_date', '>=', $dateFrom);
            })
            ->when($request->input('date_to_filter'), function ($query, $dateTo) {
                $query->whereDate('first_installment_due_date', '<=', $dateTo);
            });

        $payments = $paymentsQuery->orderBy($sortBy, $sortDirection)
            ->paginate(20) // Ou o número de itens que preferir
            ->withQueryString();

        // Anexa o status_label para cada pagamento
        $payments->getCollection()->each->append('status_label');


        // Totais para o período filtrado (exemplo)
        $totalReceived = (clone $paymentsQuery)->where('status', ProcessPayment::STATUS_PAID)->sum('total_amount');
        $totalPending = (clone $paymentsQuery)->where('status', ProcessPayment::STATUS_PENDING)->sum('total_amount');


        return Inertia::render('Financial/Index', [
            'payments' => $payments,
            'filters' => $request->only([
                'sort_by', 'sort_direction', 'search_process', 'search_contact',
                'payment_type_filter', 'status_filter', 'date_from_filter', 'date_to_filter'
            ]),
            'paymentTypes' => \App\Enums\PaymentType::forFrontend(), // Todos os tipos para o filtro
            'paymentStatuses' => \App\Models\ProcessPayment::getStatusesForFrontend(), // Status para o filtro
            'summary' => [
                'totalReceived' => $totalReceived,
                'totalPending' => $totalPending,
            ]
        ]);
    }
}