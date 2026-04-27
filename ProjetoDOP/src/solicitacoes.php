<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// MUDANÇA AQUI: Verificando se é admin OU se tem acesso_solicitacoes (antes estava acesso_os)
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_solicitacoes']))) {
    header("Location: ../index.php");
    exit;
}

// Processamento de Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mudar_status'])) {
        $pdo->prepare("UPDATE solicitacoes SET status = ?, analisado_por = ?, data_analise = ? WHERE id = ?")
            ->execute([$_POST['novo_status'], $_SESSION['usuario_nome'], date('d/m/Y H:i'), $_POST['id_solicitacao']]);
        header("Location: solicitacoes.php"); exit;
    }
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
        $pdo->prepare("DELETE FROM solicitacoes WHERE id = ?")->execute([$_POST['id_excluir']]);
        header("Location: solicitacoes.php"); exit;
    }
}

// Consultas
$aguardando = $pdo->query("SELECT * FROM solicitacoes WHERE status = 'Aguardando' ORDER BY id DESC")->fetchAll();
$aceitas = $pdo->query("SELECT * FROM solicitacoes WHERE status = 'Aceita' ORDER BY id DESC LIMIT 50")->fetchAll();
$recusadas = $pdo->query("SELECT * FROM solicitacoes WHERE status = 'Recusada' ORDER BY id DESC LIMIT 50")->fetchAll(); 
$historico_geral = $pdo->query("SELECT * FROM solicitacoes ORDER BY id DESC LIMIT 150")->fetchAll();

$notificacoes = [];
if (count($aguardando) > 0) {
    $notificacoes[] = ['cor' => '#f59e0b', 'bg' => '#fef3c7', 'icone' => 'fa-bell', 'titulo' => 'Triagem Pendente', 'texto' => "Existem " . count($aguardando) . " solicitações aguardando análise."];
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
    <title>SGOI - Solicitações Profissional</title>
    <style>
        /* Estilos de Alta Legibilidade */
        .os-title { font-size: 1.15rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 8px; }
        .os-meta { font-size: 0.95rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .badge-online { background: #dcfce7; color: #15803d; border-radius: 20px; padding: 4px 12px; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .topbar-right { display: flex; align-items: center; gap: 15px; }

        .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
        .kanban-col { background: #f8fafc; border-radius: 20px; padding: 20px; border: 2px solid #e2e8f0; height: 50vh; min-height: 450px; display: flex; flex-direction: column; }
        .kanban-col h3 { font-size: 1.2rem; font-weight: 800; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; }
        .os-card { background: white; border-radius: 15px; padding: 18px; margin-bottom: 15px; box-shadow: var(--shadow); border-left: 6px solid #cbd5e1; }
        .os-card.aguardando { border-left-color: var(--warning); }
        .os-card.aceita { border-left-color: var(--primary-green); }
        .os-card.recusada { border-left-color: var(--danger); opacity: 0.8; }
        #buscaHistorico { padding: 10px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-size: 1rem; width: 300px; outline: none; transition: 0.3s; }
        #buscaHistorico:focus { border-color: var(--primary-green); box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1); }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'solicitacoes.php'; include 'menu_nav.php'; ?>
    <main class="content" id="main-content">
        <header class="topbar" style="margin-bottom: 20px;">
            <button class="hamburger" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i> Menu</button>
            <div class="topbar-right">
                <span class="badge-online">
                    <i class="fa-solid fa-circle" style="font-size: 0.6rem;"></i> 
                    <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> (Online)
                </span>
                <button class="btn-notif <?php echo $tem_notificacao ? 'tem-alerta' : ''; ?>" onclick="abrirNotificacoes()"><i class="fa-solid fa-bell"></i><?php if($tem_notificacao): ?><span class="badge-notif"><?php echo count($notificacoes); ?></span><?php endif; ?></button>
            </div>
            <div class="dropdown-notif" id="dropdown-notif">
                <div class="notif-header">Avisos de Triagem</div>
                <?php if(!$tem_notificacao): ?><div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Tudo em dia!</div>
                <?php else: foreach($notificacoes as $n): ?><div class="notif-item"><div class="notif-icon" style="background:<?php echo $n['bg']; ?>; color:<?php echo $n['cor']; ?>;"><i class="fa-solid <?php echo $n['icone']; ?>"></i></div><div><strong style="display:block; font-size:0.95rem;"><?php echo $n['titulo']; ?></strong><span style="font-size:0.85rem; color:var(--text-muted);"><?php echo $n['texto']; ?></span></div></div><?php endforeach; endif; ?>
            </div>
        </header>

        <h1 style="color: var(--primary-green); margin-bottom: 25px; font-weight: 800; font-size: 1.8rem;"><i class="fa-solid fa-inbox"></i> Central de Solicitações</h1>

        <div class="kanban-board">
            <div class="kanban-col" style="border-top: 5px solid var(--warning);">
                <h3 style="color: var(--warning);"><i class="fa-solid fa-hourglass-half"></i> Pendentes (<?php echo count($aguardando); ?>)</h3>
                <div class="scroll-box" style="flex:1;">
                    <?php foreach($aguardando as $sol): ?>
                        <div class="os-card aguardando">
                            <div class="os-title">#<?php echo $sol['id']; ?> - <?php echo htmlspecialchars($sol['descricao']); ?></div>
                            <div class="os-meta"><i class="fa-solid fa-location-dot"></i> <b><?php echo htmlspecialchars($sol['local']); ?></b></div>
                            <div class="os-meta"><i class="fa-solid fa-user"></i> De: <?php echo htmlspecialchars($sol['solicitante']); ?></div>
                            <div class="os-meta"><i class="fa-regular fa-calendar"></i> Criado em: <?php echo $sol['data_criacao']; ?></div>
                            <div style="display:flex; gap:10px; margin-top:15px; padding-top:12px; border-top:1px dashed #eee;">
                                <form method="POST" style="flex:1;"><input type="hidden" name="mudar_status" value="1"><input type="hidden" name="id_solicitacao" value="<?php echo $sol['id']; ?>"><input type="hidden" name="novo_status" value="Recusada"><button type="submit" class="btn" style="background:var(--danger); width:100%; padding:8px; font-size:0.8rem;"><i class="fa-solid fa-xmark"></i> Negar</button></form>
                                <form method="POST" style="flex:1;"><input type="hidden" name="mudar_status" value="1"><input type="hidden" name="id_solicitacao" value="<?php echo $sol['id']; ?>"><input type="hidden" name="novo_status" value="Aceita"><button type="submit" class="btn" style="width:100%; padding:8px; font-size:0.8rem;"><i class="fa-solid fa-check"></i> Aceitar</button></form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 5px solid var(--primary-green);">
                <h3 style="color: var(--primary-green);"><i class="fa-solid fa-check-double"></i> Aceitas</h3>
                <div class="scroll-box" style="flex:1;">
                    <?php foreach($aceitas as $sol): ?>
                        <div class="os-card aceita">
                            <div class="os-title">#<?php echo $sol['id']; ?> - <?php echo htmlspecialchars($sol['descricao']); ?></div>
                            <div class="os-meta"><b><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($sol['local']); ?></b></div>
                            <div class="os-meta" style="color:var(--primary-green); font-weight:800;"><i class="fa-solid fa-user-check"></i> Por: <?php echo htmlspecialchars($sol['analisado_por'] ?? 'Sistema'); ?></div>
                            <div class="os-meta"><i class="fa-regular fa-calendar-check"></i> Aceito em: <?php echo $sol['data_analise'] ?? $sol['data_criacao']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 5px solid var(--danger);">
                <h3 style="color: var(--danger);"><i class="fa-solid fa-ban"></i> Recusadas</h3>
                <div class="scroll-box" style="flex:1;">
                    <?php foreach($recusadas as $sol): ?>
                        <div class="os-card recusada">
                            <div class="os-title" style="text-decoration: line-through;">#<?php echo $sol['id']; ?> - <?php echo htmlspecialchars($sol['descricao']); ?></div>
                            <div class="os-meta"><b><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($sol['local']); ?></b></div>
                            <div class="os-meta" style="color:var(--danger); font-weight:800;"><i class="fa-solid fa-user-xmark"></i> Por: <?php echo htmlspecialchars($sol['analisado_por'] ?? 'Sistema'); ?></div>
                            <div class="os-meta"><i class="fa-regular fa-calendar-xmark"></i> Recusado em: <?php echo $sol['data_analise'] ?? $sol['data_criacao']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="stat-card" style="padding:0; overflow:hidden; border-top:5px solid #64748b; margin-top:35px;">
            <div style="padding:15px 20px; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin:0; font-size:1.1rem;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de Triagem</h3>
                <input type="text" id="buscaHistorico" placeholder="🔍 Pesquisar local, responsável..." onkeyup="filtrarHistorico()">
            </div>
            <div class="scroll-box" style="max-height: 350px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position: sticky; top: 0; background: white; box-shadow: 0 1px 0 #eee;">
                        <tr><th style="padding:12px; text-align:left; font-size:0.85rem;">Data</th><th style="padding:12px; text-align:left; font-size:0.85rem;">Descrição</th><th style="padding:12px; text-align:left; font-size:0.85rem;">Status</th></tr>
                    </thead>
                    <tbody id="tabelaHist">
                        <?php foreach($historico_geral as $hg): ?>
                            <tr class="linha-hist" style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px; font-size:0.85rem; color:var(--text-muted);"><i class="fa-regular fa-calendar"></i> <?php echo $hg['data_criacao']; ?></td>
                                <td style="padding:12px; font-size:0.95rem;"><b><?php echo htmlspecialchars($hg['descricao']); ?></b><br><small><?php echo htmlspecialchars($hg['local']); ?></small></td>
                                <td style="padding:12px;"><span style="font-weight:800; font-size:0.8rem;"><?php echo $hg['status']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>
        function toggleMenu() { document.getElementById('sidebar').classList.toggle('closed'); document.getElementById('main-content').classList.toggle('expanded'); }
        function abrirNotificacoes() { document.getElementById('dropdown-notif').classList.toggle('show'); }
        function filtrarHistorico() { let t = document.getElementById('buscaHistorico').value.toLowerCase(); document.querySelectorAll('.linha-hist').forEach(l => { l.style.display = l.innerText.toLowerCase().includes(t) ? '' : 'none'; }); }
        window.onclick = function(e) { if (!e.target.matches('.btn-notif') && !e.target.closest('.btn-notif') && !e.target.closest('.dropdown-notif')) { var d = document.getElementById("dropdown-notif"); if (d && d.classList.contains('show')) d.classList.remove('show'); } }
    </script>
</body>
</html>