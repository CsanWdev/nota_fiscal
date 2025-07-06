<?php
session_start();

// Define o cabeçalho para indicar que a resposta será JSON
header('Content-Type: application/json');

// Verifica se o usuário está logado e se o 2FA foi verificado.
// Esta verificação é crucial para proteger este endpoint, pois ele retorna dados do sistema.
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Acesso não autorizado. Por favor, faça login.']);
    exit();
}

try {
    // Define o caminho para o banco de dados
    $caminhoBanco = __DIR__ . '/banco.db';
    // Conecta ao banco de dados SQLite
    $db = new PDO('sqlite:' . $caminhoBanco);
    // Configura o PDO para lançar exceções em caso de erros
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta o número mais alto na coluna 'numero' da tabela 'notas'
    $stmt = $db->query("SELECT MAX(numero) as max_numero FROM notas");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inicializa o próximo número como 1
    $next_number = 1;

    // Se houver resultados e 'max_numero' não for nulo (ou seja, já existem notas),
    // o próximo número será o maior número existente + 1.
    if ($result && $result['max_numero'] !== null) {
        $next_number = $result['max_numero'] + 1;
    }

    // Retorna o próximo número como um objeto JSON de sucesso
    echo json_encode(['status' => 'sucesso', 'next_number' => $next_number]);

} catch (Exception $e) {
    // Em caso de erro, retorna uma mensagem de erro em formato JSON
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao obter próximo número da nota: ' . $e->getMessage()]);
}
?>