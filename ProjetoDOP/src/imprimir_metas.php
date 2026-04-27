<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// Verifica se está logado
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

// Pega os IDs selecionados
$ids = $_POST['metas_imprimir'] ?? [];

if (empty($ids)) {
    die("<script>alert('Por favor, selecione pelo menos uma meta na coluna de Conquistas antes de imprimir.'); window.close();</script>");
}

// Prepara a consulta dinamicamente para os IDs selecionados
$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM metas WHERE id IN ($placeholders) ORDER BY data_limite ASC");
$stmt->execute($ids);
$metas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Metas Concluídas - SGOI</title>
    <style>
        body { font-family: Arial, sans-serif; color: #000; background: #fff; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header img { max-height: 60px; }
        .header-info { text-align: right; font-size: 0.85em; color: #333; }
        h2 { text-align: center; text-transform: uppercase; margin-bottom: 20px; font-size: 1.4em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 10px; text-align: left; font-size: 0.9em; }
        th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; }
        .footer { text-align: center; margin-top: 30px; font-size: 0.8em; color: #555; border-top: 1px solid #ccc; padding-top: 10px; }
        .resultado-ok { color: #15803d; font-weight: bold; }
        .resultado-bad { color: #b91c1c; font-weight: bold; }
        @media print {
            @page { margin: 1.5cm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <img src="../logo.png" alt="SGOI Logo">
        <div class="header-info">
            <strong>Relatório Gerado por:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?><br>
            <strong>Data da Emissão:</strong> <?php echo date('d/m/Y \à\s H:i'); ?>
        </div>
    </div>

    <h2>Relatório de Metas Concluídas (DOP)</h2>

    <table>
        <thead>
            <tr>
                <th>Objetivo da Meta</th>
                <th>Data Limite</th>
                <th>Orçamento Previsto</th>
                <th>Custo Final</th>
                <th>Balanço</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metas as $m): 
                $orc = $m['orcamento'];
                $gasto = $m['gasto_real'];
                $diferenca = $orc - $gasto;
                
                $resultado = "Sem orçamento definido";
                $class = "";
                if ($orc > 0) {
                    if ($diferenca > 0) {
                        $resultado = "Economia (R$ " . number_format($diferenca, 2, ',', '.') . ")";
                        $class = "resultado-ok";
                    } elseif ($diferenca < 0) {
                        $resultado = "Estouro (R$ " . number_format(abs($diferenca), 2, ',', '.') . ")";
                        $class = "resultado-bad";
                    } else {
                        $resultado = "No alvo exato";
                    }
                }
            ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($m['titulo']); ?></strong>
                        <?php if(!empty($m['descricao'])): ?>
                            <br><small style="color: #555;"><?php echo htmlspecialchars($m['descricao']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($m['data_limite'])); ?></td>
                    <td>R$ <?php echo number_format($orc, 2, ',', '.'); ?></td>
                    <td>R$ <?php echo number_format($gasto, 2, ',', '.'); ?></td>
                    <td class="<?php echo $class; ?>"><?php echo $resultado; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        SGOI - Sistema de Gestão Operacional Integrada | DOP
    </div>
</body>
</html>