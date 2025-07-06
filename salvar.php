<?php
// --- INÍCIO DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// --- FIM DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---

// Cabeçalhos CORS para permitir requisições do seu domínio Netlify
// Se você está movendo tudo para a Umbler, pode ser que estes cabeçalhos não sejam mais necessários
// ou precisem ser ajustados para o domínio da sua Umbler se a "gerar_nota.php" for uma SPA em outro domínio.
// Se gerar_nota.php e salvar.php estiverem no mesmo domínio na Umbler, você pode REMOVER estas 3 linhas.
header("Access-Control-Allow-Origin: *"); // Altere para o domínio específico da sua aplicação front-end se houver
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

try {
    // Define o caminho do banco de dados de forma relativa ao diretório do script atual
    $caminhoBanco = __DIR__ . '/banco.db';

    // Criar conexão com o banco
    $db = new PDO('sqlite:' . $caminhoBanco);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cria a tabela se ainda não existir (garantia, mas deve ser criada pelo adm.php)
    $db->exec("CREATE TABLE IF NOT EXISTS notas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        numero INTEGER,
        empresa TEXT,
        cnpj TEXT,
        endereco_empresa TEXT,
        telefone_empresa TEXT,
        cliente TEXT,
        endereco_cliente TEXT,
        cep_cliente TEXT,
        produtos TEXT,
        valor REAL,
        data_emissao TEXT,
        informacoes_adicionais TEXT, -- Novo campo
        condicoes_pagamento TEXT    -- Novo campo
    )");

    // Pega os dados enviados em JSON
    $dados = json_decode(file_get_contents('php://input'), true);

    if ($dados) {
        // Converte o valor para float antes de salvar
        $valor = str_replace(',', '.', str_replace('.', '', $dados['valor'])); // Remove ponto de milhar e troca vírgula por ponto

        // Converte os itens para JSON antes de salvar
        $itens_json = json_encode($dados['itens'] ?? []);

        // Prepara o INSERT
        $stmt = $db->prepare("INSERT INTO notas (
            numero, empresa, cnpj, endereco_empresa, telefone_empresa,
            cliente, endereco_cliente, cep_cliente, produtos, valor, data_emissao,
            informacoes_adicionais, condicoes_pagamento -- Novos campos
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Executa o INSERT
        $stmt->execute([
            $dados['numero'],
            $dados['empresa'],
            $dados['cnpj'],
            $dados['enderecoEmpresa'],
            $dados['telefoneEmpresa'],
            $dados['cliente'],
            $dados['enderecoCliente'],
            $dados['cepCliente'],
            $itens_json, // Salva os itens como JSON
            $valor,
            date('Y-m-d H:i:s'), // Data de emissão automática no momento do salvamento
            $dados['informacoesAdicionais'] ?? null, // Novo campo
            $dados['condicoesPagamento'] ?? null    // Novo campo
        ]);

        // Retorna sucesso
        echo json_encode(['status' => 'sucesso', 'id' => $db->lastInsertId()]);
    } else {
        // Dados inválidos
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados inválidos recebidos.']);
    }
} catch (Exception $e) {
    // Retorna erro
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar nota: ' . $e->getMessage()]);
    // Registra o erro no log do servidor
    error_log("Erro em salvar.php: " . $e->getMessage());
}
?>