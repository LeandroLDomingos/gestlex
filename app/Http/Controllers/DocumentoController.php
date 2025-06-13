<?php

namespace App\Http\Controllers;

use App\Models\Process;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use NumberFormatter;

class DocumentoController extends Controller
{
    // --- CONTRATO DE APOSENTADORIA ---

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

        $pdf = Pdf::loadView('pdfs.aposentadoria', $dados);
        $nomeArquivo = "contrato-aposentadoria-" . Str::slug($outorgante->name) . ".pdf";
        return $pdf->stream($nomeArquivo);
    }

    // --- DECLARAÇÃO DE NECESSITADO ---

    /**
     * Mostra o formulário para preencher a declaração de necessitado.
     */
    public function showDeclaracaoNecessitadoForm(Process $processo)
    {
        return Inertia::render('Documents/DeclaracaoNecessitadoForm', [
            'process' => $processo->load('contact'),
            'textoDeclaracao' => $this->getDeclaracaoNecessitadoText($processo),
        ]);
    }

    /**
     * Gera o PDF da declaração a partir dos dados do formulário.
     */
    public function gerarDeclaracaoNecessitadoPdf(Request $request, Process $processo)
    {
        $outorgante = $processo->contact;
        if (!$outorgante) {
            abort(404);
        }

        $validatedData = $request->validate(['texto_declaracao' => 'required|string']);

        $dados = array_merge($validatedData, [
            'local_emissao' => 'Lagoa Santa/MG',
            'data_emissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
            'outorgante' => $outorgante,
        ]);

        $pdf = Pdf::loadView('pdfs.declaracao_necessitado', $dados);
        $nomeArquivo = "declaracao-necessitado-" . Str::slug($outorgante->name) . ".pdf";
        return $pdf->stream($nomeArquivo);
    }


    // --- PROCURAÇÃO ---
    
    /**
     * Mostra o formulário para preencher a procuração.
     */
    public function showProcuracaoForm(Process $processo)
    {
        return Inertia::render('Documents/ProcuracaoForm', [
            'process' => $processo->load('contact'),
            'qualificacaoOutorgante' => $this->getQualificacaoCompleta($processo->contact),
        ]);
    }

    /**
     * Gera o PDF da procuração a partir dos dados do formulário.
     */
    public function gerarProcuracaoPdf(Request $request, Process $processo)
    {
        $outorgante = $processo->contact;
        if (!$outorgante) {
            abort(404, 'Cliente principal não está associado a este processo.');
        }

        // Validação completa para os campos do formulário da procuração
        $validatedData = $request->validate([
            'outorgante_qualificacao' => 'required|string',
            'outorgados_qualificacao' => 'required|string',
            'poderes' => 'required|string',
            'poderes_especificos' => 'required|string',
        ]);

        $dados = array_merge($validatedData, [
            'local_emissao' => 'Lagoa Santa/MG',
            'data_emissao' => Carbon::now()->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y'),
            'outorgante' => $outorgante,
        ]);

        $pdf = Pdf::loadView('pdfs.procuracao', $dados);
        $nomeArquivo = "procuracao-" . Str::slug($outorgante->name) . ".pdf";
        return $pdf->stream($nomeArquivo);
    }

    // --- MÉTODOS AUXILIARES ---

    /**
     * Gera o texto da cláusula de pagamento dinamicamente.
     */
    private function getPaymentClauseText(Process $processo): string
    {
        $payments = $processo->payments()
            ->where('payment_type', '!=', 'honorario') // Corrected based on your controller
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

        // 1. Identifica e descreve a entrada
        $downPayment = $payments->firstWhere('down_payment_amount', '>', 0);
        if ($downPayment) {
            $downPaymentFormatted = number_format($downPayment->down_payment_amount, 2, ',', '.');
            $downPaymentText = ucfirst($formatter->format($downPayment->down_payment_amount));
            $paymentDescriptions[] = "uma entrada de R$ {$downPaymentFormatted} ({$downPaymentText})";
        }

        // 2. Identifica, conta e descreve as parcelas
        $installmentPayments = $payments->where('value_of_installment', '>', 0)
                                        ->where(fn($q) => $q->where('down_payment_amount', 0)->orWhereNull('down_payment_amount'));

        if ($installmentPayments->isNotEmpty()) {
            $firstInstallment = $installmentPayments->first();
            $installmentCount = $installmentPayments->count(); // CORREÇÃO: Conta o número de registos de parcelas
            $installmentValue = $firstInstallment->value_of_installment;

            $installmentAmountFormatted = number_format($installmentValue, 2, ',', '.');
            $dueDate = Carbon::parse($firstInstallment->first_installment_due_date)->locale('pt_BR');
            
            $prefix = $downPayment ? "e o saldo remanescente em" : "em";
            
            $paymentDescriptions[] = sprintf(
                "%s %d parcelas de R$ %s, sendo a primeira com vencimento em %s e as demais no mesmo dia nos meses subsequentes",
                $prefix,
                $installmentCount,
                $installmentAmountFormatted,
                $dueDate->translatedFormat('d \d\e F \d\e Y')
            );
        }
        
        $mainClause .= " " . implode(', ', $paymentDescriptions) . ".";

        $successClause = 'Serão devidos ainda, a título de honorários de êxito, o importe de 30% (trinta por cento) dos valores que vier a receber a título de atrasados, além do correspondente a 02 (dois) salários de benefício.';
        return $mainClause . "\n\n" . $successClause;
    }

    /**
     * Gera a string de qualificação completa para um contato.
     */
    private function getQualificacaoCompleta($contato, $uppercase = true)
    {
        $nome = $uppercase ? strtoupper($contato->name) : $contato->name;
        $cpfCnpjFormatado = $this->formatCpfCnpj($contato->cpf_cnpj);
        $cepFormatado = $this->formatCep($contato->zip_code);
        $complemento = !empty($contato->complement) ? ', ' . $contato->complement : '';

        if ($contato->type === 'physical') {
            return sprintf(
                '%s, %s, %s, %s, portador do RG %s e inscrito no CPF sob o nº %s, residente e domiciliado na %s, nº %s%s, %s, %s/%s, CEP %s.',
                $nome,
                $contato->nationality_full_name ?? 'nacionalidade não informada',
                $contato->marital_status_label ?? 'estado civil não informado',
                $contato->profession ?? 'profissão não informada',
                $contato->rg ?? 'RG não informado',
                $cpfCnpjFormatado,
                $contato->address ?? 'endereço não informado',
                $contato->number ?? 's/n',
                $complemento,
                $contato->neighborhood ?? 'bairro não informado',
                $contato->city ?? 'cidade não informada',
                $contato->state ?? 'UF',
                $cepFormatado
            );
        }
        return sprintf(
            '%s, pessoa jurídica de direito privado, inscrita no CNPJ sob o nº %s, com sede na %s, nº %s%s, %s, %s/%s, CEP %s.',
            strtoupper($contato->business_name),
            $cpfCnpjFormatado,
            $contato->address ?? 'endereço não informado',
            $contato->number ?? 's/n',
            $complemento,
            $contato->neighborhood ?? 'bairro não informado',
            $contato->city ?? 'cidade não informada',
            $contato->state ?? 'UF',
            $cepFormatado
        );
    }

    private function getDeclaracaoNecessitadoText(Process $processo): string
    {
        $qualificacao = $this->getQualificacaoCompleta($processo->contact, false);
        return "{$qualificacao}, nos termos da Lei 1.060 de 05 de fevereiro de 1950 e suas modificações subsequentes, entre estas a Lei 7.115 de 29 de agosto de 1983 e Lei 7.510 de 04 de julho de 1986, sujeitando-se às sanções da esfera administrativa, cível e criminal previstas na legislação da República Federativa do Brasil, DECLARA ser pobre na acepção jurídica da palavra, não possuindo, portanto, condições, meios ou recursos de arcar com as despesas processuais, honorários advocatícios e demais ônus judiciais inerentes ao presente feito, sem prejuízo do sustento próprio e de seus familiares. É a expressão da verdade.";
    }
    
    private function formatCpfCnpj($value)
    {
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        if (strlen($value) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $value);
        }
        if (strlen($value) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $value);
        }
        return $value;
    }

    private function formatCep($value)
    {
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        if (strlen($value) === 8) {
            return substr($value, 0, 2) . '.' . substr($value, 2, 3) . '-'. substr($value, 5, 3);
        }
        return $value;
    }
}
