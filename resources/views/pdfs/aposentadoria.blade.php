@php
    // Carrega a imagem de fundo do cabeçalho
    $headerImagePath = public_path('images/contrato/header_background.png');
    $headerImageData = '';
    if (file_exists($headerImagePath)) {
        $imageType = pathinfo($headerImagePath, PATHINFO_EXTENSION);
        $headerImageData = 'data:image/' . ($imageType ?: 'png') . ';base64,' . base64_encode(file_get_contents($headerImagePath));
    }

    // Carrega a imagem do logo
    $logoImagePath = public_path('images/contrato/logo.png'); // Certifique-se de que o seu logo está neste caminho
    $logoImageData = '';
    if (file_exists($logoImagePath)) {
        $logoImageType = pathinfo($logoImagePath, PATHINFO_EXTENSION);
        $logoImageData = 'data:image/' . ($logoImageType ?: 'png') . ';base64,' . base64_encode(file_get_contents($logoImagePath));
    }

    // Função auxiliar para ícones do rodapé (sem alterações)
    function get_icon_base64_data($iconFileName) {
        $path = public_path('images/contrato/' . $iconFileName);
        if (file_exists($path)) {
            $iconType = pathinfo($path, PATHINFO_EXTENSION);
            return 'data:image/' . ($iconType ?: 'png') . ';base64,' . base64_encode(file_get_contents($path));
        }
        return '';
    }
@endphp

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Contrato</title>
    <style>
        @page {
            margin: 150px 80px 100px 80px; /* Topo, Direita, Baixo, Esquerda */
        }

        /* --- ESTILO DO HEADER MODIFICADO --- */
        header {
            position: fixed;
            top: -310px; /* SEU VALOR ORIGINAL MANTIDO */
            left: 0px;
            right: 0px;
            height: 250px; /* SEU VALOR ORIGINAL MANTIDO */
            text-align: center;
        }

        .header-image {
            width: 185%; /* SEU VALOR ORIGINAL MANTIDO */
            height: 310%; /* SEU VALOR ORIGINAL MANTIDO */
            object-fit: cover;
        }

        /* --- NOVO: Estilo para o logo sobreposto --- */
        .logo-overlay {
            position: absolute;
            /* Posiciona o logo no meio do espaço visível do cabeçalho */
            top: 185px;  /* Ajustado para alinhar verticalmente com seus valores */
            left: 50%;
            transform: translateX(-50%);
            /* Defina um tamanho para o seu logo */
            max-width: 230px;
            max-height: 150px;
            z-index: 10; /* Garante que o logo fique por cima */
        }

        /* --- Resto do seu CSS (sem alterações) --- */
        footer {
            position: fixed;
            bottom: -80px; left: 0px; right: 0px; height: 80px;
            font-family: Arial, sans-serif; font-size: 9pt; color: #555; padding: 0 50px;
        }
        .footer-content table { width: 100%; border-spacing: 5px; }
        .footer-content td { vertical-align: middle; }
        .footer-content img { width: 14px; height: 14px; vertical-align: middle; margin-right: 8px; }
        main { font-family: 'Times New Roman', Times, serif; font-size: 11pt; line-height: 1.5; text-align: justify; }
        main h3 { text-align: center; font-weight: normal; font-family: Cambria, "DejaVu Serif", "Times New Roman", Times, serif; }
        main p { margin-bottom: 20px; }
        .assinatura { text-align: center; margin-top: 50px; }
        .assinatura-linha { display: block; width: 70%; margin: 0 auto 5px auto; border-bottom: 1px solid #000; }
        .testemunhas-section { margin-top: 40px; font-size: 9pt; text-align: left; }
        .testemunhas-titulo { margin-bottom: 15px; font-weight: bold; }
        .testemunha { margin-bottom: 10px; }
        .no-indent { text-indent: 0; }
    </style>
</head>
<body>
    {{-- --- HEADER MODIFICADO --- --}}
    <header>
        {{-- Imagem de fundo --}}
        @if($headerImageData)
            <img src="{{ $headerImageData }}" class="header-image" alt="Cabeçalho do Documento">
        @endif
        {{-- Logo sobreposto --}}
        @if($logoImageData)
            <img src="{{ $logoImageData }}" class="logo-overlay" alt="Logo">
        @endif
    </header>

    <footer>
        <div class="footer-content">
            <table>
                <tr>
                    <td>@if(get_icon_base64_data('phone-icon.png'))<img src="{{ get_icon_base64_data('phone-icon.png') }}" alt="Telefone">@endif 
                    (31) 98980-3313</td>
                </tr>
                <tr>
                    <td>@if(get_icon_base64_data('instagram-icon.png'))<img src="{{ get_icon_base64_data('instagram-icon.png') }}" alt="Instagram">@endif @fernandaloren_</td>
                </tr>
                <tr>
                    <td>@if(get_icon_base64_data('location-icon.png'))<img src="{{ get_icon_base64_data('location-icon.png') }}" alt="Endereço">@endif Rua Coronel Durães, nº 170, Sala 09, Bela Vista, Lagoa Santa/MG</td>
                </tr>
                <tr>
                    <td>@if(get_icon_base64_data('email-icon.png'))<img src="{{ get_icon_base64_data('email-icon.png') }}" alt="Email">@endif prev.fernandaloren@gmail.com</td>
                </tr>
            </table>
        </div>
    </footer>

    <main>
        <br>
        <h3>CONTRATO DE PRESTAÇÃO DE SERVIÇOS ADVOCATÍCIOS</h3>
        <br>
        <br>
        <p>{!! nl2br($paragrafo_completo) !!}</p>

        {{-- As cláusulas agora usam as variáveis do controller --}}
        <p><strong>Cláusula Primeira</strong> – {!! nl2br(e($clausula_1)) !!}</p>
        <p><strong>Cláusula Segunda</strong> – {!! nl2br(e($clausula_2)) !!}</p>
        <p><strong>Cláusula Terceira</strong> – {!! nl2br(e($clausula_3)) !!}</p>
        <p><strong>Parágrafo Primeiro</strong> – {!! nl2br(e($paragrafo_primeiro_clausula_3)) !!}</p>
        <p><strong>Cláusula Quarta</strong> – {!! nl2br(e($clausula_4)) !!}</p>
        <p><strong>Cláusula Quinta</strong> - {!! nl2br(e($clausula_5)) !!}</p>
        <p><strong>Cláusula Sexta</strong> - {!! nl2br(e($clausula_6)) !!}</p>
        <p><strong>Cláusula Sétima</strong> - {!! nl2br(e($clausula_7)) !!}</p>
        <p>{!! nl2br(e($texto_final)) !!}</p>

        <p style="text-align: center;">
            {{ $local_emissao }}, {{ $data_emissao }}.
        </p>
        
        <br>

        {{-- Assinaturas permanecem como no original --}}
        <div class="assinatura">
            <span class="assinatura-linha"></span>
            <strong>{{ strtoupper($outorgante->name ?: ($outorgante->business_name ?? 'ASSINATURA OUTORGANTE')) }}</strong>
        </div>
        <div class="assinatura">
            <span class="assinatura-linha"></span>
            Dr.ª <strong>FERNANDA LÓREN FERREIRA SANTOS</strong>
        </div>
        <div class="testemunhas-section">
            <p class="no-indent testemunhas-titulo"><strong>Testemunhas:</strong></p>
            <div class="testemunha">
                <p class="no-indent testemunha-linha">1. _________________________________________________________ R.G.:</p>
            </div>
            <div class="testemunha">
                <p class="no-indent testemunha-linha">2. _________________________________________________________ R.G.:</p>
            </div>
        </div>
    </main>
</body>
</html>
