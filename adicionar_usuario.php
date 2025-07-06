<?php
session_start();

// --- INÍCIO DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// --- FIM DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---

// Conexão com o banco de dados
try {
    $caminhoBanco = __DIR__ . '/banco.db';
    $db = new PDO('sqlite:' . $caminhoBanco);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erro ao acessar o banco de dados: " . $e->getMessage());
}

// Verifica se o usuário está logado e se tem a função 'founder'
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || $_SESSION['role'] !== 'founder') {
    header('Location: adm.php'); // Redireciona para o login se não for fundador
    exit();
}

$mensagem = '';
$status_mensagem = ''; // 'success' ou 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user'; // Padrão para 'user' se não especificado

    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $mensagem = "Por favor, preencha todos os campos.";
        $status_mensagem = 'error';
    } elseif ($password !== $confirmPassword) {
        $mensagem = "As senhas não coincidem.";
        $status_mensagem = 'error';
    } elseif (strlen($password) < 6) { // Exemplo: senha mínima de 6 caracteres
        $mensagem = "A senha deve ter pelo menos 6 caracteres.";
        $status_mensagem = 'error';
    } else {
        // Verifica se o username já existe
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetchColumn() > 0) {
            $mensagem = "O nome de usuário '$username' já existe. Por favor, escolha outro.";
            $status_mensagem = 'error';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $googleAuthSecret = null; // O novo usuário configurará o 2FA na primeira vez que for solicitado

            try {
                $stmtInsert = $db->prepare("INSERT INTO users (username, password, google_auth_secret, role) VALUES (?, ?, ?, ?)");
                if ($stmtInsert->execute([$username, $hashedPassword, $googleAuthSecret, $role])) {
                    $mensagem = "Usuário '$username' adicionado com sucesso!";
                    $status_mensagem = 'success';
                    // Limpar campos do formulário após sucesso, se desejar
                    $_POST = [];
                } else {
                    $mensagem = "Erro ao adicionar usuário. Tente novamente.";
                    $status_mensagem = 'error';
                }
            } catch (PDOException $e) {
                $mensagem = "Erro no banco de dados: " . $e->getMessage();
                $status_mensagem = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Novo Usuário - Painel Administrativo</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 400px; }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="text"], input[type="password"], select { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .botao-voltar { display: inline-block; padding: 10px 15px; margin-top: 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; text-align: center; width: calc(100% - 30px); }
        .botao-voltar:hover { background-color: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Adicionar Novo Usuário</h1>

        <?php if ($mensagem): ?>
            <p class="message <?= $status_mensagem ?>"><?= htmlspecialchars($mensagem) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Nome de Usuário:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="off">

            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">

            <label for="confirm_password">Confirmar Senha:</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">

            <label for="role">Função:</label>
            <select id="role" name="role">
                <option value="user" <?= (($_POST['role'] ?? '') == 'user') ? 'selected' : '' ?>>Usuário Comum</option>
                <option value="admin" <?= (($_POST['role'] ?? '') == 'admin') ? 'selected' : '' ?>>Administrador</option>
                </select>
            <button type="submit">Adicionar Usuário</button>
        </form>

        <a href="gerenciar_usuarios.php" class="botao-voltar">← Voltar ao Gerenciamento de Usuários</a>
    </div>
</body>
</html>