<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// =========================================================================
// AUTO-CORREÇÃO: Adiciona todas as colunas novas se elas não existirem
try { $pdo->exec("ALTER TABLE metas ADD COLUMN descricao TEXT"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE metas ADD COLUMN orcamento REAL DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE metas ADD COLUMN gasto_real REAL DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE metas ADD COLUMN criado_por TEXT"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE metas ADD COLUMN data_criacao TEXT"); } catch (Exception $e) {}
// =========================================================================

// Permite a entrada se for Admin (1) OU se tiver acesso a metas
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_metas']))) {
    header("Location: ../index.php");
    exit;
}

// Processamento de Ações (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Nova Meta
    if (isset($_POST['nova_meta'])) {
        $orc = empty($_POST['orcamento']) ? 0 : floatval(str_replace(',', '.', $_POST['orcamento']));
        $pdo->prepare("INSERT INTO metas (titulo, descricao, data_limite, orcamento, criado_por, data_criacao) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$_POST['titulo'], $_POST['descricao'], $_POST['data_limite'], $orc, $_SESSION['usuario_nome'], date('Y-m-d H:i:s')]);
        header("Location: metas.php");
        exit;
    }
    
    // Concluir Meta
    if (isset($_POST['concluir_meta'])) {
        $gasto = empty($_POST['gasto_real']) ? 0 : floatval(str_replace(',', '.', $_POST['gasto_real']));
        $pdo->prepare("UPDATE metas SET status = 'Concluída', gasto_real = ? WHERE id = ?")
            ->execute([$gasto, $_POST['id_meta']]);
        header("Location: metas.php");
        exit;
    }
    
    // Reabrir Meta
    if (isset($_POST['reabrir_meta'])) {
        $pdo->prepare("UPDATE metas SET status = 'Pendente' WHERE id = ?")
            ->execute([$_POST['id_meta']]);
        header("Location: metas.php");
        exit;
    }
    
    // Excluir Meta
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
        $pdo->prepare("DELETE FROM metas WHERE id = ?")->execute([$_POST['id_excluir']]);
        header("Location: metas.php");
        exit;
    }
}

// Buscar as metas divididas
$metas_pendentes = $pdo->query("SELECT * FROM metas WHERE status = 'Pendente' ORDER BY data_limite ASC")->fetchAll();
$metas_concluidas = $pdo->query("SELECT * FROM metas WHERE status = 'Concluída' ORDER BY id DESC LIMIT 20")->fetchAll();

// Histórico Geral (Para a tabela inferior)
$historico_geral = $pdo->query("SELECT * FROM metas ORDER BY id DESC LIMIT 100")->fetchAll();

// Sistema Inteligente de Notificações
$notificacoes = [];
$qtd_urgentes = 0;
$hoje = new DateTime(date('Y-m-d'));

foreach ($metas_pendentes as $mt) {
    $int = $hoje->diff(new DateTime($mt['data_limite']));
    if (($int->invert ? -$int->days : $int->days) <= 3) {
        $qtd_urgentes++;
    }
}

if ($qtd_urgentes > 0) {
    $notificacoes[] = [
        'cor' => '#f59e0b',
        'bg' => '#fef3c7',
        'icone' => 'fa-bullseye',
        'titulo' => 'Metas Urgentes',
        'texto' => "Você tem $qtd_urgentes meta(s) vencendo nos próximos 3 dias ou atrasadas!"
    ];
}
$tem_notificacao = count($notificacoes) > 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Metas DOP</title>
    <style>
        /* Estilos do Aviso Online Profissional */
        .badge-online { 
            background: #dcfce7; 
            color: #15803d; 
            border-radius: 20px; 
            padding: 6px 15px; 
            font-size: 0.85rem; 
            font-weight: bold; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        
        .topbar-right { display: flex; align-items: center; gap: 15px; }

        /* Estrutura Compacta das Colunas (Igual Kanban O.S.) */
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 15px;
            align-items: stretch;
        }
        .kanban-col {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 5px 12px 12px; 
            border: 2px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            height: 45vh; 
            min-height: 350px; 
        }
        .kanban-col h3 {
            text-align: center;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #cbd5e1;
            font-size: 1.05em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-right: 10px; 
        }
        
        .kanban-cards-area {
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 8px;
            flex: 1; 
        }
        
        .kanban-cards-area::-webkit-scrollbar { width: 5px; }
        .kanban-cards-area::-webkit-scrollbar-track { background: transparent; }
        .kanban-cards-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .kanban-cards-area::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .os-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 5px solid var(--text-muted);
            transition: 0.2s;
            display: flex;
            flex-direction: column;
        }
        .os-card:hover { transform: translateY(-2px); box-shadow: 0 6px 10px rgba(0,0,0,0.08); }
        
        .os-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .os-title { font-size: 1.05em; font-weight: bold; color: var(--text-main); margin: 0; line-height: 1.3; }
        .os-meta { color: var(--text-muted); font-size: 0.8em; margin-bottom: 4px; }
        
        .os-actions { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 15px; 
            border-top: 1px dashed #e2e8f0; 
            padding-top: 12px; 
        }
        
        .btn-k { padding: 6px 12px; font-size: 0.8em; border-radius: 6px; border: none; cursor: pointer; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-next { background: #dcfce7; color: #15803d; } .btn-next:hover { background: #bbf7d0; }
        .btn-info { background: #dbeafe; color: #1d4ed8; } .btn-info:hover { background: #bfdbfe; }

        .finance-box {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.85em;
            border: 1px solid #e2e8f0;
        }
        .finance-row { display: flex; justify-content: space-between; margin-bottom: 6px; }

        #buscaHistorico {
            padding: 8px 15px; 
            border-radius: 8px; 
            border: 2px solid #cbd5e1; 
            font-size: 0.9em; 
            outline: none; 
            min-width: 250px;
            transition: 0.3s;
        }
        #buscaHistorico:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'metas.php'; include 'menu_nav.php'; ?>

    <main class="content" id="main-content" style="padding-bottom: 20px;">
        <header class="topbar" style="margin-bottom: 20px;">
            <button class="hamburger" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i> Menu</button>
            <div class="topbar-right">
                <span class="badge-online">
                    <i class="fa-solid fa-circle" style="font-size: 0.6rem;"></i> 
                    <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> (Online)
                </span>

                <button class="btn-notif <?php echo $tem_notificacao ? 'tem-alerta' : ''; ?>" onclick="abrirNotificacoes()">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($tem_notificacao): ?><span class="badge-notif"><?php echo count($notificacoes); ?></span><?php endif; ?>
                </button>
            </div>
            
            <div class="dropdown-notif" id="dropdown-notif">
                <div class="notif-header">Avisos de Metas</div>
                <?php if(!$tem_notificacao): ?>
                    <div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Prazos sob controle!</div>
                <?php else: foreach($notificacoes as $n): ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="background:<?php echo $n['bg']; ?>; color:<?php echo $n['cor']; ?>;"><i class="fa-solid <?php echo $n['icone']; ?>"></i></div>
                        <div>
                            <strong style="display:block; font-size:0.95em; color:var(--text-main); margin-bottom:3px;"><?php echo $n['titulo']; ?></strong>
                            <span style="font-size:0.85em; color:var(--text-muted); display:block;"><?php echo $n['texto']; ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </header>

        <h1 style="color: var(--primary-green); margin-bottom: 20px; font-size: 1.6em;"><i class="fa-solid fa-bullseye"></i> Metas Operacionais DOP</h1>
        
        <section class="stat-card" style="margin-bottom:20px; border-top: 4px solid var(--primary-green);">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" 
                 onclick="var f = document.getElementById('form-meta'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'flex'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main);"><i class="fa-solid fa-plus-circle"></i> Estabelecer Nova Meta</h3>
                <i class="fa-solid fa-chevron-down seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            
            <form id="form-meta" method="POST" style="display:none; gap:15px; flex-wrap:wrap; margin-top:20px; padding-top:20px; border-top:1px dashed #cbd5e1;">
                <input type="text" name="titulo" placeholder="Qual é o objetivo?" required style="flex:2; min-width: 200px; padding: 10px;">
                <input type="text" name="descricao" placeholder="Detalhes (Opcional)" style="flex:2; min-width: 150px; padding: 10px;">
                <input type="number" step="0.01" name="orcamento" placeholder="Orçamento R$ (Opcional)" style="flex:1; min-width: 150px; padding: 10px;">
                
                <div style="flex:1; display:flex; align-items:center; gap:10px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius:10px; padding: 0 10px; min-width: 180px;">
                    <span style="font-size:0.85em; font-weight:bold; color:var(--text-muted);">Prazo:</span>
                    <input type="date" name="data_limite" required style="border:none; background:transparent; padding:10px 5px; outline:none;">
                </div>
                <button type="submit" name="nova_meta" class="btn" style="padding: 10px 20px;"><i class="fa-solid fa-rocket"></i> Lançar Meta</button>
            </form>
        </section>

        <form id="form-imprimir" action="imprimir_metas.php" method="POST" target="_blank" style="display:none;"></form>

        <div class="kanban-board">
            <div class="kanban-col" style="border-top: 4px solid var(--warning);">
                <h3 style="color: var(--warning); justify-content: center;"><i class="fa-solid fa-fire"></i> Em Andamento (<?php echo count($metas_pendentes); ?>)</h3>
                
                <div class="kanban-cards-area">
                    <?php if(empty($metas_pendentes)) echo "<p style='text-align:center; color:#94a3b8; font-size:0.8em; margin-top:20px;'>Nenhuma meta ativa.</p>"; ?>
                    
                    <?php foreach($metas_pendentes as $m): 
                        $limite_obj = new DateTime($m['data_limite']); 
                        $int = $hoje->diff($limite_obj); 
                        $dias = $int->invert ? -$int->days : $int->days;
                        
                        $cor_borda = '#3b82f6'; $texto_prazo = "Faltam $dias dias"; $icone = "fa-clock"; 
                        if ($dias < 0) { $cor_borda = 'var(--danger)'; $texto_prazo = "ATRASADA HÁ ".abs($dias)." DIAS!"; $icone = "fa-triangle-exclamation"; } 
                        elseif ($dias <= 3) { $cor_borda = 'var(--warning)'; $texto_prazo = "Vence em $dias dias!"; $icone = "fa-bell"; }
                    ?>
                    <div class="os-card" style="border-left-color: <?php echo $cor_borda; ?>;">
                        <div class="os-header">
                            <h4 class="os-title"><?php echo htmlspecialchars($m['titulo']); ?></h4>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Excluir meta?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id_excluir" value="<?php echo $m['id']; ?>">
                                <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </div>
                        <?php if(!empty($m['descricao'])): ?>
                            <p class="os-meta"><?php echo htmlspecialchars($m['descricao']); ?></p>
                        <?php endif; ?>
                        
                        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                            <div style="display:inline-flex; align-items:center; gap:6px; background:<?php echo $cor_borda; ?>15; color:<?php echo $cor_borda; ?>; padding:4px 8px; border-radius:6px; font-size:0.75em; font-weight:800;">
                                <i class="fa-solid <?php echo $icone; ?>"></i> <?php echo $texto_prazo; ?> (<?php echo date('d/m/Y', strtotime($m['data_limite'])); ?>)
                            </div>
                            <?php if($m['orcamento'] > 0): ?>
                            <div style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; color:#475569; padding:4px 8px; border-radius:6px; font-size:0.75em; font-weight:800;">
                                <i class="fa-solid fa-wallet"></i> Orçamento: R$ <?php echo number_format($m['orcamento'], 2, ',', '.'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="os-actions" style="justify-content: flex-end;">
                            <button type="button" class="btn-k btn-next" onclick="document.getElementById('box-concluir-<?php echo $m['id']; ?>').style.display='flex'">
                                Finalizar Meta <i class="fa-solid fa-check-double"></i>
                            </button>
                        </div>
                        
                        <form id="box-concluir-<?php echo $m['id']; ?>" method="POST" style="display:none; align-items:center; gap:10px; flex-wrap:wrap; margin-top:15px; padding-top:15px; border-top:1px dashed #cbd5e1;">
                            <input type="hidden" name="id_meta" value="<?php echo $m['id']; ?>">
                            <span style="font-size:0.85em; font-weight:bold; color:var(--text-main);">Custo Final: R$</span>
                            <input type="number" step="0.01" name="gasto_real" required placeholder="0.00" style="width:100px; height:32px; padding:5px 10px; border-radius:6px; border:2px solid #cbd5e1;">
                            <button type="submit" name="concluir_meta" class="btn" style="height:32px; padding:0 15px; font-size:0.8em; border-radius:6px;">OK</button>
                            <button type="button" class="btn" style="height:32px; padding:0 15px; background:#94a3b8; font-size:0.8em; border-radius:6px;" onclick="this.parentElement.style.display='none'">X</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 4px solid var(--primary-green);">
                <h3>
                    <div style="display:flex; align-items:center; gap:8px; color: var(--primary-green);">
                        <i class="fa-solid fa-trophy"></i> Conquistas Recentes
                    </div>
                    <button type="submit" form="form-imprimir" class="btn" style="background:#3b82f6; padding:4px 8px; font-size:0.7em;" title="Imprimir selecionados">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </h3>
                
                <div class="kanban-cards-area">
                    <?php if(empty($metas_concluidas)) echo "<p style='text-align:center; color:#94a3b8; font-size:0.8em; margin-top:20px;'>Bata uma meta para ver aqui.</p>"; ?>
                    
                    <?php foreach($metas_concluidas as $mc): 
                        $orc = $mc['orcamento']; 
                        $gasto = $mc['gasto_real']; 
                        $diferenca = $orc - $gasto;
                    ?>
                    <div class="os-card" style="border-left-color: var(--primary-green); background-color: #f8fafc;">
                        <div class="os-header" style="margin-bottom: 5px;">
                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                <input type="checkbox" name="metas_imprimir[]" value="<?php echo $mc['id']; ?>" form="form-imprimir" style="width:18px;height:18px;accent-color:var(--primary-green);margin-top:2px;">
                                <h4 class="os-title" style="text-decoration: line-through; color: var(--text-muted);"><?php echo htmlspecialchars($mc['titulo']); ?></h4>
                            </div>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja apagar definitivamente?')">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id_excluir" value="<?php echo $mc['id']; ?>">
                                <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                        </div>
                        
                        <div class="finance-box">
                            <div class="finance-row">
                                <span style="color: var(--text-muted);">Valor Previsto:</span>
                                <strong>R$ <?php echo number_format($orc, 2, ',', '.'); ?></strong>
                            </div>
                            <div class="finance-row">
                                <span style="color: var(--text-muted);">Valor Gasto:</span>
                                <strong>R$ <?php echo number_format($gasto, 2, ',', '.'); ?></strong>
                            </div>
                            
                            <div style="border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px; display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Resultado:</span>
                                <?php 
                                    if ($orc > 0) {
                                        if ($diferenca > 0) {
                                            echo "<span style='color: #15803d; font-weight: bold;'><i class='fa-solid fa-arrow-trend-down'></i> Economia de R$ " . number_format($diferenca, 2, ',', '.') . "</span>";
                                        } elseif ($diferenca < 0) {
                                            echo "<span style='color: #b91c1c; font-weight: bold;'><i class='fa-solid fa-arrow-trend-up'></i> Estouro de R$ " . number_format(abs($diferenca), 2, ',', '.') . "</span>";
                                        } else {
                                            echo "<span style='color: #0369a1; font-weight: bold;'><i class='fa-solid fa-bullseye'></i> No alvo exato</span>";
                                        }
                                    } else {
                                        echo "<span style='color: #64748b; font-weight: bold;'>Sem orçamento definido</span>";
                                    }
                                ?>
                            </div>
                        </div>
                        
                        <div class="os-actions" style="justify-content: flex-start; margin-top: 10px; padding-top: 10px;">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="id_meta" value="<?php echo $mc['id']; ?>">
                                <button type="submit" name="reabrir_meta" class="btn-k btn-info" style="background:transparent; color:#64748b; border:1px solid #cbd5e1;"><i class="fa-solid fa-rotate-left"></i> Reabrir</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="stat-card" style="padding:0; overflow:hidden; border-top:4px solid #64748b; margin-top: 25px;">
            <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin:0; font-size:1.1em;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico Geral de Metas</h3>
                <input type="text" id="buscaHistorico" placeholder="🔍 Pesquisar por título, descrição, status..." onkeyup="filtrarHistorico()">
            </div>
            <div class="scroll-box" style="max-height: 350px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <tr>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Data Limite</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Objetivo da Meta</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Finanças (Previsto vs Real)</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historico_geral)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">Nenhum registo encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach($historico_geral as $hg): 
                            if($hg['status'] == 'Pendente') { $bg_s = '#fef3c7'; $cor_s = '#b45309'; }
                            else { $bg_s = '#dcfce7'; $cor_s = '#15803d'; }
                        ?>
                            <tr class="linha-historico">
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.85em; color:var(--text-muted);">
                                    <strong><?php echo date('d/m/Y', strtotime($hg['data_limite'])); ?></strong><br>
                                    <small>Criada em: <?php echo date('d/m/Y', strtotime($hg['data_criacao'])); ?></small>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.9em;">
                                    <strong><?php echo htmlspecialchars($hg['titulo']); ?></strong><br>
                                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($hg['descricao']); ?></small>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.85em;">
                                    <span style="color:var(--text-muted);">Orçamento:</span> R$ <?php echo number_format($hg['orcamento'], 2, ',', '.'); ?><br>
                                    <?php if($hg['status'] == 'Concluída'): ?>
                                        <span style="color:var(--text-muted);">Custo:</span> <strong>R$ <?php echo number_format($hg['gasto_real'], 2, ',', '.'); ?></strong>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">Em andamento...</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9;">
                                    <span style="background:<?php echo $bg_s; ?>; color:<?php echo $cor_s; ?>; padding:4px 8px; border-radius:4px; font-size:0.8em; font-weight:bold;"><?php echo $hg['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        function toggleMenu() { const s = document.getElementById('sidebar'); const c = document.getElementById('main-content'); const o = document.getElementById('overlay'); if (window.innerWidth > 768) { if(s) s.classList.toggle('closed'); if(c) c.classList.toggle('expanded'); } else { if(s) s.classList.toggle('open'); if(o) o.classList.toggle('active'); } }
        function abrirNotificacoes() { document.getElementById('dropdown-notif').classList.toggle('show'); }
        window.onclick = function(event) { if (!event.target.matches('.btn-notif') && !event.target.closest('.btn-notif') && !event.target.closest('.dropdown-notif')) { var dropdown = document.getElementById("dropdown-notif"); if (dropdown && dropdown.classList.contains('show')) { dropdown.classList.remove('show'); } } }
        
        function filtrarHistorico() {
            let termo = document.getElementById('buscaHistorico').value.toLowerCase();
            let linhas = document.querySelectorAll('.linha-historico');
            linhas.forEach(function(linha) {
                let texto = linha.innerText.toLowerCase();
                if(texto.includes(termo)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>