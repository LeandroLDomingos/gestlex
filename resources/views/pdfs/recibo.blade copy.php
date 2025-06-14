<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Recibo</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; }
        .container { width: 90%; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-family: 'Times New Roman', Times, serif; font-size: 28px; margin: 0; font-weight: bold; }
        .header h2 { font-size: 24px; font-weight: normal; border: 1px solid #000; padding: 10px; display: inline-block; margin-top: 15px; }
        .content { font-size: 14pt; line-height: 1.6; text-align: justify; }
        .date-line { text-align: right; margin-top: 40px; margin-bottom: 60px; }
        .signature { text-align: center; }
        .signature-line { border-bottom: 1px solid #000; width: 400px; margin: 0 auto; }
        .signature p { margin-top: 5px; font-size: 12pt; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RECIBO</h1>
            <h2>{{ $valor_formatado }}</h2>
        </div>

        <div class="content">
            <p>
                Eu, <strong>Fernanda Lóren Ferreira Santos</strong>, inscrita na OAB/MG sob o nº 187.526,
                declaro para os devidos fins que recebi de
                <strong>{{ $nome_cliente }}</strong>
                a importância de <strong>{{ $valor_formatado }} ({{ $valor_por_extenso }})</strong>
                referente a {{ $referencia }}.
            </p>
        </div>

        <div class="date-line">
            <p>{{ $local_emissao }}, {{ $data_emissao }}.</p>
        </div>

        <div class="signature">
            <div class="signature-line"></div>
            <p>
                <strong>Fernanda Lóren Ferreira Santos</strong><br>
                OAB/MG 187.526<br>
                (31) 98980-3313
            </p>
        </div>
    </div>
</body>
</html>