<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// MUDANÇA AQUI: Permite a entrada se for Admin (1) OU se tiver acesso a O.S.
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_os']))) {
    header("Location: ../index.php");
    exit;
}

// Processamento de Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nova OS
    if (isset($_POST['nova_os'])) {
        $desc = (isset($_POST['urgente']) && $_POST['urgente'] == '1' ? '[URGENTE] ' : '') . trim($_POST['descricao']);
        $pdo->prepare("INSERT INTO ordens_servico (descricao, local, solicitante, status, criado_por, data_criacao) VALUES (?, ?, ?, 'Pendente', ?, ?)")
            ->execute([$desc, $_POST['local'], $_POST['solicitante'], $_SESSION['usuario_nome'], date('d/m/Y H:i')]);
        
        $pdo->prepare("INSERT INTO historico_manutencao (categoria, identificador, acao, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
            ->execute(['O.S.', $desc, 'Abertura de Chamado', $_SESSION['usuario_nome'], date('d/m/Y H:i')]);
            
        header("Location: os_kanban.php"); exit;
    }
    // Mudar Status
    if (isset($_POST['mudar_status'])) {
        $id = $_POST['id_os'];
        $novo_status = $_POST['novo_status'];
        $stmt = $pdo->prepare("SELECT descricao FROM ordens_servico WHERE id = ?");
        $stmt->execute([$id]);
        $desc_os = $stmt->fetchColumn();

        $pdo->prepare("UPDATE ordens_servico SET status = ? WHERE id = ?")->execute([$novo_status, $id]);
        
        $pdo->prepare("INSERT INTO historico_manutencao (categoria, identificador, acao, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
            ->execute(['O.S.', $desc_os, "Moveu para: $novo_status", $_SESSION['usuario_nome'], date('d/m/Y H:i')]);

        header("Location: os_kanban.php"); exit;
    }
    // Excluir OS
    if (isset($_POST['excluir_os'])) {
        $id = $_POST['id_os'];
        $stmt = $pdo->prepare("SELECT descricao FROM ordens_servico WHERE id = ?");
        $stmt->execute([$id]);
        $desc_os = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM ordens_servico WHERE id = ?")->execute([$id]);
        
        $pdo->prepare("INSERT INTO historico_manutencao (categoria, identificador, acao, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
            ->execute(['O.S.', $desc_os, 'Exclusão de Registro', $_SESSION['usuario_nome'], date('d/m/Y H:i')]);

        header("Location: os_kanban.php"); exit;
    }
}

// Consultas
$pendentes = $pdo->query("SELECT * FROM ordens_servico WHERE status = 'Pendente' ORDER BY id DESC")->fetchAll();
$executando = $pdo->query("SELECT * FROM ordens_servico WHERE status = 'Executando' ORDER BY id DESC")->fetchAll();
$concluidos = $pdo->query("SELECT * FROM ordens_servico WHERE status = 'Concluido' ORDER BY id DESC LIMIT 15")->fetchAll(); 
$historico_atividades = $pdo->query("SELECT * FROM historico_manutencao WHERE categoria = 'O.S.' ORDER BY id DESC LIMIT 100")->fetchAll();

// Lógica de Notificações (Aviso quando tiver pendências)
$notificacoes = []; 
$urgentes = 0;
foreach($pendentes as $p) { if(strpos($p['descricao'], '[URGENTE]') !== false) $urgentes++; }

if($urgentes > 0) {
    $notificacoes[] = ['cor' => '#ef4444', 'bg' => '#fee2e2', 'icone' => 'fa-triangle-exclamation', 'titulo' => 'Chamado Urgente!', 'texto' => "Existem $urgentes O.S. prioritárias pendentes."];
}

$total_pendentes = count($pendentes);
if($total_pendentes > 0 && $urgentes == 0) {
    $notificacoes[] = ['cor' => '#f59e0b', 'bg' => '#fef3c7', 'icone' => 'fa-clock', 'titulo' => 'O.S. Pendentes', 'texto' => "Existem $total_pendentes chamados aguardando atendimento."];
}
$tem_notificacao = count($notificacoes) > 0;

function formatDesc($t) {
    if (strpos($t, '[URGENTE]') !== false) {
        return '<span style="background:var(--danger); color:white; padding:2px 6px; border-radius:6px; font-size:0.75rem; font-weight:bold; margin-right:6px;"><i class="fa-solid fa-triangle-exclamation"></i> URGENTE</span>' . str_replace('[URGENTE]', '', $t);
    }
    return $t;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Quadro O.S.</title>
    <style>
        .badge-online { background: #dcfce7; color: #15803d; border-radius: 20px; padding: 6px 15px; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .topbar-right { display: flex; align-items: center; gap: 15px; }

        .os-title { font-size: 1.1rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 8px; line-height: 1.4; }
        .os-meta { font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        
        .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
        .kanban-col { background: #f8fafc; border-radius: 20px; padding: 20px; border: 2px solid #e2e8f0; height: 50vh; min-height: 450px; display: flex; flex-direction: column; }
        .kanban-col h3 { font-size: 1.15rem; font-weight: 800; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        
        .os-card { background: white; border-radius: 15px; padding: 18px; margin-bottom: 15px; box-shadow: var(--shadow); border-left: 6px solid #64748b; position: relative; }
        .os-card.pendente { border-left-color: var(--warning); }
        .os-card.executando { border-left-color: #3b82f6; }
        .os-card.concluido { border-left-color: var(--primary-green); opacity: 0.8; }
        
        .btn-delete { background: none; border: none; color: #cbd5e1; cursor: pointer; transition: 0.2s; font-size: 1rem; }
        .btn-delete:hover { color: var(--danger); }
        
        .card-actions { display: flex; gap: 8px; margin-top: 15px; border-top: 1px dashed #eee; padding-top: 12px; }
        .btn-status { flex: 1; padding: 8px; border-radius: 8px; border: none; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; color: #fff; transition: 0.2s; }
        .btn-voltar-status { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-voltar-status:hover { background: #e2e8f0; }
        
        #buscaHistorico { padding: 10px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-size: 1rem; width: 300px; outline: none; }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'os_kanban.php'; include 'menu_nav.php'; ?>
    <main class="content" id="main-content">
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
                <div class="notif-header">Alertas da Manutenção</div>
                <?php if(!$tem_notificacao): ?><div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Sem pendências.</div>
                <?php else: foreach($notificacoes as $n): ?><div class="notif-item"><div class="notif-icon" style="background:<?php echo $n['bg']; ?>; color:<?php echo $n['cor']; ?>;"><i class="fa-solid <?php echo $n['icone']; ?>"></i></div><div><strong style="display:block; font-size:0.95em;"><?php echo $n['titulo']; ?></strong><span style="font-size:0.85rem; color:var(--text-muted);"><?php echo $n['texto']; ?></span></div></div><?php endforeach; endif; ?>
            </div>
        </header>

        <h1 style="color: var(--primary-green); margin-bottom: 25px; font-weight: 800; font-size: 1.8rem;"><i class="fa-solid fa-list-check"></i> Gestão de Ordens de Serviço</h1>

        <section class="stat-card" style="border-top: 5px solid var(--primary-green); margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" 
                 onclick="var f = document.getElementById('form-os'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'grid'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main); font-size: 1.2rem;"><i class="fa-solid fa-plus-circle"></i> Abrir Nova O.S. Interna</h3>
                <i class="fa-solid fa-chevron-down seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            
            <form id="form-os" method="POST" style="display:none; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-top:20px; padding-top:20px; border-top:1px dashed #eee;">
                <input type="text" name="descricao" placeholder="Descrição do serviço" required style="padding:12px; border-radius:8px; border:2px solid #e2e8f0;">
                <input type="text" name="local" placeholder="Local/Setor" required style="padding:12px; border-radius:8px; border:2px solid #e2e8f0;">
                <input type="text" name="solicitante" value="<?php echo $_SESSION['usuario_nome']; ?>" required style="padding:12px; border-radius:8px; border:2px solid #e2e8f0;">
                <select name="urgente" style="padding:12px; font-weight:800; border-radius:8px; border:2px solid #e2e8f0;"><option value="0">Normal</option><option value="1" style="color:red;">🚨 URGENTE</option></select>
                <button type="submit" name="nova_os" class="btn" style="padding:12px; font-weight:800;"><i class="fa-solid fa-check"></i> LANÇAR O.S.</button>
            </form>
        </section>

        <div class="kanban-board">
            <div class="kanban-col" style="border-top: 5px solid var(--warning);">
                <h3>Pendente (<?php echo count($pendentes); ?>)</h3>
                <div class="scroll-box" style="flex:1;">
                    <?php foreach($pendentes as $os): ?>
                        <div class="os-card pendente">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div class="os-title"><?php echo formatDesc($os['descricao']); ?></div>
                                <form method="POST" onsubmit="return confirm('Excluir O.S.?');">
                                    <input type="hidden" name="id_os" value="<?php echo $os['id']; ?>">
                                    <button type="submit" name="excluir_os" class="btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                            <div class="os-meta"><i class="fa-solid fa-location-dot"></i> <b><?php echo htmlspecialchars($os['local']); ?></b></div>
                            <div class="os-meta"><i class="fa-regular fa-calendar"></i> <?php echo $os['data_criacao']; ?></div>
                            <form method="POST" class="card-actions">
                                <input type="hidden" name="mudar_status" value="1"><input type="hidden" name="id_os" value="<?php echo $os['id']; ?>"><input type="hidden" name="novo_status" value="Executando">
                                <button type="submit" class="btn-status" style="background:#3b82f6;"><i class="fa-solid fa-play"></i> INICIAR</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 5px solid #3b82f6;">
                <h3>
                    <span>Em Execução</span>
                    <button type="submit" form="form-imprimir" class="btn" style="background:#3b82f6; padding:5px 10px; font-size:0.75rem;"><i class="fa-solid fa-print"></i></button>
                </h3>
                <div class="scroll-box" style="flex:1;">
                    <form id="form-imprimir" action="imprimir_os.php" method="POST" target="_blank">
                    <?php foreach($executando as $os): ?>
                        <div class="os-card executando">
                            <div style="display:flex; gap:10px; align-items:flex-start;">
                                <input type="checkbox" name="os_imprimir[]" value="<?php echo $os['id']; ?>" style="width:18px;height:18px;accent-color:#3b82f6;margin-top:2px;">
                                <div class="os-title"><?php echo formatDesc($os['descricao']); ?></div>
                            </div>
                            <div class="os-meta"><i class="fa-solid fa-location-dot"></i> <b><?php echo htmlspecialchars($os['local']); ?></b></div>
                            <div class="os-meta"><i class="fa-regular fa-calendar"></i> <?php echo $os['data_criacao']; ?></div>
                            <div class="card-actions">
                                <button type="submit" name="mudar_status" value="1" class="btn-status btn-voltar-status" onclick="this.form.action=''; this.form.target='_self'; document.getElementById('nv-<?php echo $os['id']; ?>').value='Pendente';">
                                    <i class="fa-solid fa-rotate-left"></i> Voltar
                                </button>
                                <button type="submit" name="mudar_status" value="1" class="btn-status" style="background:var(--primary-green);" onclick="this.form.action=''; this.form.target='_self'; document.getElementById('nv-<?php echo $os['id']; ?>').value='Concluido';">
                                    <i class="fa-solid fa-check"></i> Concluir
                                </button>
                                <input type="hidden" name="id_os" value="<?php echo $os['id']; ?>">
                                <input type="hidden" name="novo_status" id="nv-<?php echo $os['id']; ?>" value="Concluido">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </form>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 5px solid var(--primary-green);">
                <h3>Finalizados</h3>
                <div class="scroll-box" style="flex:1;">
                    <?php foreach($concluidos as $os): ?>
                        <div class="os-card concluido">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div class="os-title" style="text-decoration: line-through; opacity:0.7;"><?php echo htmlspecialchars($os['descricao']); ?></div>
                                <form method="POST" onsubmit="return confirm('Excluir?');">
                                    <input type="hidden" name="id_os" value="<?php echo $os['id']; ?>">
                                    <button type="submit" name="excluir_os" class="btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                            <div class="os-meta" style="margin-top: 5px;"><i class="fa-regular fa-calendar-check"></i> <?php echo $os['data_criacao']; ?></div>
                            <form method="POST" class="card-actions">
                                <input type="hidden" name="id_os" value="<?php echo $os['id']; ?>">
                                <input type="hidden" name="novo_status" value="Executando">
                                <button type="submit" name="mudar_status" value="1" class="btn-status btn-voltar-status" style="width:100%;">
                                    <i class="fa-solid fa-rotate-left"></i> Reabrir
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="stat-card" style="padding:0; overflow:hidden; border-top:5px solid #64748b; margin-top:35px;">
            <div style="padding:15px 20px; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin:0; font-size:1.1rem;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico Geral de O.S.</h3>
                <input type="text" id="buscaHistorico" placeholder="🔍 Pesquisar no histórico..." onkeyup="filtrarHistorico()">
            </div>
            <div class="scroll-box" style="max-height: 350px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10; box-shadow: 0 1px 0 #eee;">
                        <tr><th style="padding:12px; text-align:left; font-size:0.85rem;">Data / Hora</th><th style="padding:12px; text-align:left; font-size:0.85rem;">Descrição</th><th style="padding:12px; text-align:left; font-size:0.85rem;">Ação</th><th style="padding:12px; text-align:left; font-size:0.85rem;">Usuário</th></tr>
                    </thead>
                    <tbody id="tabelaHist">
                        <?php foreach($historico_atividades as $hg): ?>
                            <tr class="linha-hist" style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px; font-size:0.85rem; color:var(--text-muted);"><i class="fa-regular fa-calendar"></i> <?php echo $hg['data_hora']; ?></td>
                                <td style="padding:12px; font-size:0.95rem;"><b><?php echo htmlspecialchars($hg['identificador']); ?></b></td>
                                <td style="padding:12px;"><span style="font-weight:800; font-size:0.75rem; text-transform:uppercase; background:#f1f5f9; padding:4px 8px; border-radius:4px;"><?php echo $hg['acao']; ?></span></td>
                                <td style="padding:12px; font-size:0.85rem;"><i class="fa-solid fa-user-tag" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($hg['usuario']); ?></td>
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