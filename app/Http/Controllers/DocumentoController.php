<?php

namespace App\Http\Controllers;

use App\Models\Process;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Importar a facade de Log
use Illuminate\Support\Str;
use Inertia\Inertia;
use NumberFormatter;
use Throwable; // Importar para capturar a exceção

class DocumentoController extends Controller
{
    /**
     * Mostra o formulário para preencher as cláusulas do contrato.
     */
    public function showAposentadoriaForm(Process $processo)
    {
        return Inertia::render('Documents/AposentadoriaForm', [
            'process' => $processo->load('contact', 'payments'),
            'clausulaPagamento' => $this->getPaymentClauseText($processo),
        ]);
    }

    /**
     * Gera o PDF do contrato de aposentadoria a partir dos dados do formulário.
     */
    public function gerarAposentadoriaPdf(Request $request, Process $processo)
    {
        // --- ADICIONADO BLOCO TRY-CATCH PARA DEBUG ---
        try {
            $outorgante = $processo->contact;

            if (!$outorgante) {
                abort(404, 'Cliente principal não está associado a este processo.');
            }

            $validatedData = $request->validate([
                'clausula_1' => 'required|string',
                'clausula_2' => 'required|string',
                'clausula_3' => 'required|string',
                'paragrafo_primeiro_clausula_3' => 'required|string',
                'clausula_4' => 'required|string',
                'clausula_5' => 'required|string',
                'clausula_6' => 'required|string',
                'clausula_7' => 'required|string',
                'texto_final' => 'required|string',
            ]);
            
            $dados = array_merge($validatedData, [
                'outorgante' => $outorgante,
                'qualificacao_outorgante' => $this->getQualificacaoCompleta($outorgante),
                'local_emissao' => 'Lagoa Santa/MG',
                'data_emissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
            ]);

            // CORREÇÃO: Usando a barra invertida (\) para chamar a facade Log a partir do namespace raiz.
            \Log::info('Dados para a view do PDF preparados com sucesso. Tentando renderizar...');

            $pdf = Pdf::loadView('pdfs.aposentadoria', $dados);
            
            \Log::info('PDF renderizado com sucesso. Enviando para o browser...');

            $nomeArquivo = "contrato-aposentadoria-" . Str::slug($outorgante->name) . ".pdf";
            
            return $pdf->stream($nomeArquivo);

        } catch (Throwable $e) {
            // Se um erro ocorrer, ele será registado em detalhe.
            \Log::error('================= FALHA NA GERAÇÃO DO PDF =================');
            \Log::error("ERRO: " . $e->getMessage());
            \Log::error("FICHEIRO: " . $e->getFile() . " (Linha: " . $e->getLine() . ")");
            \Log::error("STACK TRACE: " . $e->getTraceAsString());
            \Log::error('===========================================================');
            
            // Retorna uma mensagem de erro clara para o utilizador.
            return response('Ocorreu um erro ao gerar o PDF. Verifique os logs do sistema para mais detalhes.', 500);
        }
    }
    
    /**
     * Gera o texto da cláusula de pagamento dinamicamente para pré-preenchimento.
     */
    private function getPaymentClauseText(Process $processo): string
    {
        $payments = $processo->payments()
            ->where('payment_type', '!=', 'honorario')
            ->orderBy('created_at')
            ->get();

        if ($payments->isEmpty()) {
            return 'Em remuneração pelos serviços profissionais ora contratados, os honorários serão definidos conforme acordo prévio entre as partes.';
        }

        $formatter = new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT);
        $totalContractValue = $payments->sum('total_amount');
        
        $totalAmountFormatted = number_format($totalContractValue, 2, ',', '.');
        $totalAmountText = ucfirst($formatter->format($totalContractValue));
        
        $mainClause = "Em remuneração pelos serviços profissionais ora contratados, serão devidos honorários no importe total de R$ {$totalAmountFormatted} ({$totalAmountText}), a serem pagos da seguinte forma:";
        
        $paymentDescriptions = [];

        $downPayment = $payments->firstWhere('down_payment_amount', '>', 0);
        if ($downPayment) {
            $downPaymentFormatted = number_format($downPayment->down_payment_amount, 2, ',', '.');
            $downPaymentText = ucfirst($formatter->format($downPayment->down_payment_amount));
            $paymentDescriptions[] = "uma entrada de R$ {$downPaymentFormatted} ({$downPaymentText})";
        }

        $installmentPayments = $payments->where('number_of_installments', '>', 0)
                                        ->filter(fn($p) => !$p->down_payment_amount || $p->down_payment_amount == 0);

        if ($installmentPayments->isNotEmpty()) {
            $firstInstallment = $installmentPayments->first();
            $totalInstallments = $firstInstallment->number_of_installments;
            $installmentValue = $firstInstallment->value_of_installment;

            $installmentAmountFormatted = number_format($installmentValue, 2, ',', '.');
            $dueDate = Carbon::parse($firstInstallment->first_installment_due_date)->locale('pt_BR');
            
            $prefix = $downPayment ? "e o saldo remanescente em" : "em";
            
            $paymentDescriptions[] = sprintf(
                "%s %d parcelas de R$ %s, sendo a primeira com vencimento em %s e as demais no mesmo dia nos meses subsequentes",
                $prefix,
                $totalInstallments,
                $installmentAmountFormatted,
                $dueDate->translatedFormat('d \d\e F \d\e Y')
            );
        }
        
        $mainClause .= " " . implode(', ', $paymentDescriptions) . ".";

        $successClause = 'Serão devidos ainda, a título de honorários de êxito, o importe de 30% (trinta por cento) dos valores que vier a receber a título de atrasados, além do correspondente a 02 (dois) salários de benefício.';
        
        return $mainClause . "\n\n" . $successClause;
    }
    
    // O resto do controller permanece o mesmo...
    public function gerarProcuracaoPdf(Process $processo) {
        $outorgante = $processo->contact;
        if (!$outorgante) { abort(404, 'Cliente principal não está associado a este processo.'); }
        $dados = [
            'outorgante' => $outorgante,
            'qualificacao_outorgante' => $this->getQualificacaoCompleta($outorgante),
            'local_emissao' => 'Lagoa Santa/MG',
            'data_emissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
        ];
        $pdf = Pdf::loadView('pdfs.procuracao', $dados);
        return $pdf->stream("procuracao-" . Str::slug($outorgante->name) . ".pdf");
    }
    private function getQualificacaoCompleta($contato) {
        if ($contato->type === 'physical') {
            return sprintf(
                '%s, %s, %s, %s, portador do RG %s e inscrito no CPF sob o nº %s, residente e domiciliado na %s, nº %s, %s, %s/%s, CEP %s.',
                strtoupper($contato->name), $contato->nationality_full_name ?? 'nacionalidade não informada',
                $contato->marital_status_full_name ?? 'estado civil não informado', $contato->profession ?? 'profissão não informada',
                $contato->rg ?? 'RG não informado', $contato->cpf_cnpj ?? 'CPF não informado',
                $contato->address ?? 'endereço não informado', $contato->number ?? 's/n',
                $contato->neighborhood ?? 'bairro não informado', $contato->city ?? 'cidade não informada',
                $contato->state ?? 'UF', $contato->zip_code ?? 'CEP não informado'
            );
        }
        return sprintf(
            '%s, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº %s, com sede na %s, nº %s, %s, %s/%s, CEP %s.',
            strtoupper($contato->business_name), $contato->cpf_cnpj ?? 'CNPJ não informado',
            $contato->address ?? 'endereço não informado', $contato->number ?? 's/n',
            $contato->neighborhood ?? 'bairro não informado', $contato->city ?? 'cidade não informada',
            $contato->state ?? 'UF', $contato->zip_code ?? 'CEP não informado'
        );
    }
}
