<?php
session_start(); 
date_default_timezone_set('America/Sao_Paulo'); 
require_once '../config/database.php';

// MUDANÇA AQUI: Permite a entrada se for Admin (1) OU se tiver acesso ao dashboard
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_dashboard']))) { 
    header("Location: ../index.php"); 
    exit; 
}

$hora_atual = date('H:i'); 
$hoje = new DateTime(date('Y-m-d'));

// --- CONSULTAS DE DADOS ---
$solicitacoes_novas = $pdo->query("SELECT COUNT(*) FROM solicitacoes WHERE status = 'Aguardando'")->fetchColumn();
$os_pendentes = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status = 'Pendente'")->fetchColumn();
$emprestados = $pdo->query("SELECT COUNT(*) FROM ativos WHERE status = 'Emprestado'")->fetchColumn();
$estoque_baixo = $pdo->query("SELECT COUNT(*) FROM estoque WHERE quantidade <= 3")->fetchColumn();

// Listagens para os cards
$metas_painel = $pdo->query("SELECT * FROM metas WHERE status = 'Pendente' ORDER BY data_limite ASC LIMIT 6")->fetchAll();
$lista_os = $pdo->query("SELECT id, descricao, local, status FROM ordens_servico WHERE status != 'Concluido' ORDER BY id DESC LIMIT 6")->fetchAll();
$lista_emprestados = $pdo->query("SELECT identificador, categoria, responsavel FROM ativos WHERE status = 'Emprestado' ORDER BY identificador ASC LIMIT 6")->fetchAll();
$lista_solicitacoes = $pdo->query("SELECT id, descricao, solicitante, data_criacao FROM solicitacoes WHERE status = 'Aguardando' ORDER BY id DESC LIMIT 5")->fetchAll();
$lista_estoque = $pdo->query("SELECT produto, quantidade, unidade FROM estoque WHERE quantidade <= 3 ORDER BY quantidade ASC LIMIT 5")->fetchAll();
$lista_extintores = $pdo->query("SELECT local, tipo, data_vencimento FROM extintores ORDER BY data_vencimento ASC LIMIT 5")->fetchAll();
$lista_filtros = $pdo->query("SELECT local, tipo, data_vencimento FROM filtros ORDER BY data_vencimento ASC LIMIT 5")->fetchAll();
$lista_usuarios = $pdo->query("SELECT nome FROM usuarios ORDER BY nome ASC LIMIT 5")->fetchAll();

// --- CENTRAL DE AVISOS (TODOS OS ALERTAS) ---
$notificacoes = [];

// 1. Alerta de Solicitações
if ($solicitacoes_novas > 0) { 
    $notificacoes[] = ['cor' => '#3b82f6', 'bg' => '#eff6ff', 'icone' => 'fa-inbox', 'titulo' => 'Solicitações', 'texto' => "$solicitacoes_novas novos pedidos aguardando triagem."]; 
}

// 2. Alerta de O.S. Urgentes
$os_urgentes = 0;
foreach($lista_os as $os) { if(strpos($os['descricao'], '[URGENTE]') !== false) $os_urgentes++; }
if ($os_urgentes > 0) {
    $notificacoes[] = ['cor' => '#ef4444', 'bg' => '#fee2e2', 'icone' => 'fa-triangle-exclamation', 'titulo' => 'O.S. Urgente', 'texto' => "Existem $os_urgentes chamados prioritários abertos."];
}

// 3. Alerta de Metas Próximas
$metas_vencendo = 0;
foreach($metas_painel as $m) {
    $prazo = new DateTime($m['data_limite']); $dif = $hoje->diff($prazo); $dias = $dif->invert ? -$dif->days : $dif->days;
    if ($dias <= 3) $metas_vencendo++;
}
if ($metas_vencendo > 0) {
    $notificacoes[] = ['cor' => '#f59e0b', 'bg' => '#fef3c7', 'icone' => 'fa-bullseye', 'titulo' => 'Prazos de Metas', 'texto' => "$metas_vencendo meta(s) vencendo em breve ou atrasada(s)."];
}

// 4. Alerta de Estoque Crítico
if ($estoque_baixo > 0) { 
    $notificacoes[] = ['cor' => '#8b5cf6', 'bg' => '#f5f3ff', 'icone' => 'fa-box-open', 'titulo' => 'Estoque Baixo', 'texto' => "Há $estoque_baixo item(ns) com quantidade crítica (<=3)."]; 
}

// 5. Alerta de Portaria (Após as 16:30)
if ($hora_atual >= '16:30' && $emprestados > 0) { 
    $notificacoes[] = ['cor' => '#ef4444', 'bg' => '#fee2e2', 'icone' => 'fa-clock-rotate-left', 'titulo' => 'Portaria', 'texto' => "Atenção: $emprestados item(ns) ainda não devolvidos hoje."]; 
}

// 6. Alerta de Segurança (Extintores/Filtros Vencidos)
$seguranca_vencida = 0;
foreach($lista_extintores as $ex) { if(new DateTime($ex['data_vencimento']) < $hoje) $seguranca_vencida++; }
foreach($lista_filtros as $fl) { if(new DateTime($fl['data_vencimento']) < $hoje) $seguranca_vencida++; }
if ($seguranca_vencida > 0) {
    $notificacoes[] = ['cor' => '#b91c1c', 'bg' => '#fef2f2', 'icone' => 'fa-shield-halved', 'titulo' => 'Segurança', 'texto' => "Existem $seguranca_vencida item(ns) (Extintor/Filtro) com validade vencida!"];
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
    <title>SGOI - Painel de Controle</title>
    <style>
        /* Estilos Exclusivos do Painel (O resto já está no style.css global) */
        .stat-card-pro { background: var(--white); padding: 25px 15px; border-radius: 20px; box-shadow: var(--shadow); border-top: 6px solid #e2e8f0; text-align: center; }
        .stat-card-pro small { font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px; }
        .stat-card-pro h2 { font-size: 3.2rem; font-weight: 800; margin: 0; line-height: 1; }

        .card-header-pro { padding: 18px 22px; border-bottom: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; background: transparent; }
        .card-header-pro h3 { font-size: 1.15rem; font-weight: 700; color: var(--primary-dark); margin: 0; display: flex; align-items: center; gap: 10px; }

        .list-item-pro { padding: 14px 22px; border-bottom: 1px solid rgba(0,0,0,0.05); transition: 0.2s; }
        .list-item-pro:hover { background: #f8fafc; }
        .list-item-pro strong { font-size: 1rem; color: var(--text-main); display: block; margin-bottom: 4px; }
        .list-item-pro .sub-info { font-size: 0.88rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .badge-status { padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        
        .btn-icon { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; text-decoration: none; font-size: 0.9rem; transition: 0.2s; color: #fff; }
        .btn-icon:hover { opacity: 0.85; transform: translateX(3px); }
        .dashboard-grid-main { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-top: 25px; }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'painel.php'; include 'menu_nav.php'; ?>
    
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
                <div class="notif-header">Alertas do Sistema (<?php echo count($notificacoes); ?>)</div>
                <?php if(!$tem_notificacao): ?>
                    <div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Sem pendências no momento.</div>
                <?php else: foreach($notificacoes as $n): ?>
                    <div class="notif-item">
                        <div class="notif-icon" style="background:<?php echo $n['bg']; ?>; color:<?php echo $n['cor']; ?>;"><i class="fa-solid <?php echo $n['icone']; ?>"></i></div>
                        <div>
                            <strong style="display:block; font-size:0.95rem;"><?php echo $n['titulo']; ?></strong>
                            <span style="font-size:0.85rem; color:var(--text-muted);"><?php echo $n['texto']; ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </header>

        <h1 style="color: var(--primary-dark); margin-bottom: 35px; font-weight: 800; font-size: 2.2rem;">
            <i class="fa-solid fa-gauge-high" style="color: var(--primary-green);"></i> Painel de Controle Integrado
        </h1>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card-pro" style="border-top-color: #3b82f6;"><small>Solicitações</small><h2 style="color:#3b82f6;"><?php echo $solicitacoes_novas; ?></h2></div>
            <div class="stat-card-pro" style="border-top-color: var(--warning);"><small>O.S. Pendentes</small><h2 style="color:var(--warning);"><?php echo $os_pendentes; ?></h2></div>
            <div class="stat-card-pro" style="border-top-color: var(--danger);"><small>Estoque Crítico</small><h2 style="color:var(--danger);"><?php echo $estoque_baixo; ?></h2></div>
            <div class="stat-card-pro" style="border-top-color: var(--primary-green);"><small>Portaria em Uso</small><h2 style="color:var(--primary-green);"><?php echo $emprestados; ?></h2></div>
        </div>

        <div class="dashboard-grid-main">
            
            <div class="stat-card" style="padding: 0; border-top: 5px solid #f59e0b;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-bullseye"></i> Metas</h3><a href="metas.php" class="btn-icon" style="background:#f59e0b;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php if(empty($metas_painel)) echo "<p style='padding:20px; text-align:center; color:var(--text-muted);'>Nenhuma meta pendente.</p>"; ?>
                    <?php foreach($metas_painel as $m): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($m['titulo']); ?></strong><div class="sub-info"><span class="badge-status badge-warning">Venc: <?php echo date('d/m/Y', strtotime($m['data_limite'])); ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #8b5cf6;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-list-check"></i> O.S. Ativas</h3><a href="os_kanban.php" class="btn-icon" style="background:#8b5cf6;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php if(empty($lista_os)) echo "<p style='padding:20px; text-align:center; color:var(--text-muted);'>Nenhum serviço ativo.</p>"; ?>
                    <?php foreach($lista_os as $os): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($os['descricao']); ?></strong><div class="sub-info"><span><?php echo htmlspecialchars($os['local']); ?></span><span class="badge-status badge-info"><?php echo $os['status']; ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #ef4444;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-box-open"></i> Estoque</h3><a href="estoque.php" class="btn-icon" style="background:#ef4444;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php if(empty($lista_estoque)) echo "<p style='padding:20px; text-align:center; color:var(--text-muted);'>Tudo abastecido.</p>"; ?>
                    <?php foreach($lista_estoque as $est): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($est['produto']); ?></strong><div class="sub-info"><span class="badge-status badge-danger">Qtd: <?php echo $est['quantidade'] . " " . $est['unidade']; ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #10b981;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-users"></i> Online Agora</h3></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <div class="list-item-pro" style="background:#f8fafc;">
                        <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> (Você)</strong>
                        <div class="sub-info"><span class="badge-status badge-success"><i class="fa-solid fa-circle"></i> Conectado</span></div>
                    </div>
                    <?php foreach($lista_usuarios as $user): if($user['nome'] != $_SESSION['usuario_nome']): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($user['nome']); ?></strong><div class="sub-info"><span>Equipe DOP</span></div></div>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #64748b;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-key"></i> Portaria</h3><a href="ativos.php" class="btn-icon" style="background:#64748b;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php if(empty($lista_emprestados)) echo "<p style='padding:20px; text-align:center; color:var(--text-muted);'>Nada emprestado.</p>"; ?>
                    <?php foreach($lista_emprestados as $emp): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($emp['identificador']); ?></strong><div class="sub-info"><span>Com: <b><?php echo htmlspecialchars($emp['responsavel']); ?></b></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #3b82f6;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-inbox"></i> Solicitações</h3><a href="solicitacoes.php" class="btn-icon" style="background:#3b82f6;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php if(empty($lista_solicitacoes)) echo "<p style='padding:20px; text-align:center; color:var(--text-muted);'>Nenhuma pendente.</p>"; ?>
                    <?php foreach($lista_solicitacoes as $s): ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($s['descricao']); ?></strong><div class="sub-info"><span>Por: <?php echo htmlspecialchars($s['solicitante']); ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #f97316;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-fire-extinguisher"></i> Extintores</h3><a href="extintores.php" class="btn-icon" style="background:#f97316;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php foreach($lista_extintores as $ex): 
                        $v = new DateTime($ex['data_vencimento']); $ds = $hoje->diff($v); $dias = $ds->invert ? -$ds->days : $ds->days;
                    ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($ex['local']); ?></strong><div class="sub-info"><span class="badge-status <?php echo ($dias < 0) ? 'badge-danger' : (($dias <= 90) ? 'badge-warning' : 'badge-success'); ?>">Vence: <?php echo date('d/m/Y', strtotime($ex['data_vencimento'])); ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stat-card" style="padding: 0; border-top: 5px solid #0ea5e9;">
                <div class="card-header-pro"><h3><i class="fa-solid fa-droplet"></i> Filtros</h3><a href="filtros.php" class="btn-icon" style="background:#0ea5e9;"><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="scroll-box" style="max-height: 280px;">
                    <?php foreach($lista_filtros as $fl): 
                        $v = new DateTime($fl['data_vencimento']); $ds = $hoje->diff($v); $dias = $ds->invert ? -$ds->days : $ds->days;
                    ?>
                        <div class="list-item-pro"><strong><?php echo htmlspecialchars($fl['local']); ?></strong><div class="sub-info"><span class="badge-status <?php echo ($dias < 0) ? 'badge-danger' : (($dias <= 90) ? 'badge-warning' : 'badge-success'); ?>">Troca: <?php echo date('d/m/Y', strtotime($fl['data_vencimento'])); ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>
    <script>
        function toggleMenu() { const s = document.getElementById('sidebar'); const c = document.getElementById('main-content'); const o = document.getElementById('overlay'); if (window.innerWidth > 768) { if(s) s.classList.toggle('closed'); if(c) c.classList.toggle('expanded'); } else { if(s) s.classList.toggle('open'); if(o) o.classList.toggle('active'); } }
        function abrirNotificacoes() { document.getElementById('dropdown-notif').classList.toggle('show'); }
        window.onclick = function(e) { if (!e.target.matches('.btn-notif') && !e.target.closest('.btn-notif') && !e.target.closest('.dropdown-notif')) { var d = document.getElementById("dropdown-notif"); if (d && d.classList.contains('show')) d.classList.remove('show'); } }
    </script>
</body>
</html>