<?php
session_start();

// --- INÍCIO DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// --- FIM DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---

// Verifica se está logado (reaproveitar do adm.php)
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: adm.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("ID da nota não informado.");
}

try {
    // Define o caminho do banco de dados de forma relativa ao diretório do script atual
    $caminhoBanco = __DIR__ . '/banco.db';

    // Conexão com o banco de dados
    $db = new PDO('sqlite:' . $caminhoBanco);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Se enviou o formulário para salvar alterações
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("UPDATE notas SET
            numero = ?, empresa = ?, cnpj = ?, endereco_empresa = ?, telefone_empresa = ?,
            cliente = ?, endereco_cliente = ?, cep_cliente = ?, produtos = ?, valor = ?,
            informacoes_adicionais = ?, condicoes_pagamento = ? -- Novos campos
            WHERE id = ?");

        // Converte o valor para float antes de salvar
        $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor'])); // Remove ponto de milhar e troca vírgula por ponto

        // Converte os itens para JSON antes de salvar
        $itens_json = json_encode([]); // Inicializa como array vazio
        if (isset($_POST['item_descricao']) && is_array($_POST['item_descricao'])) {
            $itens_para_salvar = [];
            foreach ($_POST['item_descricao'] as $key => $descricao) {
                if (!empty(trim($descricao))) {
                    $quantidade = (int)($_POST['item_quantidade'][$key] ?? 1);
                    $valorUnitario = floatval(str_replace(',', '.', str_replace('.', '', $_POST['item_valorUnitario'][$key] ?? 0)));
                    $itens_para_salvar[] = [
                        'descricao' => trim($descricao),
                        'quantidade' => $quantidade,
                        'valorUnitario' => $valorUnitario,
                        'valorTotal' => $quantidade * $valorUnitario
                    ];
                }
            }
            $itens_json = json_encode($itens_para_salvar);
        }

        $stmt->execute([
            $_POST['numero'], // Adicionado o campo numero aqui
            $_POST['empresa'],
            $_POST['cnpj'],
            $_POST['endereco_empresa'],
            $_POST['telefone_empresa'],
            $_POST['cliente'],
            $_POST['endereco_cliente'],
            $_POST['cep_cliente'],
            $itens_json, // Salva os itens como JSON
            $valor,
            $_POST['informacoes_adicionais'], // Novo campo
            $_POST['condicoes_pagamento'],   // Novo campo
            $_GET['id']
        ]);

        $_SESSION['mensagem_sucesso'] = "Nota fiscal atualizada com sucesso!";
        header('Location: adm.php'); // Redireciona de volta para o painel
        exit();
    }

    // Carrega os dados da nota fiscal para preencher o formulário
    $stmt = $db->prepare("SELECT * FROM notas WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nota) {
        die("Nota não encontrada.");
    }

    // Decodifica o campo 'produtos' se for JSON para exibir no formulário
    $itens_existentes = [];
    if (!empty($nota['produtos'])) {
        $decoded_items = json_decode($nota['produtos'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_items)) {
            $itens_existentes = $decoded_items;
        }
    }

} catch (Exception $e) {
    die("Erro ao acessar o banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Nota Fiscal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
        h1 { text-align: center; color: #2c3e50; }
        label { display: block; margin-top: 12px; font-weight: bold; color: #34495e; }
        input[type="text"], textarea { width: calc(100% - 22px); padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 5px; }
        button { margin-top: 20px; padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #218838; }
        .botao-voltar { display: inline-block; padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        .botao-voltar:hover { background-color: #5a6268; }
        .item-row { display: flex; align-items: center; margin-bottom: 10px; }
        .item-row input, .item-row .remove-item-btn { margin-right: 10px; }
        .item-row input[name*="descricao"] { flex-grow: 3; }
        .item-row input[name*="quantidade"], .item-row input[name*="valorUnitario"] { flex-grow: 1; max-width: 150px; }
        .add-item-btn { background-color: #007bff; }
        .add-item-btn:hover { background-color: #0056b3; }
        .remove-item-btn { background-color: #dc3545; color: white; border: none; padding: 8px 12px; cursor: pointer; border-radius: 4px; }
        .remove-item-btn:hover { background-color: #c82333; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Formatar valor ao carregar
            const valorInput = document.querySelector('input[name="valor"]');
            if (valorInput) {
                valorInput.value = formatarMoeda(valorInput.value);
            }

            // Adicionar formatação ao digitar
            valorInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
                value = (value / 100).toFixed(2) + ''; // Divide por 100 e formata para 2 casas decimais
                value = value.replace(".", ","); // Troca ponto por vírgula
                value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); // Adiciona pontos de milhar
                e.target.value = value;
            });

            // Adicionar novo item
            document.getElementById('add-item-btn').addEventListener('click', function() {
                const container = document.getElementById('itens-container');
                const newItemRow = document.createElement('div');
                newItemRow.className = 'item-row';
                newItemRow.innerHTML = `
                    <input type="text" name="item_descricao[]" placeholder="Descrição do Item" required>
                    <input type="text" name="item_quantidade[]" placeholder="Qtd" value="1" required oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    <input type="text" name="item_valorUnitario[]" placeholder="Valor Unitário (R$)" required>
                    <button type="button" class="remove-item-btn">Remover</button>
                `;
                container.appendChild(newItemRow);

                // Adicionar evento para remover novo item
                newItemRow.querySelector('.remove-item-btn').addEventListener('click', function() {
                    newItemRow.remove();
                });

                // Adicionar formatação de moeda ao novo campo de valor unitário
                const newValorUnitarioInput = newItemRow.querySelector('input[name*="valorUnitario"]');
                newValorUnitarioInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
                    value = (value / 100).toFixed(2) + ''; // Divide por 100 e formata para 2 casas decimais
                    value = value.replace(".", ","); // Troca ponto por vírgula
                    value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); // Adiciona pontos de milhar
                    e.target.value = value;
                });
                newValorUnitarioInput.value = formatarMoeda(newValorUnitarioInput.value); // Formata valor inicial do novo campo
            });

            // Adicionar evento para remover itens existentes
            document.querySelectorAll('.remove-item-btn').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.item-row').remove();
                });
            });

            // Aplicar formatação inicial para itens existentes
            document.querySelectorAll('input[name*="valorUnitario"]').forEach(input => {
                input.value = formatarMoeda(input.value);
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = (value / 100).toFixed(2) + '';
                    value = value.replace(".", ",");
                    value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                    e.target.value = value;
                });
            });

            function formatarMoeda(valor) {
                // Remove caracteres não numéricos e converte para float
                let num = parseFloat(valor.replace(/\./g, '').replace(',', '.'));
                if (isNaN(num)) num = 0;
                // Formata para moeda brasileira
                return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Editar Nota Fiscal</h1>
        <form method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($nota['id']) ?>">

            <label>Número da Nota</label>
            <input type="text" name="numero" value="<?= htmlspecialchars($nota['numero']) ?>" required>

            <label>Empresa</label>
            <input type="text" name="empresa" value="<?= htmlspecialchars($nota['empresa']) ?>" required>

            <label>CNPJ</label>
            <input type="text" name="cnpj" value="<?= htmlspecialchars($nota['cnpj']) ?>" required>

            <label>Endereço da Empresa</label>
            <input type="text" name="endereco_empresa" value="<