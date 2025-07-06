<?php
session_start();

// Verifica se o usuário está logado e se o 2FA foi verificado
// Se não estiver logado ou o 2FA não foi verificado, redireciona para a página de login (adm.php)
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true || !isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
    header('Location: adm.php'); // Redireciona para a página de login
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerador de Nota Fiscal</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
            color: #2c3e50;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
            color: #34495e;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background-color: #218838;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .button-group button {
            width: auto; /* Permite que os botões tenham largura automática */
            flex-grow: 1; /* Faz com que os botões dividam o espaço igualmente */
            padding: 10px 15px; /* Ajusta o padding para botões menores */
        }
        .button-group .preview-button {
            background-color: #007bff; /* Azul para o botão de pré-visualização */
        }
        .button-group .preview-button:hover {
            background-color: #0056b3;
        }
        .button-group .clear-button {
            background-color: #6c757d; /* Cinza para o botão de limpar */
        }
        .button-group .clear-button:hover {
            background-color: #5a6268;
        }

        #invoicePreview {
            border: 1px solid #ccc;
            padding: 15px;
            margin-top: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        #invoicePreview p {
            margin: 5px 0;
        }
        #invoicePreview strong {
            color: #34495e;
        }
        .back-to-admin {
            display: block;
            margin-top: 20px;
            text-align: center;
            background: #007bff; /* Cor azul para o botão Voltar */
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
        }
        .back-to-admin:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerador de Nota Fiscal</h1>

        <form id="invoiceForm">
            <h2>Dados da Empresa</h2>
            <label for="empresa">Empresa</label>
            <input type="text" id="empresa" name="empresa" value="DATAHOTEL SISTEMAS LTDA" required>

            <label for="cnpj">CNPJ</label>
            <input type="text" id="cnpj" name="cnpj" value="11.960.533/0001-03" required>

            <label for="enderecoEmpresa">Endereço da Empresa</label>
            <input type="text" id="enderecoEmpresa" name="enderecoEmpresa" value="Rua Dr. Miguel Couto, 1373 - Centro, São Sebastião, SP" required>

            <label for="telefoneEmpresa">Telefone da Empresa</label>
            <input type="text" id="telefoneEmpresa" name="telefoneEmpresa" value="(12)3863-2345" required>

            <h2>Dados do Cliente</h2>
            <label for="cliente">Cliente</label>
            <input type="text" id="cliente" name="cliente" required>

            <label for="enderecoCliente">Endereço do Cliente</label>
            <input type="text" id="enderecoCliente" name="enderecoCliente" required>

            <label for="cepCliente">CEP do Cliente</label>
            <input type="text" id="cepCliente" name="cepCliente" required>

            <h2>Dados da Nota Fiscal</h2>
            <label for="produtos">Produtos/Serviços (uma linha por item)</label>
            <textarea id="produtos" name="produtos" rows="5" required></textarea>

            <label for="valor">Valor (R$)</label>
            <input type="number" id="valor" name="valor" step="0.01" required>

            <div class="button-group">
                <button type="button" onclick="previewInvoice()" class="preview-button">Pré-visualizar Nota</button>
                <button type="button" onclick="clearForm()" class="clear-button">Limpar Formulário</button>
            </div>
            <button type="submit">Salvar Nota Fiscal</button>
        </form>

        <a href="adm.php" class="back-to-admin">Voltar ao Painel Administrativo</a>


        <div id="invoicePreview" style="display: none;">
            <h2>Pré-visualização da Nota Fiscal</h2>
            <p><strong>Número da Nota:</strong> <span id="numeroNotaPreview"></span></p>
            <h3>Dados da Empresa</h3>
            <p><strong>Empresa:</strong> <span id="nfEmpresa"></span></p>
            <p><strong>CNPJ:</strong> <span id="nfCnpj"></span></p>
            <p><strong>Endereço:</strong> <span id="nfEnderecoEmpresa"></span></p>
            <p><strong>Telefone:</strong> <span id="nfTelefoneEmpresa"></span></p>
            <h3>Dados do Cliente</h3>
            <p><strong>Cliente:</strong> <span id="nfCliente"></span></p>
            <p><strong>Endereço:</strong> <span id="nfEnderecoCliente"></span></p>
            <p><strong>CEP:</strong> <span id="nfCepCliente"></span></p>
            <h3>Produtos/Serviços</h3>
            <p id="nfProdutos"></p>
            <h3>Valor Total</h3>
            <p><strong>Valor:</strong> R$ <span id="nfValor"></span></p>
            <button onclick="generatePdfPreview()">Gerar PDF da Pré-visualização</button>
        </div>
    </div>

    <script>
        document.getElementById('invoiceForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const numeroNota = await fetch('/nota_fiscal/next_invoice_number.php') // Supondo que você terá um script para obter o próximo número
                .then(response => response.json())
                .then(data => data.next_number)
                .catch(error => {
                    console.error('Erro ao obter o próximo número da nota:', error);
                    alert('Erro ao obter o número da nota. Verifique o console.');
                    return null;
                });

            if (numeroNota === null) return;

            const dados = {
                numero: numeroNota, // Usar o número obtido
                empresa: document.getElementById('empresa').value,
                cnpj: document.getElementById('cnpj').value,
                enderecoEmpresa: document.getElementById('enderecoEmpresa').value,
                telefoneEmpresa: document.getElementById('telefoneEmpresa').value,
                cliente: document.getElementById('cliente').value,
                enderecoCliente: document.getElementById('enderecoCliente').value,
                cepCliente: document.getElementById('cepCliente').value,
                produtos: document.getElementById('produtos').value,
                valor: parseFloat(document.getElementById('valor').value)
            };

            try {
                const response = await fetch('salvar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dados)
                });

                const result = await response.json();

                if (result.status === 'sucesso') {
                    alert('Nota Fiscal salva com sucesso!');
                    // Redireciona para o painel administrativo após salvar
                    window.location.href = 'adm.php';
                } else {
                    alert('Erro ao salvar Nota Fiscal: ' + result.mensagem);
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro de comunicação ao salvar Nota Fiscal.');
            }
        });

        function previewInvoice() {
            document.getElementById('nfEmpresa').innerText = document.getElementById('empresa').value;
            document.getElementById('nfCnpj').innerText = document.getElementById('cnpj').value;
            document.getElementById('nfEnderecoEmpresa').innerText = document.getElementById('enderecoEmpresa').value;
            document.getElementById('nfTelefoneEmpresa').innerText = document.getElementById('telefoneEmpresa').value;
            document.getElementById('nfCliente').innerText = document.getElementById('cliente').value;
            document.getElementById('nfEnderecoCliente').innerText = document.getElementById('enderecoCliente').value;
            document.getElementById('nfCepCliente').innerText = document.getElementById('cepCliente').value;
            document.getElementById('nfProdutos').innerText = document.getElementById('produtos').value;
            document.getElementById('nfValor').innerText = parseFloat(document.getElementById('valor').value).toFixed(2).replace('.', ',');

            // Para o número da nota na pré-visualização, podemos usar um placeholder ou buscar o próximo número.
            // Por enquanto, vamos usar um placeholder 'PRÉV.'.
            document.getElementById('numeroNotaPreview').innerText = 'PRÉV.';

            document.getElementById('invoicePreview').style.display = 'block';
        }

        function clearForm() {
            document.getElementById('invoiceForm').reset();
            document.getElementById('invoicePreview').style.display = 'none';
        }

        // Função para gerar o PDF da pré-visualização (ainda é o PDF simples do jsPDF)
        function generatePdfPreview() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            const empresa = document.getElementById('nfEmpresa').innerText;
            const cnpj = document.getElementById('nfCnpj').innerText;
            const enderecoEmpresa = document.getElementById('nfEnderecoEmpresa').innerText;
            const telefoneEmpresa = document.getElementById('nfTelefoneEmpresa').innerText;
            const cliente = document.getElementById('nfCliente').innerText;
            const enderecoCliente = document.getElementById('nfEnderecoCliente').innerText;
            const cepCliente = document.getElementById('nfCepCliente').innerText;
            const produtos = document.getElementById('nfProdutos').innerText;
            const valor = document.getElementById('nfValor').innerText;
            const numeroNota = document.getElementById('numeroNotaPreview').innerText; // Usa o número da pré-visualização

            let y = 20;
            doc.setFontSize(16);
            doc.text(`Nota Fiscal Nº ${numeroNota}`, 20, y);
            y += 10;
            doc.setFontSize(12);
            doc.text(`Empresa: ${empresa}`, 20, y);
            y += 7;
            doc.text(`CNPJ: ${cnpj}`, 20, y);
            y += 7;
            doc.text(`Endereço: ${enderecoEmpresa}`, 20, y);
            y += 7;
            doc.text(`Telefone: ${telefoneEmpresa}`, 20, y);
            y += 10;
            doc.text(`Cliente: ${cliente}`, 20, y);
            y += 7;
            doc.text(`Endereço: ${enderecoCliente}`, 20, y);
            y += 7;
            doc.text(`CEP: ${cepCliente}`, 20, y);
            y += 10;
            doc.text(`Produtos/Serviços:`, 20, y);
            y += 7;
            // Quebra as linhas dos produtos/serviços
            const produtosLines = doc.splitTextToSize(produtos, 170);
            doc.text(produtosLines, 20, y);
            y += (produtosLines.length * 7); // Ajusta 'y' com base no número de linhas

            y += 10;
            doc.text(`Valor: R$ ${valor}`, 20, y);

            doc.save(`nota_fiscal_${numeroNota}.pdf`);
        }
    </script>
</body>
</html>