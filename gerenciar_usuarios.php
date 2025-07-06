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

// --- Lógica para Deletar Usuário ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idParaDeletar = (int)$_GET['id'];

    // Impede o fundador de deletar a si mesmo
    if ($idParaDeletar === $_SESSION['user_id']) {
        $mensagem = "Você não pode deletar sua própria conta!";
        $status_mensagem = 'error';
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$idParaDeletar])) {
                $mensagem = "Usuário deletado com sucesso!";
                $status_mensagem = 'success';
            } else {
                $mensagem = "Erro ao deletar usuário.";
                $status_mensagem = 'error';
            }
        } catch (PDOException $e) {
            $mensagem = "Erro no banco de dados ao deletar: " . $e->getMessage();
            $status_mensagem = 'error';
        }
    }
}

// --- Lógica para Redefinir Senha (com formulário) ---
if (isset($_POST['action']) && $_POST['action'] === 'reset_password' && isset($_POST['user_id_reset']) && isset($_POST['new_password'])) {
    $userIdReset = (int)$_POST['user_id_reset'];
    $newPassword = $_POST['new_password'];

    // Impede o fundador de redefinir a própria senha por aqui (deveria usar a interface de edição)
    if ($userIdReset === $_SESSION['user_id']) {
        $mensagem = "Você não pode redefinir sua própria senha por aqui. Use a opção de edição de perfil.";
        $status_mensagem = 'error';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userIdReset])) {
                // Opcional: Limpar o segredo 2FA do usuário se a senha for redefinida
                $db->prepare("UPDATE users SET google_auth_secret = NULL WHERE id = ?")
                   ->execute([$userIdReset]);
                $mensagem = "Senha e 2FA do usuário redefinidos com sucesso!";
                $status_mensagem = 'success';
            } else {
                $mensagem = "Erro ao redefinir senha do usuário.";
                $status_mensagem = 'error';
            }
        } catch (PDOException $e) {
            $mensagem = "Erro no banco de dados ao redefinir senha: " . $e->getMessage();
            $status_mensagem = 'error';
        }
    }
}


// Busca todos os usuários
$stmtUsers = $db->query("SELECT id, username, role FROM users");
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários - Painel Administrativo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; color: #555; }
        .botao { display: inline-block; padding: 8px 12px; margin-right: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; text-align: center; }
        .botao-voltar { background-color: #6c757d; color: white; }
        .botao-voltar:hover { background-color: #5a6268; }
        .botao-deletar { background-color: #dc3545; color: white; }
        .botao-deletar:hover { background-color: #c82333; }
        .botao-resetar { background-color: #ffc107; color: #333; }
        .botao-resetar:hover { background-color: #e0a800; }
        .botao-novo-usuario { background-color: #28a745; color: white; margin-bottom: 20px; }
        .botao-novo-usuario:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Estilos para o modal de redefinição de senha */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-content h2 {
            margin-top: 0;
            text-align: center;
        }
        .modal-content label {
            display: block;
            margin-bottom: 8px;
            margin-top: 15px;
        }
        .modal-content input[type="password"] {
            width: calc(100% - 22px); /* Ajuste para padding e border */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-content button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        .modal-content button:hover {
            background-color: #0056b3;
        }
    </style>
    <script>
        function confirmarDelete(id, username) {
            if (confirm("Tem certeza que deseja deletar o usuário '" + username + "' (ID: " + id + ")? Esta ação não pode ser desfeita.")) {
                window.location.href = 'gerenciar_usuarios.php?action=delete&id=' + id;
            }
        }

        // Função para abrir o modal de redefinição de senha
        function abrirModalRedefinirSenha(userId, username) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUsername').innerText = username;
            document.getElementById('resetPasswordModal').style.display = 'flex'; // Use flex para centralizar
        }

        // Função para fechar o modal
        function fecharModalRedefinirSenha() {
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.getElementById('newPassword').value = ''; // Limpa o campo de senha
            document.getElementById('confirmNewPassword').value = ''; // Limpa o campo de confirmação
            document.getElementById('resetPasswordError').innerText = ''; // Limpa mensagens de erro
        }

        // Validação de senha no envio do modal
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            form.addEventListener('submit', function(event) {
                const newPassword = document.getElementById('newPassword').value;
                const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                const errorDiv = document.getElementById('resetPasswordError');

                if (newPassword === '' || confirmNewPassword === '') {
                    errorDiv.innerText = 'Por favor, preencha ambos os campos de senha.';
                    errorDiv.style.display = 'block';
                    event.preventDefault(); // Impede o envio do formulário
                } else if (newPassword !== confirmNewPassword) {
                    errorDiv.innerText = 'As senhas não coincidem.';
                    errorDiv.style.display = 'block';
                    event.preventDefault(); // Impede o envio do formulário
                } else if (newPassword.length < 6) { // Exemplo de validação de força mínima
                    errorDiv.innerText = 'A nova senha deve ter pelo menos 6 caracteres.';
                    errorDiv.style.display = 'block';
                    event.preventDefault();
                } else {
                    errorDiv.innerText = ''; // Limpa qualquer erro anterior
                    errorDiv.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Gerenciar Usuários</h1>

        <?php if ($mensagem): ?>
            <p class="message <?= $status_mensagem ?>"><?= htmlspecialchars($mensagem) ?></p>
        <?php endif; ?>

        <a href="adm.php" class="botao botao-voltar">← Voltar ao Painel</a>
        <a href="adicionar_usuario.php" class="botao botao-novo-usuario">Adicionar Novo Usuário</a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Função</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Nenhum usuário encontrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td>
                                <button class="botao botao-resetar" onclick="abrirModalRedefinirSenha(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">Redefinir Senha</button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): // Não permite deletar a si mesmo ?>
                                    <button class="botao botao-deletar" onclick="confirmarDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">Deletar</button>
                                <?php else: ?>
                                    <span style="color: #6c757d;">(Você)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="fecharModalRedefinirSenha()">&times;</span>
            <h2>Redefinir Senha para <span id="resetUsername"></span></h2>
            <form id="resetPasswordForm" method="POST" action="gerenciar_usuarios.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id_reset" id="resetUserId">

                <label for="newPassword">Nova Senha:</label>
                <input type="password" id="newPassword" name="new_password" required autocomplete="new-password">

                <label for="confirmNewPassword">Confirmar Nova Senha:</label>
                <input type="password" id="confirmNewPassword" name="confirm_new_password" required autocomplete="new-password">

                <p id="resetPasswordError" class="message error" style="display: none;"></p>

                <button type="submit">Redefinir Senha</button>
            </form>
        </div>
    </div>
</body>
</html>