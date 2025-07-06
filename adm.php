<?php
session_start();

// --- INÍCIO DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---
// Desativar exibição de erros para usuários em ambiente de produção
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On'); // Ativar o registro de erros em arquivo
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Registra a maioria dos erros importantes, mas esconde avisos/deprecated
// --- FIM DAS CONFIGURAÇÕES DE ERRO PARA PRODUÇÃO ---

// Inclui manualmente a classe GoogleAuthenticator. Certifique-se de que o caminho está correto.
require_once 'lib/PHPGangsta/GoogleAuthenticator.php';

// Senha fixa (OBS: Em um sistema real, a senha não deve ser fixa no código)
$senhaCorreta = 'admin00'; // Esta senha é para o 'founder' inicial

// Sua chave secreta do Google Authenticator gerada para o admin00 (founder).
// Cada usuário terá a sua no banco de dados.
$googleAuthSecret = 'PDGZ6YNCFWUFGFJK'; // Use o segredo do 'founder'

$googleAuthEnabled = true; // DEVE SER TRUE PARA HABILITAR O 2FA

// --- Início da Lógica de Logout ---
if (isset($_GET['logout'])) {
    // Apaga o cookie 'remember_me_token'
    if (isset($_COOKIE['remember_me_token'])) {
        setcookie('remember_me_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    session_destroy();
    session_unset(); // Limpa todas as variáveis de sessão
    header("Location: " . $_SERVER['PHP_SELF']); // Redireciona para a mesma página, que agora mostrará o login
    exit();
}
// --- Fim da Lógica de Logout ---

// --- Início da Lógica de Autenticação 2FA ---
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true && $googleAuthEnabled && $_SESSION['2fa_verified'] !== true) {
    $erro2FA = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_2fa'])) {
        $ga = new PHPGangsta_GoogleAuthenticator();
        $code = $_POST['codigo_2fa'];

        // Use o segredo 2FA do usuário logado (armazenado na sessão)
        // Se o founder não tem um segredo 2FA no banco, ele usará a chave fixa ($googleAuthSecret)
        $user_google_auth_secret = $_SESSION['google_auth_secret'] ?? $googleAuthSecret;

        if ($ga->verifyCode($user_google_auth_secret, $code, 2)) { // 2 para desvio de tempo
            $_SESSION['2fa_verified'] = true;

            // Se a caixa "Lembrar deste dispositivo" foi marcada, gerar e salvar token
            if (isset($_POST['remember_device']) && $_POST['remember_device'] == 'on') {
                $selector = base64_encode(random_bytes(9)); // 9 bytes = 12 caracteres
                $validator = base64_encode(random_bytes(18)); // 18 bytes = 24 caracteres
                $token_hash = hash('sha256', $validator);

                $expiry = time() + (30 * 24 * 60 * 60); // 30 dias

                // Salvar selector, hash do validator, user_id e expiry no banco
                try {
                    $caminhoBanco = __DIR__ . '/banco.db';
                    $db = new PDO('sqlite:' . $caminhoBanco);
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $stmt = $db->prepare("INSERT INTO remember_tokens (selector, validator_hash, user_id, expiry) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$selector, $token_hash, $_SESSION['user_id'], date('Y-m-d H:i:s', $expiry)]);

                    // Definir o cookie
                    setcookie(
                        'remember_me_token',
                        $selector . '.' . base66_encode($validator), // Concatena selector e validator
                        [
                            'expires' => $expiry,
                            'path' => '/',
                            'domain' => '', // Vazio para o domínio atual
                            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // AQUI A MUDANÇA
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]
                    );
                } catch (Exception $e) {
                    // Tratar erro ao salvar token (pode apenas logar, sem impedir o login)
                    error_log("Erro ao salvar token de lembrar dispositivo: " . $e->getMessage());
                }
            }

            header('Location: adm.php'); // Redireciona para o painel administrativo
            exit();
        } else {
            $erro2FA = 'Código 2FA inválido. Tente novamente.';
        }
    }
    // Exibe o formulário 2FA
    // ... (restante do HTML para o formulário 2FA) ...
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Verificação 2FA</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 350px; text-align: center; }
            h1 { color: #2c3e50; margin-bottom: 20px; }
            input[type="text"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; text-align: center; font-size: 1.2em; }
            button { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1em; }
            button:hover { background-color: #218838; }
            .error-message { color: #dc3545; margin-bottom: 15px; }
            .remember-me { margin-top: 15px; display: flex; align-items: center; justify-content: center; }
            .remember-me input { width: auto; margin-right: 8px; }
            .remember-me label { font-weight: normal; margin-bottom: 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Verificação de Dois Fatores</h1>
            <?php if ($erro2FA): ?>
                <p class="error-message"><?= htmlspecialchars($erro2FA) ?></p>
            <?php endif; ?>
            <p>Por favor, insira o código do seu aplicativo autenticador.</p>
            <form method="POST">
                <input type="text" name="codigo_2fa" placeholder="Código 2FA" required autofocus>
                <?php if (false): // Mantido 'false' aqui conforme a remoção da funcionalidade de lembrar ?>
                <div class="remember-me">
                    <input type="checkbox" id="remember_device" name="remember_device" value="on">
                    <label for="remember_device">Lembrar deste dispositivo por 30 dias</label>
                </div>
                <?php endif; ?>
                <button type="submit">Verificar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Sai do script após exibir o formulário 2FA
}
// --- Fim da Lógica de Autenticação 2FA ---

// --- Início da Lógica de Login Principal ---
// Apenas exibe o formulário de login se o usuário não estiver logado e o 2FA não foi verificado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $erroLogin = '';
    // Tenta validar o token remember me
    if (isset($_COOKIE['remember_me_token']) && !empty($_COOKIE['remember_me_token'])) {
        $token = $_COOKIE['remember_me_token'];
        list($selector, $validator_encoded) = explode('.', $token);

        try {
            $caminhoBanco = __DIR__ . '/banco.db';
            $db = new PDO('sqlite:' . $caminhoBanco);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expiry > ?");
            $stmt->execute([$selector, date('Y-m-d H:i:s')]);
            $remember_token_db = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($remember_token_db) {
                $validator = base66_decode($validator_encoded);
                if (hash('sha256', $validator) === $remember_token_db['validator_hash']) {
                    // Token válido, logar usuário
                    $_SESSION['logado'] = true;
                    $_SESSION['user_id'] = $remember_token_db['user_id']; // Certifique-se de ter 'user_id' na tabela 'remember_tokens'
                    
                    // Buscar role e google_auth_secret do usuário do banco de dados
                    $stmtUser = $db->prepare("SELECT username, role, google_auth_secret FROM users WHERE id = ?");
                    $stmtUser->execute([$_SESSION['user_id']]);
                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['google_auth_secret'] = $user['google_auth_secret']; // Carrega o segredo do 2FA do usuário
                        $_SESSION['2fa_verified'] = true; // Se logou por remember-me, o 2FA já está validado para a sessão

                        // Gerar novo validator para o token
                        $new_validator = base64_encode(random_bytes(18));
                        $new_token_hash = hash('sha256', $new_validator);
                        $new_expiry = time() + (30 * 24 * 60 * 60);

                        // Atualizar token no banco de dados
                        $stmtUpdate = $db->prepare("UPDATE remember_tokens SET validator_hash = ?, expiry = ? WHERE selector = ?");
                        $stmtUpdate->execute([$new_token_hash, date('Y-m-d H:i:s', $new_expiry), $selector]);

                        // Atualizar cookie
                        setcookie(
                            'remember_me_token',
                            $selector . '.' . base66_encode($new_validator),
                            [
                                'expires' => $new_expiry,
                                'path' => '/',
                                'domain' => '',
                                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // AQUI A MUDANÇA
                                'httponly' => true,
                                'samesite' => 'Lax',
                            ]
                        );
                        header('Location: adm.php');
                        exit();
                    }
                }
            }
            // Se o token for inválido ou expirado, apaga-o
            setcookie('remember_me_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        } catch (Exception $e) {
            error_log("Erro no remember me: " . $e->getMessage());
            setcookie('remember_me_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $caminhoBanco = __DIR__ . '/banco.db';
            $db = new PDO('sqlite:' . $caminhoBanco);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Tenta buscar o usuário pelo username
            $stmt = $db->prepare("SELECT id, username, password, google_auth_secret, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica se o usuário existe e a senha está correta
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['logado'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['google_auth_secret'] = $user['google_auth_secret'];

                // Verifica se o 2FA está habilitado para este usuário ou para o founder padrão
                if ($googleAuthEnabled && (!empty($user['google_auth_secret']) || ($user['role'] === 'founder' && !empty($googleAuthSecret)))) {
                    $_SESSION['2fa_verified'] = false; // Exige verificação 2FA
                } else {
                    $_SESSION['2fa_verified'] = true; // Não exige 2FA se não estiver configurado
                }
                header('Location: adm.php');
                exit();
            } else {
                $erroLogin = 'Usuário ou senha incorretos.';
            }
        } catch (Exception $e) {
            $erroLogin = "Erro ao conectar ao banco de dados: " . $e->getMessage();
            error_log("Erro no login: " . $e->getMessage());
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Login - Painel Administrativo</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 350px; text-align: center; }
            h1 { color: #2c3e50; margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; font-weight: bold; text-align: left; }
            input[type="text"], input[type="password"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
            button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1em; }
            button:hover { background-color: #0056b3; }
            .error-message { color: #dc3545; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Login</h1>
            <?php if ($erroLogin): ?>
                <p class="error-message"><?= htmlspecialchars($erroLogin) ?></p>
            <?php endif; ?>
            <form method="POST">
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" required>

                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Entrar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(); // Sai do script após exibir o formulário de login
}

// --- Fim da Lógica de Login Principal ---

// --- Início da Criação e Verificação do Banco de Dados e Tabelas ---
try {
    $caminhoBanco = __DIR__ . '/banco.db'; // Define o caminho do banco de dados

    // Conexão com o banco de dados SQLite
    $db = new PDO('sqlite:' . $caminhoBanco);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criação da tabela 'notas' se não existir
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
        logo_path TEXT DEFAULT 'uploads/logo_default.png' -- Adicionado campo para o caminho da logo
    )");

    // Criação da tabela 'users' se não existir
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        google_auth_secret TEXT,
        role TEXT NOT NULL DEFAULT 'user' -- 'founder', 'admin', 'user'
    )");

    // Criação da tabela 'remember_tokens' se não existir
    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        selector TEXT UNIQUE NOT NULL,
        validator_hash TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        expiry DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Verifica se já existe um usuário 'admin00' com a role 'founder'
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin00'");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count === 0) {
        // Se não existir, insere o usuário 'admin00' com a role 'founder'
        $hashedPassword = password_hash($senhaCorreta, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, google_auth_secret, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin00', $hashedPassword, $googleAuthSecret, 'founder']);
    }

} catch (Exception $e) {
    die("Erro ao acessar o banco de dados: " . $e->getMessage());
}
// --- Fim da Criação e Verificação do Banco de Dados e Tabelas ---

// Lógica para upload da logo
$upload_message = '';
$upload_status = ''; // 'success' ou 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_upload'])) {
    if ($_SESSION['role'] === 'founder') {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0775, true); // Cria a pasta se não existir
        }
        $target_file = $target_dir . "logo.png"; // Força o nome do arquivo para logo.png

        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($_FILES["logo_upload"]["name"], PATHINFO_EXTENSION));

        // Verifica se é uma imagem real ou fake
        $check = getimagesize($_FILES["logo_upload"]["tmp_name"]);
        if ($check !== false) {
            $uploadOk = 1;
        } else {
            $upload_message = "Arquivo não é uma imagem.";
            $upload_status = 'error';
            $uploadOk = 0;
        }

        // Verifica o tamanho do arquivo (limite de 5MB)
        if ($_FILES["logo_upload"]["size"] > 5000000) {
            $upload_message = "Desculpe, seu arquivo é muito grande (máx 5MB).";
            $upload_status = 'error';
            $uploadOk = 0;
        }

        // Permite certos formatos de arquivo
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif") {
            $upload_message = "Desculpe, apenas arquivos JPG, JPEG, PNG e GIF são permitidos.";
            $upload_status = 'error';
            $uploadOk = 0;
        }

        // Verifica se $uploadOk é 0 por um erro
        if ($uploadOk == 0) {
            // Mensagem de erro já definida acima
        } else {
            if (move_uploaded_file($_FILES["logo_upload"]["tmp_name"], $target_file)) {
                $upload_message = "A logo " . htmlspecialchars(basename($_FILES["logo_upload"]["name"])) . " foi enviada e renomeada para logo.png.";
                $upload_status = 'success';
            } else {
                $upload_message = "Desculpe, houve um erro ao enviar seu arquivo.";
                $upload_status = 'error';
            }
        }
    } else {
        $upload_message = "Você não tem permissão para enviar uma logo.";
        $upload_status = 'error';
    }
}


// Lógica de Paginação e Busca
$limite = 10; // Notas por página
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

$termoBusca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

$condicoes = [];
$parametros = [];

if (!empty($termoBusca)) {
    $condicoes[] = "numero LIKE ? OR empresa LIKE ? OR cliente LIKE ? OR data_emissao LIKE ?";
    $parametros[] = '%' . $termoBusca . '%';
    $parametros[] = '%' . $termoBusca . '%';
    $parametros[] = '%' . $termoBusca . '%';
    $parametros[] = '%' . $termoBusca . '%';
}

$sqlCount = "SELECT COUNT(*) FROM notas";
$sqlSelect = "SELECT * FROM notas";

if (!empty($condicoes)) {
    $sqlCount .= " WHERE " . implode(" AND ", $condicoes);
    $sqlSelect .= " WHERE " . implode(" AND ", $condicoes);
}

$sqlSelect .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$parametros[] = $limite;
$parametros[] = $offset;

$stmtCount = $db->prepare($sqlCount);
$stmtCount->execute(array_slice($parametros, 0, count($parametros) - 2)); // Remove limite e offset para o count
$totalNotas = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalNotas / $limite);

$stmt = $db->prepare($sqlSelect);
$stmt->execute($parametros);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Administrativo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
        h1, h2 { text-align: center; color: #2c3e50; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; color: #555; }
        .botoes-container { text-align: center; margin-top: 20px; }
        .botao { display: inline-block; padding: 10px 15px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; text-align: center; }
        .botao-gerar-nota { background-color: #007bff; color: white; }
        .botao-gerar-nota:hover { background-color: #0056b3; }
        .botao-logout { background-color: #dc3545; color: white; }
        .botao-logout:hover { background-color: #c82333; }
        .botao-gerenciar-usuarios { background-color: #ffc107; color: #333; }
        .botao-gerenciar-usuarios:hover { background-color: #e0a800; }
        .search-form { text-align: center; margin-bottom: 20px; }
        .search-form input[type="text"] { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 60%; max-width: 400px; margin-right: 10px; }
        .search-form button { padding: 8px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .search-form button:hover { background-color: #218838; }
        .paginacao { text-align: center; margin-top: 20px; }
        .paginacao a, .paginacao span { display: inline-block; padding: 8px 12px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #007bff; }
        .paginacao a:hover { background-color: #e9ecef; }
        .paginacao span.current { background-color: #007bff; color: white; border-color: #007bff; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Estilos para o upload de logo */
        .logo-upload-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 30px;
            text-align: center;
        }
        .logo-upload-section h3 {
            color: #34495e;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .logo-upload-section input[type="file"] {
            display: block;
            margin: 10px auto;
        }
        .logo-upload-section button {
            background-color: #17a2b8;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .logo-upload-section button:hover {
            background-color: #138496;
        }
    </style>
    <script>
        function confirmarDelete(id) {
            if (confirm("Tem certeza que deseja deletar esta nota fiscal?")) {
                window.location.href = 'adm.php?delete_id=' + id;
            }
        }

        // Função para exportar PDF (exemplo, você pode ter o seu próprio)
        function exportarPDF(id) {
            window.open('detalhe.php?id=' + id + '&export=pdf', '_blank');
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Bem-vindo ao Painel Administrativo, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuário') ?>!</h1>

        <?php if ($upload_message): ?>
            <p class="message <?= $upload_status ?>"><?= htmlspecialchars($upload_message) ?></p>
        <?php endif; ?>

        <div class="botoes-container">
            <a href="gerar_nota.php" class="botao botao-gerar-nota">Gerar Nova Nota Fiscal</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'founder'): ?>
                <a href="gerenciar_usuarios.php" class="botao botao-gerenciar-usuarios">Gerenciar Usuários</a>
            <?php endif; ?>
            <a href="?logout=true" class="botao botao-logout">Sair</a>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'founder'): ?>
        <div class="logo-upload-section">
            <h3>Gerenciamento da Logo (Apenas Founder)</h3>
            <form action="adm.php" method="post" enctype="multipart/form-data">
                <label for="logo_upload">Carregar nova Logo (PNG, JPG, GIF - máx 5MB):</label>
                <input type="file" name="logo_upload" id="logo_upload" accept="image/png, image/jpeg, image/gif">
                <button type="submit">Enviar Logo</button>
            </form>
            <?php
            $logo_current_path = 'uploads/logo.png';
            if (file_exists($logo_current_path)) {
                echo '<p>Logo atual:</p>';
                echo '<img src="' . htmlspecialchars($logo_current_path) . '?' . time() . '" alt="Logo Atual" style="max-width: 150px; height: auto; margin-top: 10px; border: 1px solid #ddd;">';
            } else {
                echo '<p>Nenhuma logo personalizada encontrada. Usando padrão ou nenhuma.</p>';
            }
            ?>
        </div>
        <?php endif; ?>

        <h2>Minhas Notas Fiscais</h2>

        <div class="search-form">
            <form method="GET" action="adm.php">
                <input type="text" name="busca" placeholder="Buscar por número, empresa, cliente, data..." value="<?= htmlspecialchars($termoBusca) ?>">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Empresa</th>
                    <th>Cliente</th>
                    <th>Valor (R$)</th>
                    <th>Data de Emissão</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Nenhuma nota fiscal encontrada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td data-label="Número"><?= $row['numero'] ?></td>
                            <td data-label="Empresa"><?= htmlspecialchars($row['empresa']) ?></td>
                            <td data-label="Cliente"><?= htmlspecialchars($row['cliente']) ?></td>
                            <td data-label="Valor (R$)"><?= number_format($row['valor'], 2, ',', '.') ?></td>
                            <td data-label="Data de Emissão"><?= $row['data_emissao'] ?></td>
                            <td data-label="Ações">
                                <a class="botao" href="detalhe.php?id=<?= $row['id'] ?>">Ver Detalhes</a>
                                <a class="botao" href="editar.php?id=<?= $row['id'] ?>">Editar</a>
                                <button class="botao" onclick="exportarPDF(<?= $row['id'] ?>)">Exportar PDF</button>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'founder'): ?>
                                    <button class="botao" style="background:#c0392b;" onclick="confirmarDelete(<?= $row['id'] ?>)">Deletar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="paginacao">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <a href="adm.php?pagina=<?= $i ?><?= !empty($termoBusca) ? '&busca=' . urlencode($termoBusca) : '' ?>" class="<?= ($i === $pagina) ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>