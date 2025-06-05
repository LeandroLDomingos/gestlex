<?php

// app/Http/Controllers/DocumentoController.php
namespace App\Http\Controllers;

use App\Models\Process;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DocumentoController extends Controller
{
    public function gerarProcuracaoPdf(Process $processo)
    {
        $processo->load('contact', 'responsible');

        $outorgante = $processo->contact;
        $outorgado = $processo->responsible;

        if (!$outorgante) {
            abort(404, 'Cliente (outorgante) não encontrado para este processo.');
        }
        if (!$outorgado) {
            abort(404, 'Advogado (outorgado) não definido para este processo.');
        }

        $dados = [
            'outorgante' => $outorgante,
            'outorgado' => $outorgado,
            'data_emissao' => Carbon::now()->isoFormat('D [de] MMMM [de] YYYY'),
            'local_emissao' => 'Lagoa Santa',
        ];

        $pdf = Pdf::loadView('pdfs.procuracao', $dados);

        // ATUALIZADO: Usa 'name' ou 'business_name' para o nome do arquivo
        $nomeCliente = $outorgante->name ?: $outorgante->business_name;
        $nomeArquivo = 'procuracao-dh-' . Str::slug($nomeCliente) . '.pdf';

        return $pdf->stream($nomeArquivo);
    }

    public function gerarAposentadoriaPdf(Process $processo)
    {
        $processo->load('contact', 'responsible');

        $outorgante = $processo->contact;
        $outorgado = $processo->responsible;

        if (!$outorgante) {
            abort(404, 'Cliente (outorgante) não encontrado para este processo.');
        }
        if (!$outorgado) {
            abort(404, 'Advogado (outorgado) não definido para este processo.');
        }

        $dados = [
            'outorgante' => $outorgante,
            'outorgado' => $outorgado,
            'data_emissao' => Carbon::now()->isoFormat('D [de] MMMM [de] YYYY'),
            'local_emissao' => 'Lagoa Santa',
        ];

        $pdf = Pdf::loadView('pdfs.aposentadoria', $dados);

        // ATUALIZADO: Usa 'name' ou 'business_name' para o nome do arquivo
        $nomeCliente = $outorgante->name ?: $outorgante->business_name;
        $nomeArquivo = 'aposentadoria' . Str::slug($nomeCliente) . '.pdf';

        return $pdf->stream($nomeArquivo);
    }
}