<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// MUDANÇA AQUI: Permite a entrada se for Admin (1) OU se tiver acesso ao estoque
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_estoque']))) { 
    header("Location: ../index.php"); 
    exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar Novo Produto
    if (isset($_POST['add_produto'])) { 
        $p = trim($_POST['produto']); $q = intval($_POST['quantidade']); $u = $_POST['unidade']; 
        $pdo->prepare("INSERT INTO estoque (produto, quantidade, unidade) VALUES (?, ?, ?)")->execute([$p, $q, $u]); 
        $pdo->prepare("INSERT INTO historico_estoque (produto, acao, quantidade, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")->execute([$p, "Cadastro Inicial (+$q)", $q, $_SESSION['usuario_nome'], date('d/m/Y H:i:s')]); 
        header("Location: estoque.php"); exit; 
    }
    
    // Nova Lógica: Movimentar Estoque (+ ou -)
    if (isset($_POST['movimentar_estoque'])) {
        $id = (int)$_POST['id_produto'];
        $mov_qtd = (int)$_POST['qtd_movimento'];
        $tipo = $_POST['tipo_movimento']; // 'entrada' ou 'saida'

        if ($mov_qtd > 0) {
            $stmt = $pdo->prepare("SELECT * FROM estoque WHERE id = ?");
            $stmt->execute([$id]);
            $prod = $stmt->fetch();

            if ($prod) {
                if ($tipo === 'entrada') {
                    $nova_qtd = $prod['quantidade'] + $mov_qtd;
                    $acao_log = "Entrada (+$mov_qtd)";
                    $qtd_log = $mov_qtd;
                } else {
                    // Impede que o estoque fique negativo
                    $nova_qtd = max(0, $prod['quantidade'] - $mov_qtd);
                    $removido = $prod['quantidade'] - $nova_qtd;
                    
                    if ($removido == 0) { header("Location: estoque.php"); exit; } // Se já era 0 e tentou tirar, ignora
                    
                    $acao_log = "Saída (-$removido)";
                    $qtd_log = $removido;
                }

                $pdo->prepare("UPDATE estoque SET quantidade = ? WHERE id = ?")->execute([$nova_qtd, $id]);
                $pdo->prepare("INSERT INTO historico_estoque (produto, acao, quantidade, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$prod['produto'], $acao_log, $qtd_log, $_SESSION['usuario_nome'], date('d/m/Y H:i:s')]);
            }
        }
        header("Location: estoque.php"); exit;
    }

    // Excluir Produto
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
        $id = $_POST['id_excluir']; $stmt = $pdo->prepare("SELECT * FROM estoque WHERE id = ?"); $stmt->execute([$id]); $prod = $stmt->fetch(); 
        if ($prod) { 
            $pdo->prepare("DELETE FROM estoque WHERE id = ?")->execute([$id]); 
            $pdo->prepare("INSERT INTO historico_estoque (produto, acao, quantidade, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")->execute([$prod['produto'], 'Exclusão de Item', $prod['quantidade'], $_SESSION['usuario_nome'], date('d/m/Y H:i:s')]); 
        } 
        header("Location: estoque.php"); exit; 
    }
}

$produtos_ok = $pdo->query("SELECT * FROM estoque WHERE quantidade > 3 ORDER BY produto ASC")->fetchAll();
$produtos_acabando = $pdo->query("SELECT * FROM estoque WHERE quantidade > 0 AND quantidade <= 3 ORDER BY produto ASC")->fetchAll();
$produtos_falta = $pdo->query("SELECT * FROM estoque WHERE quantidade = 0 ORDER BY produto ASC")->fetchAll();

$filtro_prod = $_GET['filtro_produto'] ?? '';
if (!empty($filtro_prod)) { $stmt_hist = $pdo->prepare("SELECT * FROM historico_estoque WHERE produto = ? ORDER BY id DESC LIMIT 100"); $stmt_hist->execute([$filtro_prod]); $historico = $stmt_hist->fetchAll(); } 
else { $historico = $pdo->query("SELECT * FROM historico_estoque ORDER BY id DESC LIMIT 50")->fetchAll(); }
$produtos_historico = $pdo->query("SELECT DISTINCT produto FROM historico_estoque ORDER BY produto ASC")->fetchAll(PDO::FETCH_COLUMN);

$qtd_ok = count($produtos_ok); $qtd_acabando = count($produtos_acabando); $qtd_falta = count($produtos_falta);
$notificacoes = []; if (($qtd_acabando + $qtd_falta) > 0) { $notificacoes[] = ['cor' => '#3b82f6', 'bg' => '#eff6ff', 'icone' => 'fa-box-open', 'titulo' => 'Estoque Requer Atenção', 'texto' => "Você tem ".($qtd_acabando + $qtd_falta)." item(ns) acabando ou em falta!"]; }
$tem_notificacao = count($notificacoes) > 0;

// Função Refatorada com Botões (+ / -)
function renderizarItemEstoque($p, $cor_tema, $bg_badge, $cor_badge) {
    echo "<div class='cartao-estoque' style='background:#ffffff; border: 1px solid #e2e8f0; border-left: 4px solid $cor_tema; padding:15px; border-radius:12px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition:0.2s;'>
            <div style='display:flex; justify-content:space-between; align-items:flex-start;'>
                <div>
                    <span style='background:$bg_badge; color:$cor_badge; padding:3px 8px; border-radius:12px; font-size:0.7em; font-weight:800; text-transform:uppercase;'><i class='fa-solid fa-box'></i> ".htmlspecialchars($p['unidade'])."</span>
                    <strong class='nome-produto' style='display:block; color:var(--text-main); margin-top:8px; font-size:1.05em;'>" . htmlspecialchars($p['produto']) . "</strong>
                    <div style='font-size:2.2em; font-weight:900; color:$cor_tema; margin-top:5px; line-height:1;'>{$p['quantidade']}</div>
                </div>
                <form method='POST' style='margin:0; display:inline;' onsubmit=\"return confirm('ATENÇÃO: Deseja excluir este produto permanentemente do sistema?')\">
                    <input type='hidden' name='acao' value='excluir'><input type='hidden' name='id_excluir' value='{$p['id']}'>
                    <button type='submit' style='background:none; border:none; color:#cbd5e1; padding:4px; cursor:pointer; font-size:1.1em; transition:0.2s;' onmouseover=\"this.style.color='var(--danger)'\" onmouseout=\"this.style.color='#cbd5e1'\" title='Excluir Produto'><i class='fa-solid fa-trash'></i></button>
                </form>
            </div>

            <div style='margin-top:15px; padding-top:15px; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between; align-items:center;'>
                <span style='font-size:0.8em; color:var(--text-muted); font-weight:bold;'>Movimentar:</span>
                <form method='POST' style='margin:0; display:flex; align-items:center; gap:6px; background:#f8fafc; padding:5px; border-radius:8px; border:1px solid #e2e8f0;'>
                    <input type='hidden' name='id_produto' value='{$p['id']}'>
                    <input type='hidden' name='movimentar_estoque' value='1'>
                    <input type='number' name='qtd_movimento' min='1' placeholder='Qtd' required style='width:65px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px; font-weight:bold; font-size:0.95em; outline:none; color:var(--text-main);'>
                    
                    <button type='submit' name='tipo_movimento' value='saida' style='height:34px; width:34px; border-radius:6px; border:none; background:#ef4444; color:white; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;' onmouseover=\"this.style.background='#dc2626'\" onmouseout=\"this.style.background='#ef4444'\" title='Registrar Saída'>
                        <i class='fa-solid fa-minus'></i>
                    </button>
                    
                    <button type='submit' name='tipo_movimento' value='entrada' style='height:34px; width:34px; border-radius:6px; border:none; background:#10b981; color:white; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;' onmouseover=\"this.style.background='#059669'\" onmouseout=\"this.style.background='#10b981'\" title='Adicionar Entrada'>
                        <i class='fa-solid fa-plus'></i>
                    </button>
                </form>
            </div>
          </div>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><title>SGOI - Estoque</title>
    <style>
        /* Estilos Exclusivos do Estoque (Para inputs numéricos e filtro) */
        .select-filtro { padding: 8px 12px; font-size: 0.9em; border-radius: 8px; border: 2px solid #cbd5e1; color: var(--text-main); background-color: #fff; width: auto; min-width: 200px; outline: none;}
        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'estoque.php'; include 'menu_nav.php'; ?>
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
                <div class="notif-header">Avisos do Estoque</div>
                <?php if(!$tem_notificacao): ?>
                    <div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Estoque abastecido!</div>
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

        <h1 style="color: var(--primary-green); margin-bottom: 25px;"><i class="fa-solid fa-box-open"></i> Controle de Insumos</h1>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px;">
            <div class="stat-card" style="border-top: 5px solid var(--primary-green); text-align:center;"><i class="fa-solid fa-check-circle" style="font-size: 2em; color: #cbd5e1; margin-bottom: 10px;"></i><small style="display:block; font-weight:bold; color:var(--text-muted);">ESTOQUE OK</small><h2 style="font-size:3.5em; margin:10px 0; color:var(--primary-green);"><?php echo $qtd_ok; ?></h2></div>
            <div class="stat-card" style="border-top: 5px solid var(--warning); text-align:center;"><i class="fa-solid fa-triangle-exclamation" style="font-size: 2em; color: #cbd5e1; margin-bottom: 10px;"></i><small style="display:block; font-weight:bold; color:var(--text-muted);">QUASE ACABANDO</small><h2 style="font-size:3.5em; margin:10px 0; color:var(--warning);"><?php echo $qtd_acabando; ?></h2></div>
            <div class="stat-card" style="border-top: 5px solid var(--danger); text-align:center;"><i class="fa-solid fa-circle-xmark" style="font-size: 2em; color: #cbd5e1; margin-bottom: 10px;"></i><small style="display:block; font-weight:bold; color:var(--text-muted);">EM FALTA</small><h2 style="font-size:3.5em; margin:10px 0; color:var(--danger);"><?php echo $qtd_falta; ?></h2></div>
        </div>

        <section class="stat-card" style="margin-bottom:25px; border-top: 4px solid var(--primary-green);">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="var f = document.getElementById('form-estoque'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'flex'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main);"><i class="fa-solid fa-plus-circle"></i> Cadastrar Novo Insumo</h3>
                <i class="fa-solid fa-chevron-down seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            <form id="form-estoque" method="POST" style="display:none; gap:15px; flex-wrap: wrap; margin-top:20px; padding-top:20px; border-top:1px dashed #cbd5e1;">
                <input type="text" name="produto" placeholder="Nome do Produto" required style="flex:2; min-width:200px; padding:12px; border-radius:8px; border:2px solid #e2e8f0;">
                <input type="number" name="quantidade" placeholder="Qtd Inicial" required style="flex:0.5; min-width:80px; padding:12px; border-radius:8px; border:2px solid #e2e8f0;">
                <select name="unidade" required style="flex:0.8; min-width:150px; padding:12px; border-radius:8px; border:2px solid #e2e8f0; font-weight:bold;">
                    <option value="" disabled selected>Unidade...</option>
                    <option value="Unidades">Unidades (UN)</option><option value="Litros">Litros (L)</option><option value="Caixas">Caixas (CX)</option><option value="Pacotes">Pacotes (PCT)</option><option value="Fardos">Fardos</option>
                </select>
                <button type="submit" name="add_produto" class="btn" style="padding:12px; font-weight:800;"><i class="fa-solid fa-check"></i> Lançar</button>
            </form>
        </section>
        
        <input type="text" id="buscaEstoque" placeholder="🔍 Pesquisar em todo o estoque..." style="width:100%; margin-bottom:25px; padding:15px; border-radius:10px; border:2px solid #cbd5e1; font-size:1.05em; outline:none;" onkeyup="filtrarCards('buscaEstoque', 'cartao-estoque')">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <div class="stat-card" style="border-top: 4px solid var(--primary-green); padding: 0; overflow: hidden; display: flex; flex-direction: column;"><div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-check-circle" style="color:var(--primary-green); font-size:1.2em;"></i><h3 style="margin: 0; font-size:1.1em; color: var(--text-main);">Tudo Certo</h3></div><div class="scroll-box" style="padding: 15px; max-height:500px;"><?php if(empty($produtos_ok)) echo "<p style='text-align:center; color:var(--text-muted); font-size:0.9em;'>Nenhum produto folgado.</p>"; foreach($produtos_ok as $p) renderizarItemEstoque($p, 'var(--primary-green)', '#dcfce7', '#15803d'); ?></div></div>
            <div class="stat-card" style="border-top: 4px solid var(--warning); padding: 0; overflow: hidden; display: flex; flex-direction: column;"><div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning); font-size:1.2em;"></i><h3 style="margin: 0; font-size:1.1em; color: var(--text-main);">Atenção (<= 3)</h3></div><div class="scroll-box" style="padding: 15px; max-height:500px;"><?php if(empty($produtos_acabando)) echo "<p style='text-align:center; color:var(--text-muted); font-size:0.9em;'>Nada acabando.</p>"; foreach($produtos_acabando as $p) renderizarItemEstoque($p, 'var(--warning)', '#fef3c7', '#b45309'); ?></div></div>
            <div class="stat-card" style="border-top: 4px solid var(--danger); padding: 0; overflow: hidden; display: flex; flex-direction: column;"><div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-xmark" style="color:var(--danger); font-size:1.2em;"></i><h3 style="margin: 0; font-size:1.1em; color: var(--text-main);">Em Falta (0)</h3></div><div class="scroll-box" style="padding: 15px; max-height:500px;"><?php if(empty($produtos_falta)) echo "<p style='text-align:center; color:var(--text-muted); font-size:0.9em;'>Nenhum item zerado!</p>"; foreach($produtos_falta as $p) renderizarItemEstoque($p, 'var(--danger)', '#fee2e2', '#b91c1c'); ?></div></div>
        </div>

        <div class="stat-card" style="border-top: 4px solid #64748b; padding: 0; overflow: hidden;">
            <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin: 0; font-size:1.1em; color: var(--text-main); display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de Movimentações</h3>
                <form method="GET" style="margin:0;"><select name="filtro_produto" class="select-filtro" onchange="this.form.submit()"><option value="">Exibir Todos</option><?php foreach($produtos_historico as $ph): ?><option value="<?php echo htmlspecialchars($ph); ?>" <?php echo $filtro_prod === $ph ? 'selected' : ''; ?>><?php echo htmlspecialchars($ph); ?></option><?php endforeach; ?></select></form>
            </div>
            <div class="scroll-box" style="max-height:400px;">
                <table style="width:100%; border-collapse:collapse;"><thead style="position: sticky; top: 0; background: white; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.05);"><tr><th style="padding:15px; text-align:left;">Data/Hora</th><th style="padding:15px; text-align:left;">Produto</th><th style="padding:15px; text-align:left;">Ação</th><th style="padding:15px; text-align:left;">Responsável</th></tr></thead>
                    <tbody>
                        <?php if(empty($historico)): ?><tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">Sem movimentos.</td></tr>
                        <?php else: foreach($historico as $h): $bc = strpos($h['acao'], 'Saída')!==false||strpos($h['acao'], 'Exclusão')!==false?'#b91c1c':(strpos($h['acao'], 'Entrada')!==false||strpos($h['acao'], 'Cadastro')!==false?'#15803d':'#64748b'); ?>
                        <tr><td style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.9em; color:var(--text-muted);"><i class="fa-regular fa-calendar"></i> <?php echo $h['data_hora']; ?></td><td style="padding:15px; border-bottom:1px solid #f1f5f9;"><strong><?php echo htmlspecialchars($h['produto']); ?></strong></td><td style="padding:15px; border-bottom:1px solid #f1f5f9;"><span style="background:<?php echo $bc; ?>15; color:<?php echo $bc; ?>; padding:4px 8px; border-radius:4px; font-size:0.85em; font-weight:bold;"><?php echo $h['acao']; ?></span></td><td style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.9em;"><i class="fa-solid fa-user-tag" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($h['usuario']); ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script>
        function toggleMenu() { const s = document.getElementById('sidebar'); const c = document.getElementById('main-content'); const o = document.getElementById('overlay'); if (window.innerWidth > 768) { if(s) s.classList.toggle('closed'); if(c) c.classList.toggle('expanded'); } else { if(s) s.classList.toggle('open'); if(o) o.classList.toggle('active'); } }
        function abrirNotificacoes() { document.getElementById('dropdown-notif').classList.toggle('show'); }
        window.onclick = function(event) { if (!event.target.matches('.btn-notif') && !event.target.closest('.btn-notif') && !event.target.closest('.dropdown-notif')) { var d = document.getElementById("dropdown-notif"); if (d && d.classList.contains('show')) d.classList.remove('show'); } }
        function filtrarCards(inputId, cardClass) { let t = document.getElementById(inputId).value.toLowerCase(); let cards = document.querySelectorAll('.' + cardClass); cards.forEach(function(c) { let n = c.querySelector('.nome-produto').innerText.toLowerCase(); c.style.display = n.includes(t) ? '' : 'none'; }); }
    </script>
</body>
</html>