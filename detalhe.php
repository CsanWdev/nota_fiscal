<?php
// Este é o arquivo detalhe.php, responsável por exibir a nota fiscal com o novo layout
// e permitir a geração de PDF.

if (!isset($_GET['id'])) {
    die("Nota Fiscal não encontrada.");
}

// Define o caminho para a logo
$logo_path = 'uploads/logo.png'; // Caminho relativo à este script

try {
    $caminhoBanco = __DIR__ . '/banco.db';
    $db = new PDO('sqlite:' . $caminhoBanco);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM notas WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $nota_bd = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para chaves de array

    if (!$nota_bd) {
        die("Nota não encontrada.");
    }

    // --- Adaptar os dados do banco para o formato esperado pelo layout ---
    // Seu banco atualmente não tem todos os campos que o layout espera.
    // Você precisará adicionar esses campos à tabela 'notas' ou
    // ajustar o 'salvar.php' para armazená-los.
    // Por enquanto, vamos simular ou usar valores padrão/vazios.

    // Deserializa o campo 'produtos' se ele for um JSON de itens
    $itens_decodificados = [];
    if (!empty($nota_bd['produtos'])) {
        // Tenta decodificar como JSON. Se falhar, trata como texto simples.
        $decoded = json_decode($nota_bd['produtos'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $itens_decodificados = $decoded;
        } else {
            // Se 'produtos' não for um JSON de itens, crie um item básico a partir dele
            $itens_decodificados[] = [
                'codigo' => 'N/A',
                'descricao' => $nota_bd['produtos'], // Usa o texto do campo produtos
                'ncm_sh' => 'N/A', 'cst' => 'N/A', 'cfop' => 'N/A', 'unidade' => 'UN',
                'quantidade' => 1.00,
                'valor_unitario' => $nota_bd['valor'],
                'valor_desconto' => 0.00,
                'valor_total' => $nota_bd['valor'],
                'base_calculo_icms' => 0.00,
                'valor_icms_item' => 0.00,
                'valor_ipi_item' => 0.00,
                'aliq_icms' => 0.00,
                'aliq_ipi' => 0.00,
            ];
        }
    }


    // Preparar o array $nota para o template com todos os campos necessários.
    // Você precisará mapear os campos do seu DB para o layout.
    // Campos que não existem no seu DB atual, mas estão no layout, terão valores padrão.
    $nota = [
        'id' => $nota_bd['id'],
        'numero_nf' => $nota_bd['numero'],
        'serie_nf' => '001', // Valor fixo ou buscar do DB se houver
        'data_recebimento' => date('d/m/Y'), // Data atual ou do DB
        'identificacao_assinatura' => '______________________________',
        'inscricao_estadual' => '420069180112', // Valor fixo ou buscar do DB (OJR)
        'razao_social_destinatario' => $nota_bd['cliente'], // Usando 'cliente' do DB
        'endereco_destinatario' => $nota_bd['endereco_cliente'],
        'municipio_destinatario' => 'SÃO SEBASTIÃO', // Exemplo. Adicionar ao DB
        'uf_destinatario' => 'SP', // Exemplo. Adicionar ao DB
        'telefone_destinatario' => '(12)3863-2345', // Exemplo. Adicionar ao DB
        'cep_destinatario' => $nota_bd['cep_cliente'],
        'cnpj_cpf_destinatario' => '11.960.533/0001-03', // Exemplo. Adicionar ao DB
        'data_emissao' => date('d/m/Y', strtotime($nota_bd['data_emissao'])),
        'hora_emissao' => date('H:i:s', strtotime($nota_bd['data_emissao'])),
        'chave_acesso' => '3518 1114 3564 2900 0120 5500 1000 0003 6918 9181 1378', // Gerar ou buscar do DB
        'protocolo_autorizacao' => '135189746335667 ' . date('d/m/Y H:i:s'), // Gerar ou buscar do DB
        'inscricao_estadual_subst_tributario' => '', // Adicionar ao DB
        'cnpj_cpf_remetente' => $nota_bd['cnpj'], // Usando 'cnpj' da empresa do DB
        'valor_icms' => 0.00, // Adicionar ao DB
        'base_calculo_icms' => 0.00, // Adicionar ao DB
        'valor_frete' => 0.00, // Adicionar ao DB
        'valor_seguro' => 0.00, // Adicionar ao DB
        'desconto' => 0.00, // Adicionar ao DB
        'outras_despesas' => 0.00, // Adicionar ao DB
        'valor_ipi' => 0.00, // Adicionar ao DB (se o item não tiver, o total será 0)
        'valor_total_produtos' => $nota_bd['valor'],
        'valor_total_nota' => $nota_bd['valor'],
        'transportador_razao_social' => 'NÃO INFORMADO', // Adicionar ao DB
        'placa_veiculo' => '', // Adicionar ao DB
        'uf_veiculo' => '', // Adicionar ao DB
        'cnh_cpf_transportador' => '', // Adicionar ao DB
        'informacoes_complementares' => 'Documento gerado para demonstração. Quaisquer informações adicionais da nota fiscal podem vir aqui.', // Adicionar ao DB
        'itens' => $itens_decodificados,
    ];

    // Se o campo 'produtos' do banco for apenas um texto, e não um JSON de itens,
    // garantimos que 'itens' tenha pelo menos um item para o layout não reclamar.
    if (empty($nota['itens']) && !empty($nota_bd['produtos'])) {
         $nota['itens'][] = [
             'codigo' => '0001',
             'descricao' => $nota_bd['produtos'],
             'ncm_sh' => 'N/A',
             'cst' => '00',
             'cfop' => '5102',
             'unidade' => 'UN',
             'quantidade' => 1.00,
             'valor_unitario' => $nota_bd['valor'],
             'valor_desconto' => 0.00,
             'valor_total' => $nota_bd['valor'],
             'base_calculo_icms' => 0.00,
             'valor_icms_item' => 0.00,
             'valor_ipi_item' => 0.00,
             'aliq_icms' => 0.00,
             'aliq_ipi' => 0.00,
         ];
    }


} catch (Exception $e) {
    die("Erro ao acessar o banco de dados: " . $e->getMessage());
}

// Verifica se é para retornar JSON (para o jsPDF em adm.php, por exemplo, embora agora o PDF seja gerado no detalhe.php)
if (isset($_GET['json']) && $_GET['json'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'sucesso', 'data' => $nota]);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Visualizar Nota Fiscal #<?php echo $nota['numero_nf']; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Estilos CSS do layout da nota fiscal, replicados aqui ou movidos para nota_fiscal_layout.php */
        /* Para simplificar a manutenção, idealmente esses estilos estariam apenas em nota_fiscal_layout.php ou em um CSS externo */
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt; /* Ajustado para ser mais próximo do tamanho da fonte da imagem */
            margin: 0;
            padding: 10mm; /* Margens para simular uma impressão */
            box-sizing: border-box;
            -webkit-print-color-adjust: exact; /* Para garantir que as cores de fundo sejam impressas */
            color-adjust: exact;
        }

        .invoice-container {
            width: 210mm; /* Largura padrão A4 */
            min-height: 297mm; /* Altura padrão A4 */
            margin: 0 auto;
            border: 1px solid black; /* Borda geral da nota */
            padding: 5mm; /* Espaçamento interno da borda geral */
            box-sizing: border-box;
            background-color: white; /* Garante fundo branco para impressão */
            position: relative; /* Para posicionamento absoluto de elementos internos */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: inherit; /* Garante que a fonte da tabela siga a do body */
        }

        td, th {
            padding: 2px 3px; /* Espaçamento interno das células */
            vertical-align: top;
        }

        /* Estilos para bordas e divisões como na imagem */
        .section-box {
            border: 1px solid black;
            margin-bottom: 5px; /* Espaçamento entre as caixas de seção */
            padding: 3px;
        }

        .no-border td, .no-border th {
            border: none;
        }

        .border-left { border-left: 1px solid black; }
        .border-right { border-right: 1px solid black; }
        .border-top { border-top: 1px solid black; }
        .border-bottom { border-bottom: 1px solid black; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-uppercase { text-transform: uppercase; }
        .font-smaller { font-size: 7pt; } /* Para texto menor como "NFe" */
        .font-larger { font-size: 10pt; } /* Para "DANFE" */

        /* Layout específico das seções */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
        }

        .nf-info {
            text-align: right;
            border: 1px solid black;
            padding: 2px 5px;
            width: 150px; /* Largura ajustada */
        }

        .main-header-sections {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .receipt-box {
            width: 35%; /* Ajustado para se encaixar ao lado do DANFE */
            min-height: 70px; /* Altura mínima para o box de recebimento */
            box-sizing: border-box;
        }

        .danfe-box {
            width: 63%; /* Ajustado para se encaixar ao lado do recebimento */
            box-sizing: border-box;
        }

        .danfe-header {
            background-color: lightgray; /* Fundo cinza para o DANFE */
            padding: 2px 0;
            margin-bottom: 3px;
        }

        /* Estilos ajustados para a logo */
        .logo-container {
            width: 100%; /* Ocupa toda a largura da célula onde está */
            height: auto; /* Altura automática baseada no conteúdo */
            min-height: 60px; /* Garante uma altura mínima para o container */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px; /* Espaçamento abaixo da logo */
            overflow: hidden; /* Garante que a imagem não transborde */
            /* background-color: #f0f0f0;  <-- Descomente para visualizar a área do container durante o teste */
            /* border: 1px solid red; <-- Descomente para visualizar a borda do container durante o teste */
        }
        .logo-container img {
            max-width: 100%; /* Garante que a imagem não seja maior que o container */
            max-height: 80px; /* Aumenta a altura máxima permitida para a imagem */
            object-fit: contain; /* Redimensiona a imagem para caber no container sem cortar ou distorcer */
            display: block; /* Remove espaço extra que alguns navegadores adicionam a imagens */
        }


        .barcode-placeholder {
            width: 98%;
            height: 30px;
            border: 1px solid black;
            margin: 5px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
            background-color: #eee;
        }

        .qr-code-placeholder {
            width: 98%;
            height: 50px;
            border: 1px solid black;
            margin: 5px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
            background-color: #eee;
        }

        /* Linhas divisórias internas em tabelas complexas */
        .inner-table-cell {
            border-bottom: 1px solid black;
        }
        .inner-table-cell:last-child {
            border-bottom: none;
        }

        /* Estilo para a tabela de itens */
        .items-table thead th {
            background-color: #e0e0e0; /* Um pouco de cor para o cabeçalho dos itens */
            border: 1px solid black;
            font-weight: bold;
            text-align: center;
            padding: 3px;
        }
        .items-table tbody td {
            border: 1px solid black;
            text-align: center; /* Centralizar os dados dos itens */
            padding: 3px;
        }

        .total-values-table td {
            border: none;
            padding: 1px 3px;
        }
        .total-values-table .label {
            width: 50%;
            text-align: left;
        }
        .total-values-table .value {
            width: 50%;
            text-align: right;
            border: 1px solid black;
        }

        .flexible-cell {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            padding: 0; /* Remover padding padrão para controlar internamente */
        }
        .flexible-cell > div {
            border-bottom: 1px solid black;
            padding: 2px 3px;
        }
        .flexible-cell > div:last-child {
            border-bottom: none;
        }

        /* Para campos que precisam de uma linha sublinhada para preencher */
        .fill-line {
            display: block;
            margin-top: 10px;
            border-bottom: 1px solid black;
            width: 100%;
        }

        /* Classes para layout de colunas */
        .col-2 { width: 16.66%; } /* Alterado para 6 colunas iguais para flexibilidade */
        .col-3 { width: 33.33%; }
        .col-4 { width: 25%; }
        .col-5 { width: 20%; }
        .col-6 { width: 16.66%; } /* Usado para divisões de 6 em 6 */
        .col-7 { width: 14.28%; }
        .col-8 { width: 12.5%; }
        .col-9 { width: 11.11%; }
        .col-10 { width: 10%; }
        .col-11 { width: 9.09%; }
        .col-12 { width: 8.33%; } /* Usado para 12 colunas iguais */

        /* Estilos para o botão de PDF */
        .pdf-button-container {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px; /* Adiciona margem inferior para não colar na nota */
        }
        .pdf-button {
            background-color: #28a745; /* Cor verde para o botão de sucesso */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none; /* Remover sublinhado se for um link */
            display: inline-block; /* Para alinhar ao centro */
        }
        .pdf-button:hover {
            background-color: #218838;
        }

        /* Oculta o botão ao imprimir */
        @media print {
            .pdf-button-container {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="pdf-button-container">
        <button class="pdf-button" onclick="generatePdf()">Gerar PDF</button>
        <a href="adm.php" class="pdf-button" style="background-color: #007bff;">Voltar ao Painel</a>
    </div>

    <div id="invoice-template" class="invoice-container">

        <div class="header-top">
            <div style="width: 75%;">
                Recebemos de DATAHOTEL SISTEMAS LTDA os produtos e/ou serviços constantes da Nota Fiscal Eletrônica indicada ao lado.
            </div>
            <div class="nf-info">
                <span class="font-smaller">NF-e</span><br>
                <span class="text-bold">N° <?php echo $nota['numero_nf']; ?></span><br>
                <span class="text-bold">Série <?php echo $nota['serie_nf']; ?></span>
            </div>
        </div>

        <div class="main-header-sections">
            <div class="section-box receipt-box">
                <span class="text-bold">DATA DO RECEBIMENTO</span><br><br><br>
                <div class="fill-line"></div>
                <span class="text-bold">IDENTIFICAÇÃO E ASSINATURA DO RECEBEDOR</span>
            </div>

            <div class="section-box danfe-box">
                <div class="danfe-header text-center">
                    <span class="font-larger text-bold">DANFE</span><br>
                    <span class="font-smaller">Documento Auxiliar da Nota Fiscal Eletrônica</span>
                </div>
                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid black; padding-bottom: 2px; margin-bottom: 2px;">
                    <span class="text-bold">0 - ENTRADA</span><br>
                    <span class="text-bold">1 - SAÍDA</span>
                </div>
                <div style="text-align: center; border-bottom: 1px solid black; padding-bottom: 2px; margin-bottom: 2px;">
                    <span class="text-bold">N° <?php echo $nota['numero_nf']; ?></span><br>
                    <span class="text-bold">SÉRIE <?php echo $nota['serie_nf']; ?></span><br>
                    <span class="text-bold">FOLHA 1/1</span>
                </div>
                <div class="barcode-placeholder">[CÓDIGO DE BARRAS AQUI]</div>
                <div style="font-size: 7pt; text-align: center;">
                    <span class="text-bold">CHAVE DE ACESSO:</span><br>
                    <?php echo $nota['chave_acesso']; ?>
                </div>
                <div class="qr-code-placeholder">[QR CODE AQUI]</div>
            </div>
        </div>

        <div class="section-box" style="margin-bottom: 5px;">
            <table class="no-border">
                <tr>
                    <td style="width: 20%; vertical-align: top; border-right: 1px solid black; padding-right: 5px;">
                        <div class="logo-container">
                            <?php if (file_exists($logo_path)): ?>
                                <img src="<?php echo $logo_path; ?>" alt="Logo da Empresa">
                            <?php else: ?>
                                OJR <?php endif; ?>
                        </div>
                        <span class="text-bold">Consultoria em T.I.</span><br>
                        <span class="font-smaller">SITES | SISTEMAS | SUPORTE</span>
                    </td>
                    <td style="width: 80%; vertical-align: top; padding-left: 5px;">
                        <table class="no-border">
                            <tr>
                                <td style="width: 70%;" class="border-bottom">
                                    <span class="text-bold">NATUREZA DA OPERAÇÃO:</span> VENDA DE PRODUTOS
                                </td>
                                <td style="width: 30%;" class="border-bottom">
                                    <span class="text-bold">PROTOCOLO DE AUTORIZAÇÃO DE USO:</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">INSCRIÇÃO ESTADUAL:</span> <?php echo $nota['inscricao_estadual']; ?>
                                </td>
                                <td class="border-bottom">
                                    <?php echo $nota['protocolo_autorizacao']; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">DESTINATÁRIO / REMETENTE</span>
                                </td>
                            <td class="border-bottom">
                                <span class="text-bold">CNPJ/CPF:</span> <?php echo $nota['cnpj_cpf_remetente']; ?>
                            </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">NOME / RAZÃO SOCIAL:</span> <?php echo $nota['razao_social_destinatario']; ?>
                                </td>
                                <td class="border-bottom">
                                    <span class="text-bold">DATA DE EMISSÃO:</span> <?php echo $nota['data_emissao']; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">ENDEREÇO:</span> <?php echo $nota['endereco_destinatario']; ?>
                                </td>
                                <td class="border-bottom">
                                    <span class="text-bold">HORA DE EMISSÃO:</span> <?php echo $nota['hora_emissao']; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">MUNICÍPIO:</span> <?php echo $nota['municipio_destinatario']; ?>
                                </td>
                                <td class="border-bottom">
                                    <span class="text-bold">UF:</span> <?php echo $nota['uf_destinatario']; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="border-bottom">
                                    <span class="text-bold">TELEFONE:</span> <?php echo $nota['telefone_destinatario']; ?>
                                </td>
                                <td class="border-bottom">
                                    <span class="text-bold">CEP:</span> <?php echo $nota['cep_destinatario']; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <span class="text-bold">CÁLCULO DO IMPOSTO</span>
            <table class="no-border">
                <tr>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">BASE DE CÁLCULO DO ICMS</span></div>
                        <div><?php echo number_format($nota['base_calculo_icms'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">VALOR DO ICMS</span></div>
                        <div><?php echo number_format($nota['valor_icms'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">VALOR DO FRETE</span></div>
                        <div><?php echo number_format($nota['valor_frete'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">VALOR DO SEGURO</span></div>
                        <div><?php echo number_format($nota['valor_seguro'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">DESCONTO</span></div>
                        <div><?php echo number_format($nota['desconto'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="col-2 flexible-cell">
                        <div><span class="text-bold">VALOR TOTAL DOS PRODUTOS</span></div>
                        <div><?php echo number_format($nota['valor_total_produtos'], 2, ',', '.'); ?></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" class="flexible-cell">
                        <div><span class="text-bold">OUTRAS DESPESAS ACESSÓRIAS</span></div>
                        <div><?php echo number_format($nota['outras_despesas'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="flexible-cell">
                        <div><span class="text-bold">VALOR DO IPI</span></div>
                        <div><?php echo number_format($nota['valor_ipi'], 2, ',', '.'); ?></div>
                    </td>
                    <td class="flexible-cell">
                        <div><span class="text-bold">VALOR TOTAL DA NOTA</span></div>
                        <div><?php echo number_format($nota['valor_total_nota'], 2, ',', '.'); ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <span class="text-bold">TRANSPORTADOR / VOLUMES TRANSPORTADOS</span>
            <table class="no-border">
                <tr>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">NOME / RAZÃO SOCIAL</span></div>
                        <div><?php echo $nota['transportador_razao_social']; ?></div>
                    </td>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">FRETE POR CONTA</span></div>
                        <div></div> </td>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">CÓDIGO ANTT</span></div>
                        <div></div>
                    </td>
                </tr>
                <tr>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">PLACA DO VEÍCULO</span></div>
                        <div><?php echo $nota['placa_veiculo']; ?></div>
                    </td>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">UF</span></div>
                        <div><?php echo $nota['uf_veiculo']; ?></div>
                    </td>
                    <td class="col-4 flexible-cell">
                        <div><span class="text-bold">CNPJ / CPF</span></div>
                        <div><?php echo $nota['cnh_cpf_transportador']; ?></div>
                    </td>
                </tr>
            </table>
            <div class="border-top" style="margin-top: 5px; padding-top: 5px;">
                <table class="no-border">
                    <tr>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">QUANTIDADE</span></div>
                            <div><?php echo isset($nota['itens'][0]['quantidade']) ? $nota['itens'][0]['quantidade'] : ''; ?></div> </td>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">ESPÉCIE</span></div>
                            <div>CAIXA</div>
                        </td>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">MARCA</span></div>
                            <div>ELGIN</div>
                        </td>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">NUMERAÇÃO</span></div>
                            <div></div>
                        </td>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">PESO BRUTO</span></div>
                            <div></div>
                        </td>
                        <td class="col-6 flexible-cell">
                            <div><span class="text-bold">PESO LÍQUIDO</span></div>
                            <div></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="section-box">
            <span class="text-bold">DADOS DOS PRODUTOS / SERVIÇOS</span>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-8">CÓDIGO PRODUTO</th>
                        <th class="col-3">DESCRIÇÃO DO PRODUTO / SERVIÇO</th>
                        <th class="col-10">NCM/SH</th>
                        <th class="col-11">CST</th>
                        <th class="col-12">CFOP</th>
                        <th class="col-12">UNID.</th>
                        <th class="col-12">QUANT.</th>
                        <th class="col-12">VALOR UNIT.</th>
                        <th class="col-12">VALOR DESCONTO</th>
                        <th class="col-12">VALOR TOTAL</th>
                        <th class="col-12">BASE CÁLC. ICMS</th>
                        <th class="col-12">VALOR ICMS</th>
                        <th class="col-12">VALOR IPI</th>
                        <th class="col-12">ALÍQ. ICMS</th>
                        <th class="col-12">ALÍQ. IPI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($nota['itens'])): ?>
                        <?php foreach ($nota['itens'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($item['descricao']); ?></td>
                            <td><?php echo htmlspecialchars($item['ncm_sh']); ?></td>
                            <td><?php echo htmlspecialchars($item['cst']); ?></td>
                            <td><?php echo htmlspecialchars($item['cfop']); ?></td>
                            <td><?php echo htmlspecialchars($item['unidade']); ?></td>
                            <td><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['valor_desconto'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['valor_total'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['base_calculo_icms'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['valor_icms_item'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['valor_ipi_item'], 2, ',', '.'); ?></td>
                            <td><?php echo number_format($item['aliq_icms'], 2, ',', '.'); ?>%</td>
                            <td><?php echo number_format($item['aliq_ipi'], 2, ',', '.'); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="15" class="text-center">Nenhum item encontrado para esta nota fiscal.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-box">
            <span class="text-bold">DADOS ADICIONAIS</span>
            <table class="no-border">
                <tr>
                    <td class="col-5 flexible-cell">
                        <div><span class="text-bold">INFORMAÇÕES COMPLEMENTARES</span></div>
                        <div><?php echo nl2br(htmlspecialchars($nota['informacoes_complementares'])); ?></div>
                    </td>
                    <td class="col-5 flexible-cell">
                        <div><span class="text-bold">DADOS DO IMPOSTO:</span></div>
                        <div></div>
                    </td>
                </tr>
            </table>
        </div>

    </div>
    <script>
        // Função para gerar o PDF
        function generatePdf() {
            const element = document.getElementById('invoice-template');
            const originalBodyMargin = document.body.style.margin;
            const originalBodyPadding = document.body.style.padding;

            // Temporariamente remove margem/padding do body para html2canvas capturar perfeitamente
            document.body.style.margin = '0';
            document.body.style.padding = '0';

            const opt = {
                margin: [10, 10, 10, 10], // Margens: [top, left, bottom, right] em mm
                filename: 'nota_fiscal_<?php echo $nota['numero_nf']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2, // Aumenta a escala para melhor qualidade de imagem
                    useCORS: true, // Se houver imagens de outra origem
                    scrollY: 0, // Inicia a captura do topo da página
                    scrollX: 0,
                    windowWidth: document.documentElement.offsetWidth, // Captura toda a largura visível
                    windowHeight: document.documentElement.offsetHeight // Captura toda a altura visível
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4', // Formato A4
                    orientation: 'portrait' // Orientação retrato
                }
            };

            // Gera o PDF a partir do elemento HTML
            html2canvas(element, opt.html2canvas).then(function(canvas) {
                const imgData = canvas.toDataURL('image/jpeg', opt.image.quality);
                const pdf = new jspdf.jsPDF(opt.jsPDF);

                const imgProps = pdf.getImageProperties(imgData);
                const pdfWidth = pdf.internal.pageSize.getWidth() - opt.margin[1] - opt.margin[3]; // Largura útil
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width; // Altura proporcional

                // Adiciona a imagem ao PDF com as margens
                pdf.addImage(imgData, 'JPEG', opt.margin[1], opt.margin[0], pdfWidth, pdfHeight);
                pdf.save(opt.filename);

                // Restaura as margens e padding originais do body
                document.body.style.margin = originalBodyMargin;
                document.body.style.padding = originalBodyPadding;
            });
        }
    </script>
</body>
</html>