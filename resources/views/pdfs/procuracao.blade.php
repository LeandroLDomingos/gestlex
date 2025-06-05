{{-- Obtém o caminho absoluto para a imagem --}}
@php
    // Prepara a imagem do cabeçalho
    $headerImagePath = public_path('images/contrato/header_background.png');
    $headerImageData = '';
    $headerImageError = null;

    if (file_exists($headerImagePath)) {
        try {
            $imageType = pathinfo($headerImagePath, PATHINFO_EXTENSION);
            if (empty($imageType))
                $imageType = 'png';
            $headerImageData = 'data:image/' . $imageType . ';base64,' . base64_encode(file_get_contents($headerImagePath));
        } catch (\Exception $e) {
            $headerImageError = "Erro ao carregar imagem do cabeçalho: " . $e->getMessage();
        }
    } else {
        // A mensagem de erro será montada no HTML se a imagem não for encontrada.
    }

    // Função auxiliar para obter ícones como Base64
    function get_icon_base64_data($iconFileName)
    {
        $path = public_path('images/contrato/' . $iconFileName);
        if (file_exists($path)) {
            try {
                $iconType = pathinfo($path, PATHINFO_EXTENSION);
                if (empty($iconType))
                    $iconType = 'png';
                return 'data:image/' . $iconType . ';base64,' . base64_encode(file_get_contents($path));
            } catch (\Exception $e) {
                return '';
            }
        }
        return '';
    }
@endphp

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Procuração e DH</title>
    <style>
        @page {
            /* Margem superior define o espaço para o cabeçalho */
            margin: 150px 80px 100px 80px;
            /* Topo, Direita, Baixo, Esquerda */
        }

        header {
            position: fixed;
            /* 'top' deve ser o negativo da margem superior da página */
            top: -400px;
            left: 0px;
            right: 0px;
            /* 'height' deve ser a altura desejada para a imagem do cabeçalho */
            height: 200px;
            text-align: center;
            /* border: 1px dashed red; DEBUG: para visualizar a área do header */
        }

        .header-image {
            width: 185%;
            /* Imagem ocupa toda a largura do container <header> */
            height: 310%;
            /* Imagem ocupa toda a altura do container <header> */
            object-fit: cover;
            /* Faz a imagem cobrir o espaço, mantendo a proporção e cortando o excesso. */
            /* Alternativas: 'contain' (mostra tudo, pode deixar espaços), 'fill' (estica) */
        }

        .header-error-message {
            background-color: #FFC0CB;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            width: 90%;
            margin: 5px auto;
            box-sizing: border-box;
        }

        footer {
            position: fixed;
            bottom: -80px;
            left: 0px;
            right: 0px;
            height: 80px;
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #555;
            padding: 0 50px;
        }

        .footer-content table {
            width: 100%;
            border-spacing: 5px;
        }

        .footer-content td {
            vertical-align: middle;
        }

        .footer-content img {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 8px;
        }

        main {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.5;
            text-align: justify;
        }

        main h3 {
            text-align: center;
            font-weight: normal;
            letter-spacing: -1px;
            font-family: Cambria, "DejaVu Serif", "Times New Roman", Times, serif;
            /* Fonte atualizada */
        }

        main p {
            margin-bottom: 20px;
        }

        .assinatura {
            text-align: center;
            margin-top: 50px;
        }

        .assinatura-linha {
            display: block;
            width: 70%;
            margin: 0 auto 5px auto;
            border-bottom: 1px solid #000;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>
    <header>
        @if($headerImageData)
            <img src="{{ $headerImageData }}" class="header-image" alt="Cabeçalho do Documento">
        @else
            <div class="header-error-message">
                @if($headerImageError)
                    {{ $headerImageError }}
                @else
                    Imagem do cabeçalho (header_background.png) não encontrada ou não pôde ser carregada. Verifique o caminho:
                    public/images/contrato/header_background.png
                @endif
            </div>
        @endif
    </header>

    <footer>
        <div class="footer-content">
            <table>
                <tr>
                    <td>
                        @if(get_icon_base64_data('phone-icon.png'))
                            <img src="{{ get_icon_base64_data('phone-icon.png') }}" alt="Telefone">
                        @endif
                        (31) 98980-3313
                    </td>
                </tr>
                <tr>
                    <td>
                        @if(get_icon_base64_data('instagram-icon.png'))
                            <img src="{{ get_icon_base64_data('instagram-icon.png') }}" alt="Instagram">
                        @endif
                        @fernandaloren_
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        @if(get_icon_base64_data('location-icon.png'))
                            <img src="{{ get_icon_base64_data('location-icon.png') }}" alt="Endereço">
                        @endif
                        Rua Coronel Durães, nº 170, Sala 09, Bela Vista, Lagoa Santa/MG
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        @if(get_icon_base64_data('email-icon.png'))
                            <img src="{{ get_icon_base64_data('email-icon.png') }}" alt="Email">
                        @endif
                        prev.fernandaloren@gmail.com
                    </td>
                </tr>
            </table>
        </div>
    </footer>

    <main>
        <br>
        <h3>I n s t r u m e n t o d e M a n d a t o<br>A d J u d i c i a E t E x t r a</h3>
        <br>
        <br>
        <p>
            <strong>OUTORGANTES:
                {{ strtoupper($outorgante->name ?: ($outorgante->business_name ?? 'Nome do Outorgante não informado')) }}</strong>,
            @if($outorgante->type === 'physical')
                {{-- ALTERAÇÃO APLICADA AQUI --}}
                {{ $outorgante->nationality_full_name }},
                {{ $outorgante->marital_status ?? 'Estado civil não informado' }},
                {{ $outorgante->profession ?? 'Profissão não informada' }}, portador do registro geral
                {{ $outorgante->rg ?? 'RG não informado' }} e CPF {{ $outorgante->cpf_cnpj ?? 'CPF não informado' }},
            @else
                pessoa jurídica de direito privado, inscrita no CNPJ sob o nº
                {{ $outorgante->cpf_cnpj ?? 'CNPJ não informado' }},
            @endif
            residente e domiciliado a {{ $outorgante->address ?? 'Endereço não informado' }},
            {{ $outorgante->number ?? 'N/A' }}{{ $outorgante->complement ? ', ' . $outorgante->complement : '' }}
            {{ $outorgante->neighborhood ?? 'Bairro não informado' }} –
            {{ $outorgante->city ?? 'Cidade não informada' }} – {{ $outorgante->state ?? 'Estado não informado' }}, CEP
            {{ $outorgante->zip_code ?? 'CEP não informado' }}.
        </p>

        <p>
            Pelo presente Instrumento de Mandato, nomeio e constituo minha bastante procuradora <strong>Fernanda Lóren
                Ferreira Santos</strong>, brasileira, casada, Advogada regularmente inscrita na Ordem dos Advogados do
            Brasil sob o nº 187.526 (151ª Subseção – Seção Minas Gerais), estabelecida profissionalmente na Rua Coronel
            Durães, nº 170, Sala 09, Bela Vista, Lagoa Santa/MG, CEP 33.239-206, com poderes para o Foro em Geral, para
            defender meus interesses perante repartições públicas Federais, Estaduais e Municipais, órgãos da
            administração pública direta e indireta, qualquer Juízo ou Tribunal do país, em qualquer Instância, em que
            eu for autor, réu, assistente, oponente, reclamante, reclamado, litisconsorte ou chamado à autoria podendo o
            dito procurador, para o bom e fiel desempenho deste Mandato receber crédito, desistir, transigir, receber
            citação inicial, reconhecer a procedência do pedido, confessar, firmar termos, acordos, estabelecer ritos de
            arrolamentos, impugnar créditos, oferecer lances e arrematar, habilitar, recorrer, prestar compromisso de
            inventariante, levantar ou receber RPV e ALVARÁS, pedir a justiça gratuita e assinar declaração de
            hipossuficiência econômica, em conformidade com a norma do art. 105 da Lei 13.105/2015, podendo ainda
            substabelecer com ou sem reservas de iguais poderes, do qual dou tudo por bom, firme e valioso,
            especificamente para a presente.

        </p>

        <p style="text-align: right;">
            {{ $local_emissao ?? 'Local não informado' }}, {{ $data_emissao ?? 'Data não informada' }}.
        </p>

        <div class="assinatura">
            <span class="assinatura-linha"></span>
            <strong>{{ strtoupper($outorgante->name ?: ($outorgante->business_name ?? 'ASSINATURA OUTORGANTE')) }}</strong>
        </div>

        <div class="page-break"></div>

        <br>
        <h3>Declaração de Necessitado para Fins Judiciais</h3>
        <br>
        <br>
        <p>
            <strong>{{ strtoupper($outorgante->name ?? 'Nome não informado') }}</strong>,
            {{-- ALTERAÇÃO APLICADA AQUI --}}
            {{ $outorgante->nationality_full_name }},
            {{ $outorgante->marital_status ?? 'Estado civil não informado' }},
            {{ $outorgante->profession ?? 'Profissão não informada' }}, portador do registro geral
            {{ $outorgante->rg ?? 'RG não informado' }} e CPF {{ $outorgante->cpf_cnpj ?? 'CPF não informado' }},
            residente e domiciliado a {{ $outorgante->address ?? 'Endereço não informado' }},
            {{ $outorgante->number ?? 'N/A' }}{{ $outorgante->complement ? ', ' . $outorgante->complement : '' }}
            {{ $outorgante->neighborhood ?? 'Bairro não informado' }} –
            {{ $outorgante->city ?? 'Cidade não informada' }} – {{ $outorgante->state ?? 'Estado não informado' }}, CEP
            {{ $outorgante->zip_code ?? 'CEP não informado' }}, nos termos da Lei 1.060 de 05 de fevereiro de 1950 e
            suas modificações subseqüentes, entre estas a Lei 7.115 de 29 de agosto de 1983 e Lei 7.510 de 04 de julho
            de 1986, sujeitando-se às sanções da esfera administrativa, cível e criminal previstas na legislação da
            República Federativa do Brasil, DECLARA ser pobre na acepção jurídica da palavra, não possuindo, portanto,
            condições, meios ou recursos de arcar com as despesas processuais, honorários advocatícios e demais ônus
            judiciais inerentes ao presente feito, sem prejuízo do sustento próprio e de seus familiares. É a expressão
            da verdade.
        </p>

        <p class="no-indent">É a expressão da verdade.</p>

        <p class="no-indent" style="text-align: right;">
            {{ $local_emissao ?? 'Local não informado' }}, {{ $data_emissao ?? 'Data não informada' }}.
        </p>

        <div class="assinatura">
            <span class="assinatura-linha"></span>
            <strong>{{ strtoupper($outorgante->name ?: ($outorgante->business_name ?? 'ASSINATURA DECLARANTE')) }}</strong>
        </div>
    </main>
</body>

</html>