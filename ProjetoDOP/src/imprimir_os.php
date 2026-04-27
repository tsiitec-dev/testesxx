<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// Segurança
if (!isset($_SESSION['usuario_id']) || $_SESSION['acesso_os'] != 1) { die("Acesso Negado."); }

// Verifica se selecionou alguma coisa
if (!isset($_POST['os_imprimir']) || empty($_POST['os_imprimir'])) {
    echo "<script>alert('Nenhuma O.S. selecionada para impressão!'); window.close(); window.location.href='os_kanban.php';</script>";
    exit;
}

// Prepara a consulta buscando só os IDs marcados na tela anterior
$ids = implode(',', array_map('intval', $_POST['os_imprimir']));
$ordens = $pdo->query("SELECT * FROM ordens_servico WHERE id IN ($ids) ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Ordem de Serviço - SGOI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; color: #1e293b; background: #fff; margin: 0; padding: 30px; }
        .cabecalho { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #3b82f6; padding-bottom: 20px; margin-bottom: 30px; }
        .cabecalho img { height: 60px; }
        .cabecalho h1 { margin: 0; font-size: 1.5em; color: #3b82f6; text-transform: uppercase; }
        .cabecalho p { margin: 5px 0 0 0; font-size: 0.85em; color: #64748b; }
        
        .ficha-os { border: 2px solid #cbd5e1; border-radius: 8px; margin-bottom: 30px; page-break-inside: avoid; }
        .ficha-header { background: #f1f5f9; padding: 15px 20px; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
        .ficha-body { padding: 20px; }
        
        .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .info-box strong { display: block; font-size: 0.8em; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .info-box span { font-size: 1.1em; color: #0f172a; }

        .descricao-box { padding: 15px; border-top: 1px dashed #cbd5e1; margin-top: 10px; }
        .descricao-box strong { color: #64748b; font-size: 0.8em; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .descricao-box p { margin: 0; font-size: 1.1em; color: #0f172a; line-height: 1.5; }

        .assinatura { margin-top: 30px; display: flex; justify-content: space-between; padding: 0 40px; }
        .linha-ass { border-top: 1px solid #000; width: 250px; text-align: center; padding-top: 5px; font-size: 0.8em; }

        .area-botoes { margin-top: 40px; display: flex; gap: 15px; border-top: 1px dashed #cbd5e1; padding-top: 20px; }
        .btn-acao { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: 0.2s; }
        .btn-acao:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-azul { background: #3b82f6; color: white; }
        .btn-cinza { background: #64748b; color: white; }

        @media print {
            body { padding: 0; }
            .area-botoes { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="cabecalho">
        <div>
            <h1>Fichas de Execução - O.S.</h1>
            <p>Gerado por: <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> em <?php echo date('d/m/Y \à\s H:i'); ?></p>
        </div>
        <img src="../logo.png" alt="SGOI Logo">
    </div>

    <?php foreach($ordens as $os): ?>
    <div class="ficha-os">
        <div class="ficha-header">
            <span>ORDEM DE SERVIÇO #<?php echo str_pad($os['id'], 4, '0', STR_PAD_LEFT); ?></span>
            <span>Data: <?php echo htmlspecialchars($os['data_criacao']); ?></span>
        </div>
        
        <div class="ficha-body">
            <div class="grid-info">
                <div class="info-box">
                    <strong>Local / Sala:</strong>
                    <span><?php echo htmlspecialchars($os['local']); ?></span>
                </div>
                <div class="info-box">
                    <strong>Solicitante:</strong>
                    <span><?php echo htmlspecialchars($os['solicitante']); ?></span>
                </div>
            </div>

            <div class="descricao-box">
                <strong>Descrição do Serviço Requisitado:</strong>
                <p><?php echo nl2br(htmlspecialchars($os['descricao'])); ?></p>
            </div>

            <div class="descricao-box" style="min-height: 80px;">
                <strong>Observações do Técnico (Preencher em campo):</strong>
            </div>

            <div class="assinatura">
                <div class="linha-ass">Assinatura do Solicitante</div>
                <div class="linha-ass">Assinatura do Técnico / DOP</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="area-botoes">
        <button onclick="window.close(); window.location.href='os_kanban.php';" class="btn-acao btn-cinza">
            <i class="fa-solid fa-arrow-left"></i> Voltar para Ordens de Serviço
        </button>
        <button onclick="window.print()" class="btn-acao btn-azul">
            <i class="fa-solid fa-print"></i> Reimprimir Fichas
        </button>
    </div>

</body>
</html>