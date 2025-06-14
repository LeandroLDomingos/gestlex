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
    <title>Pedido Médico</title>
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
    <h3><strong>RECIBO</strong></h3>
    <h2 style="text-align: right;">{{ $valor_formatado }}</h2>

        <p>
            Eu, <strong>Fernanda Lóren Ferreira Santos</strong>, inscrita na OAB/MG sob o nº 187.526,
            declaro para os devidos fins que recebi de
            <strong>{{ $nome_cliente }}</strong>
            a importância de <strong>{{ $valor_formatado }} ({{ $valor_por_extenso }})</strong>
            referente a {{ $referencia }}.
        </p>

        <div class="assinatura">
            <p>{{ $local_emissao }}, {{ $data_emissao }}.</p> 
            <br><br>
            <p>_________________________________________</p>
            <p>
                <strong>FERNANDA LÓREN FERREIRA SANTOS</strong><br>
                <strong>OAB/MG 187.526</strong>
            </p> 
        </div>
    </main>
</body>
</html>
