<?php

namespace App\Http\Controllers;

use App\Models\ProcessPayment;
use App\Models\Process; // Para eager loading
use App\Models\Contact; // Para eager loading
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Carbon\Carbon;

class FinancialTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'sort_by' => 'nullable|string|in:created_at,first_installment_due_date,total_amount,status,payment_type,process.title,process.contact.name',
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

        $query = ProcessPayment::query()
            ->with(['process:id,title,contact_id', 'process.contact:id,name,business_name']); // Eager load para performance

        // Aplicar filtros
        if ($request->filled('search_process')) {
            $query->whereHas('process', fn ($q) => $q->where('title', 'like', '%' . $request->input('search_process') . '%'));
        }
        if ($request->filled('search_contact')) {
            $query->whereHas('process.contact', fn ($q) => $q->where('name', 'like', '%' . $request->input('search_contact') . '%')
                                                            ->orWhere('business_name', 'like', '%' . $request->input('search_contact') . '%'));
        }
        if ($request->filled('payment_type_filter')) {
            $query->where('payment_type', $request->input('payment_type_filter'));
        }
        if ($request->filled('status_filter')) {
            $query->where('status', $request->input('status_filter'));
        }
        if ($request->filled('date_from_filter')) {
            // Filtrar por first_installment_due_date OU down_payment_date (data de pagamento da entrada/honorário)
            $query->where(function($q) use ($request) {
                $q->whereDate('first_installment_due_date', '>=', $request->input('date_from_filter'))
                  ->orWhereDate('down_payment_date', '>=', $request->input('date_from_filter'));
            });
        }
        if ($request->filled('date_to_filter')) {
            $query->where(function($q) use ($request) {
                $q->whereDate('first_installment_due_date', '<=', $request->input('date_to_filter'))
                  ->orWhereDate('down_payment_date', '<=', $request->input('date_to_filter'));
            });
        }

        // Aplicar ordenação
        if ($sortBy === 'process.title') {
            $query->join('processes', 'process_payments.process_id', '=', 'processes.id')
                  ->orderBy('processes.title', $sortDirection)
                  ->select('process_payments.*'); // Evitar ambiguidade de colunas 'id'
        } elseif ($sortBy === 'process.contact.name') {
            $query->join('processes', 'process_payments.process_id', '=', 'processes.id')
                  ->join('contacts', 'processes.contact_id', '=', 'contacts.id')
                  ->orderBy('contacts.name', $sortDirection)
                  ->select('process_payments.*');
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $transactions = $query->paginate(20)->withQueryString();
        $transactions->getCollection()->each->append('status_label');

        // Sumário (exemplo)
        $summaryQueryBase = ProcessPayment::query() // Começa uma nova query para o sumário para não ser afetada pela paginação
            ->when($request->filled('search_process'), fn ($q, $s) => $q->whereHas('process', fn ($sq) => $sq->where('title', 'like', "%{$s}%")))
            ->when($request->filled('search_contact'), fn ($q, $s) => $q->whereHas('process.contact', fn ($sq) => $sq->where('name', 'like', "%{$s}%")->orWhere('business_name', 'like', "%{$s}%")))
            ->when($request->filled('payment_type_filter'), fn ($q, $t) => $q->where('payment_type', $t))
            ->when($request->filled('status_filter'), fn ($q, $st) => $q->where('status', $st))
            ->when($request->filled('date_from_filter'), fn ($q, $d) => $q->where(fn($sq) => $sq->whereDate('first_installment_due_date', '>=', $d)->orWhereDate('down_payment_date', '>=', $d)))
            ->when($request->filled('date_to_filter'), fn ($q, $d) => $q->where(fn($sq) => $sq->whereDate('first_installment_due_date', '<=', $d)->orWhereDate('down_payment_date', '<=', $d)));


        $totalReceived = (clone $summaryQueryBase)->where('status', ProcessPayment::STATUS_PAID)->sum('total_amount');
        $totalPending = (clone $summaryQueryBase)->where('status', ProcessPayment::STATUS_PENDING)->sum('total_amount');
        $totalFees = (clone $summaryQueryBase)->where('payment_type', PaymentType::HONORARIO->value)->sum('total_amount');


        return Inertia::render('Financial/Index', [
            'transactions' => $transactions,
            'filters' => $request->all(),
            'paymentTypes' => PaymentType::forFrontend(), // Todos os tipos
            'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
            'summary' => [
                'totalReceived' => $totalReceived,
                'totalPending' => $totalPending,
                'totalFees' => $totalFees,
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * Este método pode ser usado para adicionar transações avulsas, como despesas.
     * Por agora, vamos focar na listagem e edição.
     */
    public function create(): Response
    {
        // return Inertia::render('Financial/Create', [
        //     'paymentMethods' => ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência Bancária', 'Dinheiro', 'Cheque', 'Outro'],
        //     'paymentTypes' => PaymentType::forFrontend(),
        //     'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
        // ]);
        abort(501, 'Funcionalidade de criar transação financeira genérica não implementada.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Lógica para salvar uma nova transação financeira genérica (ex: despesa)
        // $validatedData = $request->validate([...]);
        // ProcessPayment::create($validatedData);
        // return Redirect::route('financial-transactions.index')->with('success', 'Transação adicionada.');
        abort(501, 'Funcionalidade de criar transação financeira genérica não implementada.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ProcessPayment $financialTransaction): Response // Route model binding
    {
        // Geralmente, a edição é mais útil que um 'show' para transações individuais.
        // Você pode redirecionar para edit ou criar uma view específica se necessário.
        return $this->edit($financialTransaction);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProcessPayment $financialTransaction): Response // Route model binding
    {
        // Carrega informações relacionadas se necessário para o formulário de edição
        $financialTransaction->load(['process:id,title', 'process.contact:id,name,business_name']);
        $financialTransaction->append('status_label'); // Garante que o label do status esteja disponível

        return Inertia::render('Financial/Edit', [ // Você precisará criar esta view
            'transaction' => $financialTransaction,
            'paymentMethods' => ['Cartão de Crédito', 'Boleto', 'PIX', 'Transferência Bancária', 'Dinheiro', 'Cheque', 'Outro'],
            'paymentTypes' => PaymentType::forFrontend(), // Todos os tipos, incluindo honorários
            'paymentStatuses' => ProcessPayment::getStatusesForFrontend(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProcessPayment $financialTransaction) // Route model binding
    {
        // Validação específica para atualização de transação/honorário
        $validatedData = $request->validate([
            'description' => 'sometimes|required_if:payment_type,'.PaymentType::HONORARIO->value.'|string|max:255', // Descrição obrigatória para honorários
            'amount' => 'sometimes|required|numeric|min:0.01', // total_amount da transação
            'fee_date' => 'sometimes|required_if:payment_type,'.PaymentType::HONORARIO->value.'|date_format:Y-m-d', // first_installment_due_date para honorários
            'payment_method' => 'nullable|string|max:100',
            'is_paid' => 'sometimes|required|boolean', // Se foi pago
            'payment_date' => 'nullable|date_format:Y-m-d|required_if:is_paid,true', // down_payment_date para honorários
            'notes' => 'nullable|string|max:1000',
            'status' => ['sometimes','required', Rule::in(array_keys(ProcessPayment::$statuses))], // Atualizar status diretamente
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];

            // Mapear campos do formulário para colunas do banco
            if (isset($validatedData['amount'])) {
                $updateData['total_amount'] = (float) $validatedData['amount'];
                // Se for honorário, value_of_installment é o mesmo que total_amount
                if ($financialTransaction->payment_type === PaymentType::HONORARIO) {
                    $updateData['value_of_installment'] = (float) $validatedData['amount'];
                }
            }

            if (isset($validatedData['fee_date']) && $financialTransaction->payment_type === PaymentType::HONORARIO) {
                $updateData['first_installment_due_date'] = Carbon::parse($validatedData['fee_date']);
            }

            if (array_key_exists('payment_method', $validatedData)) {
                $updateData['payment_method'] = $validatedData['payment_method'];
            }
            
            // Lógica de Status e Data de Pagamento
            if (isset($validatedData['is_paid'])) { // Se o campo is_paid foi enviado
                $updateData['status'] = $validatedData['is_paid'] ? ProcessPayment::STATUS_PAID : ProcessPayment::STATUS_PENDING;
                if ($validatedData['is_paid'] && !empty($validatedData['payment_date'])) {
                    $updateData['down_payment_date'] = Carbon::parse($validatedData['payment_date']);
                } elseif (!$validatedData['is_paid']) {
                    $updateData['down_payment_date'] = null; // Limpa data de pagamento se não estiver pago
                }
            } elseif (isset($validatedData['status'])) { // Se o status foi enviado diretamente
                 $updateData['status'] = $validatedData['status'];
                 if($updateData['status'] !== ProcessPayment::STATUS_PAID){
                     $updateData['down_payment_date'] = null;
                 } elseif ($updateData['status'] === ProcessPayment::STATUS_PAID && empty($financialTransaction->down_payment_date) && empty($validatedData['payment_date'])) {
                    // Se está marcando como pago e não há data de pagamento, define como hoje
                    $updateData['down_payment_date'] = Carbon::now();
                 } elseif(isset($validatedData['payment_date'])){
                     $updateData['down_payment_date'] = Carbon::parse($validatedData['payment_date']);
                 }
            }


            // Lógica para notas (combinando descrição e notas adicionais para honorários)
            if ($financialTransaction->payment_type === PaymentType::HONORARIO) {
                $finalNotes = $validatedData['description'] ?? $financialTransaction->notes; // Usa a nova descrição como base
                if(isset($validatedData['notes']) && !empty(trim($validatedData['notes']))) {
                     // Evita duplicar a descrição se ela já estiver nas notas originais
                    if(strpos($finalNotes, $validatedData['description']) === false || $finalNotes === $validatedData['description']){
                        $finalNotes = $validatedData['description'] . "\nObservações Adicionais: " . $validatedData['notes'];
                    } else { // Se a descrição já está contida e há novas notas
                         $finalNotes = preg_replace('/^.*?\nObservações Adicionais: /s', '', $finalNotes); // Remove a parte antiga das notas adicionais
                         $finalNotes = $validatedData['description'] . "\nObservações Adicionais: " . $validatedData['notes'];
                    }
                }
                $updateData['notes'] = $finalNotes;
            } elseif (array_key_exists('notes', $validatedData)) {
                $updateData['notes'] = $validatedData['notes'];
            }


            if (!empty($updateData)) {
                $financialTransaction->update($updateData);
            }

            // Adicionar ao histórico do processo, se aplicável
            if ($financialTransaction->process) {
                $financialTransaction->process->historyEntries()->create([
                    'action' => 'Transação Financeira Atualizada',
                    'description' => "Transação ID {$financialTransaction->id} (Valor: " . number_format($financialTransaction->total_amount, 2, ',', '.') . ") atualizada.",
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return Redirect::route('financial-transactions.index')->with('success', 'Transação financeira atualizada com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar transação financeira {$financialTransaction->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Ocorreu um erro inesperado ao atualizar a transação: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProcessPayment $financialTransaction) // Route model binding
    {
        // Adicionar verificação de permissão (Policy) se necessário
        // $this->authorize('delete', $financialTransaction);

        DB::beginTransaction();
        try {
            $description = $financialTransaction->notes ?? "Transação ID {$financialTransaction->id}";
            $processId = $financialTransaction->process_id;

            $financialTransaction->delete(); // Soft delete, se configurado no model

            // Adicionar ao histórico do processo, se aplicável
            if ($processId && $process = Process::find($processId)) {
                $process->historyEntries()->create([
                    'action' => 'Transação Financeira Excluída',
                    'description' => "Transação '{$description}' (ID: {$financialTransaction->id}) foi excluída.",
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return Redirect::route('financial-transactions.index')->with('success', 'Transação financeira excluída com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir transação financeira {$financialTransaction->id}: " . $e->getMessage());
            return Redirect::route('financial-transactions.index')->with('error', 'Ocorreu um erro ao excluir a transação.');
        }
    }
}
