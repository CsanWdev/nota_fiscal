<?php
session_start();

// Verifica se o usuário está logado e se o 2FA foi verificado.
// É CRUCIAL proteger este script de upload.
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    $_SESSION['upload_status'] = 'error';
    $_SESSION['upload_message'] = 'Acesso não autorizado para upload de logo.';
    header('Location: adm.php'); // Redireciona para a página de login
    exit();
}

// Verifica se o formulário foi enviado e se um arquivo foi selecionado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logoFile'])) {
    $target_dir = __DIR__ . "/uploads/"; // Diretório onde a logo será salva

    // Cria a pasta 'uploads' se ela não existir
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) { // 0755 para permissões
            $_SESSION['upload_status'] = 'error';
            $_SESSION['upload_message'] = 'Erro: Não foi possível criar o diretório de uploads.';
            header('Location: adm.php');
            exit();
        }
    }

    $target_file = $target_dir . "logo.png"; // Nome fixo da logo

    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($_FILES["logoFile"]["name"], PATHINFO_EXTENSION));

    // Verifica se é uma imagem real ou um fake
    $check = getimagesize($_FILES["logoFile"]["tmp_name"]);
    if ($check !== false) {
        $uploadOk = 1;
    } else {
        $_SESSION['upload_status'] = 'error';
        $_SESSION['upload_message'] = 'O arquivo não é uma imagem.';
        $uploadOk = 0;
    }

    // Verifica o tamanho do arquivo (limite de 5MB = 5 * 1024 * 1024 bytes)
    if ($_FILES["logoFile"]["size"] > 5000000) {
        $_SESSION['upload_status'] = 'error';
        $_SESSION['upload_message'] = 'Desculpe, o arquivo é muito grande (máx. 5MB).';
        $uploadOk = 0;
    }

    // Permite certos formatos de arquivo
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        $_SESSION['upload_status'] = 'error';
        $_SESSION['upload_message'] = 'Desculpe, apenas arquivos JPG, JPEG, PNG e GIF são permitidos.';
        $uploadOk = 0;
    }

    // Verifica se $uploadOk é 0 por algum erro
    if ($uploadOk == 0) {
        // Nada a fazer aqui, a mensagem de erro já foi definida
    } else {
        // Se tudo estiver ok, tenta fazer o upload do arquivo
        if (move_uploaded_file($_FILES["logoFile"]["tmp_name"], $target_file)) {
            $_SESSION['upload_status'] = 'success';
            $_SESSION['upload_message'] = 'A logo "logo.png" foi atualizada com sucesso!';
        } else {
            $_SESSION['upload_status'] = 'error';
            $_SESSION['upload_message'] = 'Desculpe, houve um erro ao fazer o upload da sua logo.';
        }
    }
} else {
    $_SESSION['upload_status'] = 'error';
    $_SESSION['upload_message'] = 'Nenhum arquivo de logo selecionado ou erro no envio do formulário.';
}

// Redireciona de volta para adm.php
header('Location: adm.php');
exit();
?>