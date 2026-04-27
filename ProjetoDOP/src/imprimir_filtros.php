<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// Verifica se está logado
if (!isset($_SESSION['usuario_id'])) { die("Acesso negado."); }

// Pega os IDs selecionados
$ids = $_POST['flt_imprimir'] ?? [];

if (empty($ids)) {
    die("<script>alert('Por favor, selecione pelo menos um filtro na lista antes de imprimir.'); window.close();</script>");
}

// Prepara a consulta dinamicamente para os IDs selecionados
$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM filtros WHERE id IN ($placeholders) ORDER BY data_vencimento ASC");
$stmt->execute($ids);
$filtros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Filtros - SGOI</title>
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

    <h2>Relatório de Manutenção - Filtros de Água</h2>

    <table>
        <thead>
            <tr>
                <th>Localização do Bebedouro</th>
                <th>Tipo de Refil</th>
                <th>Data de Vencimento</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hoje = new DateTime(date('Y-m-d'));
            foreach ($filtros as $f): 
                $venc = new DateTime($f['data_vencimento']);
                $dif = $hoje->diff($venc);
                $dias = $dif->invert ? -$dif->days : $dif->days;
                
                $status = ($dias < 0) ? "VENCIDO" : (($dias <= 90) ? "VENCE EM $dias DIAS" : "OK (Em dia)");
            ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($f['local']); ?></strong></td>
                    <td><?php echo htmlspecialchars($f['tipo']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($f['data_vencimento'])); ?></td>
                    <td><b><?php echo $status; ?></b></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        SGOI - Sistema de Gestão Operacional Integrada | DOP
    </div>
</body>
</html>