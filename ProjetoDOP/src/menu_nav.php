<?php
if (!isset($pagina_atual)) { $pagina_atual = ''; }
$admin = !empty($_SESSION['is_admin']); // Variável de atalho para saber se é admin
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../logo.png" alt="SGOI Logo" style="max-width: 90px; height: auto; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
        <h2 style="margin: 0; font-size: 1.2em; font-weight: 800; letter-spacing: 1px;">SGOI</h2>
        <small style="color: #94a3b8; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px;">Operações</small>
        <div style="margin-top: 8px;"><span style="background: rgba(255,255,255,0.08); color: #cbd5e1; padding: 3px 8px; border-radius: 6px; font-size: 0.7em; font-weight: bold; border: 1px solid rgba(255,255,255,0.1);">Versão 1.0</span></div>
    </div>
    
    <div style="flex: 1; overflow-y: auto; padding-bottom: 20px;">
        
        <?php if($admin || !empty($_SESSION['acesso_dashboard'])): ?>
            <a href="painel.php" class="<?php echo ($pagina_atual == 'painel.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i> Painel Geral
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_solicitacoes'])): ?>
            <a href="solicitacoes.php" class="<?php echo ($pagina_atual == 'solicitacoes.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-inbox"></i> Solicitações
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_os'])): ?>
            <a href="os_kanban.php" class="<?php echo ($pagina_atual == 'os_kanban.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i> Quadro de O.S.
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_portaria'])): ?>
            <a href="ativos.php" class="<?php echo ($pagina_atual == 'ativos.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-door-open"></i> Portaria
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_estoque'])): ?>
            <a href="estoque.php" class="<?php echo ($pagina_atual == 'estoque.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-box-open"></i> Estoque
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_extintores'])): ?>
            <a href="extintores.php" class="<?php echo ($pagina_atual == 'extintores.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-fire-extinguisher"></i> Extintores
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_filtros'])): ?>
            <a href="filtros.php" class="<?php echo ($pagina_atual == 'filtros.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-droplet"></i> Filtros de Água
            </a>
        <?php endif; ?>

        <?php if($admin || !empty($_SESSION['acesso_metas'])): ?>
            <a href="metas.php" class="<?php echo ($pagina_atual == 'metas.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-bullseye"></i> Metas DOP
            </a>
        <?php endif; ?>
        
        <?php if($admin): ?>
            <div style="margin: 20px 15px 10px; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #64748b; font-size: 0.75em; font-weight: bold; text-transform: uppercase;">Administração</div>
            <a href="usuarios.php" class="<?php echo ($pagina_atual == 'usuarios.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users-gear"></i> Utilizadores
            </a>
        <?php endif; ?>
    </div>

    <div style="padding: 0 15px;">
        <a href="logout.php" style="color: #ef4444; background: rgba(239, 68, 68, 0.1);">
            <i class="fa-solid fa-right-from-bracket"></i> Sair do Sistema
        </a>
    </div>
</nav>