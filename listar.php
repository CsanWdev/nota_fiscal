<?php
$db = new PDO('sqlite:C:/xampp/htdocs/nota_fiscal/banco.db');

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
    data_emissao TEXT
)");

$result = $db->query("SELECT * FROM notas ORDER BY id DESC");

echo "<h2>Histórico de Notas Fiscais</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr>
    <th>Número</th>
    <th>Empresa</th>
    <th>Cliente</th>
    <th>Produtos/Serviços</th>
    <th>Valor (R$)</th>
    <th>Data</th>
</tr>";

foreach ($result as $row) {
    echo "<tr>";
    echo "<td>".$row['numero']."</td>";
    echo "<td>".$row['empresa']."</td>";
    echo "<td>".$row['cliente']."</td>";
    echo "<td>".$row['produtos']."</td>";
    echo "<td>".number_format($row['valor'], 2, ',', '.')."</td>";
    echo "<td>".$row['data_emissao']."</td>";
    echo "</tr>";
}

echo "</table>";
?>
