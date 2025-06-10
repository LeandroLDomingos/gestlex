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
    <title>CONTRATO APOSENTADORIA</title>
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

        /* ESTILOS PARA A SEÇÃO DE TESTEMUNHAS */
        .testemunhas-section {
            margin-top: 40px;
            /* Espaçamento acima da seção de testemunhas */
            font-family: 'Times New Roman', Times, serif;
            /* Mantém a fonte do corpo do texto */
            font-size: 9pt;
            /* Tamanho da fonte como na imagem de referência */
            text-align: left;
            /* Alinha o texto "Testemunhas:" à esquerda */
        }

        .testemunhas-titulo {
            margin-bottom: 15px;
            /* Espaço abaixo do título "Testemunhas:" */
            font-weight: bold;
        }

        .testemunha {
            margin-bottom: 10px;
            /* Espaço entre as linhas de cada testemunha */
        }

        .testemunha-linha {
            margin-bottom: 8px;
            /* Pequeno espaço abaixo de cada linha de testemunha, se necessário */
            line-height: 1.4;
            /* Ajuste para o espaçamento vertical da linha */
            white-space: nowrap;
            /* Evita que a linha quebre se for muito longa */
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
        <h3>CONTRATO DE PRESTAÇÃO DE SERVIÇOS ADVOCATÍCIOS</h3>
        <br>
        <br>
        <p>
            Pelo presente instrumento particular, que entre si fazem, de um lado como <strong>CLIENTE/CONTRATANTE,
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
            {{ $outorgante->zip_code ?? 'CEP não informado' }}, e, de outro lado, como ADVOGADA, assim doravante
            indicada, Dr.ª FERNANDA LÓREN FERREIRA SANTOS, brasileira, casada, Advogada regularmente inscrita na Ordem
            dos Advogados do Brasil sob o nº 187.526, estabelecida profissionalmente á Rua Coronel Durães, nº 170, Sala
            09, Bela Vista, (31) 98980-3313, CEP 33.239-206, Lagoa Santa/MG, ajustam entre si, com fulcro no artigo 22
            da Lei nº 8.906/94, mediante as seguintes cláusulas e condições

        </p>
        <p>
            <strong>Cláusula Primeira</strong> - A Advogada contratada compromete-se, em cumprimento ao mandato recebido
            a <strong>requerer administrativamente Aposentadoria e ajuste de pendências no CNIS, junto ao INSS e
                solicitar Certidão de Contagem de Tempo e PPP junto a Prefeitura Municipal de Lagoa.</strong>
        </p>
        <p>
            <strong>Cláusula Segunda</strong> – O CONTRATANTE reconhece já haver recebido a orientação preventiva
            comportamental e jurídica para a consecução dos serviços, se compromete a fornecer à ADVOGADA CONTRATADA os
            documentos e meios necessários à comprovação processual do seu pretendido direito, bem como, pagará as
            despesas extrajudiciais que decorrerem da causa, caso haja, nada havendo adiantado para esse fim.
        </p>
        <p>
            <strong>Cláusula Terceira</strong> – Em remuneração pelos serviços profissionais ora contratados serão
            devidos honorários no importe de R$ 2.000,00 (Dois mil reais), divido em 5 parcelas de R$ 400,00
            (Quatrocentos reais), sendo a primeira parcela com vencimento em 06 de junho de 2025 e as demais no mesmo
            dia nos meses subsequentes.
        </p>
        <p>
            Serão devidos ainda, o importe de 30% (trinta por cento) dos valores que vier a receber a título de
            atrasados, além do correspondente a 02 (dois) salários de benefício.
        </p>
        <p>
            Os honorários deverão ser depositados na Conta Corrente Agência 3180, Conta 02006009-5, Banco 033,
            Santander, titular Fernanda Lóren Ferreira Santos. Ou mediante PIX prev.fernandaloren@gmail.com.
        </p>

        <p>
            <strong>Parágrafo Primeiro</strong> – A respectiva quitação será dada quando da emissão do recibo.
        </p>

        <p>
            <strong>Cláusula Quarta </strong>– Outras medidas extrajudiciais ou judiciais necessárias, incidentais ou
            não, diretas ou indiretas, decorrentes da causa ora contratada, devem ter novos honorários estimados com a
            anuência da CONTRATANTE.
        </p>

        <p>
            <strong>Cláusula Quinta</strong> - Considerar-se-ão vencidos e imediatamente exigíveis os honorários ora
            contratados – como se o cliente fosse vencedor – no caso de O CONTRATANTE vir a revogar ou cassar o mandato
            outorgado, optar por não prosseguir com o procedimento por motivos pessoais ou a exigir o substabelecimento
            sem reservas, sem que a ADVOGADA CONTRATADA tenha, para isso, dado causa.
        </p>

        <p>
            <strong>Cláusula Sexta</strong> - A atuação profissional da ADVOGADA CONTRATADA ficará restrita até grau
            recursal. A indicação de advogados para acompanhamento de recursos nos Tribunais Superiores, bem como para
            acompanhamento de eventuais cartas precatórias será da CONTRATANTE, caso este prefira os serviços de outros
            profissionais da sua confiança pessoal.
        </p>

        <p>
            <strong>Cláusula Sétima</strong> - Elegem as partes o foro da Comarca de Lagoa Santa, para dirimir
            controvérsias que possam surgir do presente contrato.
        </p>

        <p>
            E por estarem assim justos e contratados, assinam o presente em duas vias de igual forma e teor, na presença
            de duas testemunhas, para que possa produzir todos os seus efeitos de direito.
        </p>

        <p style="text-align: center;">
            {{ $local_emissao ?? 'Local não informado' }}, {{ $data_emissao ?? 'Data não informada' }}.
        </p>


        <br>

        <div class="assinatura">
            <span class="assinatura-linha"></span>
            <strong>{{ strtoupper($outorgante->name ?: ($outorgante->business_name ?? 'ASSINATURA OUTORGANTE')) }}</strong>
        </div>

        <div class="assinatura">
            <span class="assinatura-linha"></span>
            Dr.ª <strong>FERNANDA LÓREN FERREIRA SANTOS</strong>
        </div>

        <div class="assinatura">
            <p class="no-indent testemunhas-titulo" style="text-align: left; padding-left: 80px;"><strong>Testemunhas:</strong></p>

            <div class="testemunha">
                <p class="no-indent testemunha-linha">1. _________________________________________________________
                    R.G.:                           </p>
            </div>

            <div class="testemunha">
                <p class="no-indent testemunha-linha">2. _________________________________________________________
                    R.G.:                           </p>
            </div>
        </div>
    </main>
</body>

</html>