<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Models\Process;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FinancialTransactionController extends Controller
{
    // Definição dos status de transação para consistência
    protected function getTransactionStatuses(): array
    {
        return [
            'Confirmado' => 'Confirmado',
            'Pendente' => 'Pendente',
            'Agendado' => 'Agendado',
            'Cancelado' => 'Cancelado',
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Query base para a lista de transações paginadas e filtradas
        $query = FinancialTransaction::with(['process:id,title', 'contact:id,name,business_name,display_name', 'createdBy:id,name'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Aplicar filtros à query principal
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            try {
                $query->whereDate('transaction_date', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
            } catch (\Exception $e) { /* Ignora data inválida */ }
        }
        if ($request->filled('date_to')) {
            try {
                $query->whereDate('transaction_date', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            } catch (\Exception $e) { /* Ignora data inválida */ }
        }
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('category', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('payment_method', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('process', fn($pq) => $pq->where('title', 'LIKE', "%{$searchTerm}%"))
                  ->orWhereHas('contact', fn($cq) => $cq->where('name', 'LIKE', "%{$searchTerm}%")->orWhere('business_name', 'LIKE', "%{$searchTerm}%"));
            });
        }

        $transactions = $query->paginate(15)->withQueryString(); // 15 por página ou ajuste conforme necessário

        // Cálculo dos dados de resumo para o dashboard
        // Estes cálculos podem ser baseados em todas as transações ou em um subconjunto específico (ex: transações confirmadas)
        // Para este exemplo, vamos calcular com base em todas as transações confirmadas.
        $allConfirmedTransactions = FinancialTransaction::where('status', 'Confirmado')->get();

        $totalIncome = $allConfirmedTransactions->where('type', 'income')->sum('amount');
        $totalExpense = $allConfirmedTransactions->where('type', 'expense')->sum('amount'); // Expenses são negativas
        $currentBalance = $totalIncome + $totalExpense; // Como expenses são negativas, a soma direta funciona

        // ProjectedBalance e balanceAsOfDate podem exigir lógica mais complexa (ex: incluir transações agendadas)
        // Por agora, podemos passar valores placeholder ou calcular de forma simplificada.
        // Exemplo: Saldo projetado incluindo agendadas
        $allConfirmedAndScheduled = FinancialTransaction::whereIn('status', ['Confirmado', 'Agendado'])->get();
        $projectedBalance = $allConfirmedAndScheduled->sum('amount');


        return Inertia::render('Finance/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['type', 'status', 'date_from', 'date_to', 'search']),
            'transactionTypes' => ['income' => 'Entrada', 'expense' => 'Saída'],
            'transactionStatuses' => $this->getTransactionStatuses(),
            'processes' => Process::whereNull('archived_at')->orderBy('title')->get(['id', 'title']), // Para o formulário
            'contacts' => Contact::orderBy('name') // Para o formulário
                ->select('id', 'name', 'business_name', 'type') // type é necessário para display_name se for um acessor
                ->get()
                ->map(fn($contact) => ['id' => $contact->id, 'display_name' => $contact->display_name]), // Assegure que Contact model tem getDisplayNameAttribute
            // Dados para o Dashboard
            'totalIncome' => (float) $totalIncome,
            'totalExpense' => (float) abs($totalExpense), // Enviar como valor positivo para exibição de "Total de Saídas"
            'currentBalance' => (float) $currentBalance,
            'projectedBalance' => (float) $projectedBalance, // Exemplo de saldo projetado
            'balanceAsOfDate' => Carbon::now()->format('d M'), // Exemplo: "22 Mai"
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * Este método pode não ser diretamente usado se o formulário estiver sempre em um modal no Index.
     * Mas é bom tê-lo para consistência ou acesso direto.
     */
    public function create(): Response
    {
        return Inertia::render('Finance/Create', [ // Ou um componente de formulário dedicado
            'processes' => Process::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'contacts' => Contact::orderBy('name')
                ->select('id', 'name', 'business_name', 'type')
                ->get()
                ->map(fn($contact) => ['id' => $contact->id, 'display_name' => $contact->display_name]),
            'transactionTypes' => ['income' => 'Entrada', 'expense' => 'Saída'],
            'transactionStatuses' => $this->getTransactionStatuses(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'transaction_date' => 'required|date_format:Y-m-d',
            'process_id' => 'nullable|uuid|exists:processes,id', // ou 'integer' se não for UUID
            'contact_id' => 'nullable|uuid|exists:contacts,id', // ou 'integer' se não for UUID
            'notes' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:100',
            'status' => 'required|string|max:50|in:'.implode(',', array_keys($this->getTransactionStatuses())),
            'due_date' => 'nullable|date_format:Y-m-d',
            'paid_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $amount = (float) $validatedData['amount'];
        if ($validatedData['type'] === 'expense') {
            $amount = -$amount; // Armazena despesas como negativas
        }

        DB::beginTransaction();
        try {
            $transaction = FinancialTransaction::create([
                'description' => $validatedData['description'],
                'amount' => $amount,
                'type' => $validatedData['type'],
                'transaction_date' => $validatedData['transaction_date'],
                'process_id' => $validatedData['process_id'] ?? null,
                'contact_id' => $validatedData['contact_id'] ?? null,
                'created_by_user_id' => Auth::id(),
                'notes' => $validatedData['notes'] ?? null,
                'category' => $validatedData['category'] ?? null,
                'payment_method' => $validatedData['payment_method'] ?? null,
                'status' => $validatedData['status'],
                'due_date' => $validatedData['due_date'] ?? null,
                'paid_at' => $validatedData['paid_at'] ?? null,
            ]);

            if ($transaction->process_id) {
                $process = Process::find($transaction->process_id);
                if ($process && !$process->isArchived()) { // Adiciona verificação de arquivamento
                    $typeLabel = $transaction->type === 'income' ? 'Receita' : 'Despesa';
                    $process->historyEntries()->create([
                        'action' => "{$typeLabel} Registrada",
                        'description' => "{$typeLabel} de R$ " . number_format(abs($transaction->amount), 2, ',', '.') . " ('{$transaction->description}') registrada para o caso. Status: {$transaction->status}.",
                        'user_id' => Auth::id(),
                    ]);
                }
            }
            DB::commit();
            return redirect()->route('financial-transactions.index')->with('success', 'Transação registrada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registrar transação financeira: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->with('error', 'Falha ao registrar transação. Reveja os dados e tente novamente.')->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialTransaction $financialTransaction): Response
    {
        $financialTransaction->load(['process:id,title', 'contact:id,name,business_name,display_name', 'createdBy:id,name']);
        return Inertia::render('Finance/Show', [ // Crie esta view se precisar de uma página de detalhes dedicada
            'transaction' => $financialTransaction,
            'transactionTypes' => ['income' => 'Entrada', 'expense' => 'Saída'],
            'transactionStatuses' => $this->getTransactionStatuses(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * Normalmente usado para carregar dados para um modal de edição ou uma página de edição.
     */
    public function edit(FinancialTransaction $financialTransaction): Response
    {
        $financialTransaction->load(['process:id,title', 'contact:id,name,business_name,display_name', 'createdBy:id,name']);
        // O formulário de edição é geralmente um modal na página Index ou Show,
        // ou uma página dedicada 'Finance/Edit'.
        // Se for uma página dedicada:
        return Inertia::render('Finance/Edit', [
            'transaction' => $financialTransaction,
            'processes' => Process::whereNull('archived_at')->orderBy('title')->get(['id', 'title']),
            'contacts' => Contact::orderBy('name')
                ->select('id', 'name', 'business_name', 'type')
                ->get()
                ->map(fn($contact) => ['id' => $contact->id, 'display_name' => $contact->display_name]),
            'transactionTypes' => ['income' => 'Entrada', 'expense' => 'Saída'],
            'transactionStatuses' => $this->getTransactionStatuses(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialTransaction $financialTransaction)
    {
        $validatedData = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'transaction_date' => 'required|date_format:Y-m-d',
            'process_id' => 'nullable|uuid|exists:processes,id',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'notes' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:100',
            'payment_method' => 'nullable|string|max:100',
            'status' => 'required|string|max:50|in:'.implode(',', array_keys($this->getTransactionStatuses())),
            'due_date' => 'nullable|date_format:Y-m-d',
            'paid_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        $amount = (float) $validatedData['amount'];
        if ($validatedData['type'] === 'expense') {
            $amount = -$amount;
        }
        
        if ($financialTransaction->process && $financialTransaction->process->isArchived()) {
            if (($validatedData['process_id'] ?? null) !== $financialTransaction->process_id) {
                return back()->with('error', 'Não é possível desvincular ou alterar o processo de uma transação ligada a um caso arquivado.')->withInput();
            }
            // Poderia adicionar outras restrições, como não permitir alterar valores de transações de casos arquivados.
        }
        if (($validatedData['process_id'] ?? null) && ($validatedData['process_id'] ?? null) !== $financialTransaction->process_id) {
            $newProcess = Process::find($validatedData['process_id']);
            if ($newProcess && $newProcess->isArchived()) {
                return back()->with('error', 'Não é possível vincular a transação a um caso arquivado.')->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $financialTransaction->update($validatedData); // $amount já está tratado em $validatedData se sobrescrevermos
            // Se $amount não for parte de $validatedData diretamente para o update:
            $updatePayload = $validatedData;
            $updatePayload['amount'] = $amount;
            $financialTransaction->update($updatePayload);


            DB::commit();
            // O redirect pode ser para o 'show' ou 'index'
            $redirectRoute = $request->input('from_process_show') && $financialTransaction->process_id
                ? route('processes.show', $financialTransaction->process_id)
                : route('financial-transactions.index');

            return redirect($redirectRoute)->with('success', 'Transação atualizada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar transação financeira {$financialTransaction->id}: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
            return back()->with('error', 'Falha ao atualizar transação. Reveja os dados e tente novamente.')->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialTransaction $financialTransaction)
    {
        if ($financialTransaction->process && $financialTransaction->process->isArchived()) {
            return back()->with('error', 'Não é possível excluir transações de um caso arquivado.');
        }

        DB::beginTransaction();
        try {
            $description = $financialTransaction->description; // Guardar antes de deletar se for usar no log
            $originalAmount = $financialTransaction->amount;
            $originalType = $financialTransaction->type;
            $processId = $financialTransaction->process_id;

            $financialTransaction->delete();

            if ($processId) {
                $process = Process::find($processId);
                 if ($process && !$process->isArchived()) {
                    $typeLabel = $originalType === 'income' ? 'Receita' : 'Despesa';
                    $process->historyEntries()->create([
                        'action' => "{$typeLabel} Excluída",
                        'description' => "{$typeLabel} \"{$description}\" de R$ " . number_format(abs($originalAmount), 2, ',', '.') . " foi excluída do caso.",
                        'user_id' => Auth::id(),
                    ]);
                }
            }
            DB::commit();
            return redirect()->route('financial-transactions.index')->with('success', 'Transação excluída com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir transação financeira {$financialTransaction->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir transação.');
        }
    }
}
