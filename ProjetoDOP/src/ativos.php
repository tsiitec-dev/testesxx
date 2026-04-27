<?php
session_start(); 
date_default_timezone_set('America/Sao_Paulo'); 
require_once '../config/database.php';

// Permite a entrada se for Admin (1) OU se tiver acesso à portaria
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_portaria']))) { 
    header("Location: ../index.php"); 
    exit; 
}

// Processamento de Ações (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Adicionar Novo Ativo (Corrigido: Agora entra como Guardado por padrão)
    if (isset($_POST['add_ativo'])) { 
        $pdo->prepare("INSERT INTO ativos (categoria, identificador, status, responsavel) VALUES (?, ?, 'Guardado', '-')")
            ->execute([$_POST['categoria'], $_POST['identificador']]); 
        header("Location: ativos.php"); 
        exit; 
    }
    
    // Emprestar ou Devolver (Corrigido: Blindado contra valores nulos)
    if (isset($_POST['registrar_movimentacao'])) {
        $id = $_POST['id']; 
        $stmt = $pdo->prepare("SELECT * FROM ativos WHERE id = ?"); 
        $stmt->execute([$id]); 
        $a = $stmt->fetch();
        
        if ($a) {
            $novo_status = ($_POST['status_atual'] === 'Guardado') ? 'Emprestado' : 'Guardado'; 
            $responsavel = ($novo_status === 'Emprestado') ? trim($_POST['responsavel'] ?? '') : '-'; 
            $acao = ($novo_status === 'Emprestado') ? 'Empréstimo' : 'Devolução'; 
            
            // Se for devolver, pega quem estava com o item. Se for vazio, assume "Sem Registo" para não travar a base de dados
            $resp_log = ($novo_status === 'Emprestado') ? $responsavel : (!empty($a['responsavel']) ? $a['responsavel'] : 'Sem Registo');
            
            // Atualiza o Status do Item
            $pdo->prepare("UPDATE ativos SET status = ?, responsavel = ? WHERE id = ?")
                ->execute([$novo_status, $responsavel, $id]);
                
            // Grava no Histórico
            $pdo->prepare("INSERT INTO historico_ativos (identificador, acao, responsavel, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
                ->execute([$a['identificador'], $acao, $resp_log, $_SESSION['usuario_nome'], date('d/m/Y H:i:s')]);
        } 
        header("Location: ativos.php"); 
        exit;
    }
    
    // Excluir Ativo
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') { 
        $pdo->prepare("DELETE FROM ativos WHERE id = ?")->execute([$_POST['id_excluir']]); 
        header("Location: ativos.php"); 
        exit; 
    }
}

// Consultas para as Colunas
$sql = "SELECT * FROM ativos WHERE categoria = ? ORDER BY CASE WHEN status = 'Emprestado' THEN 0 ELSE 1 END, identificador ASC";

$chaves = $pdo->prepare($sql); 
$chaves->execute(['Chave']); 
$chaves = $chaves->fetchAll();

$cartoes = $pdo->prepare($sql); 
$cartoes->execute(['Cartão']); 
$cartoes = $cartoes->fetchAll();

$guardas = $pdo->prepare($sql); 
$guardas->execute(['Guarda-chuva']); 
$guardas = $guardas->fetchAll();

// Lógica do Histórico
$filtro_hist = $_GET['filtro_historico'] ?? '';
if (!empty($filtro_hist)) { 
    $stmt = $pdo->prepare("SELECT * FROM historico_ativos WHERE identificador = ? ORDER BY id DESC LIMIT 100"); 
    $stmt->execute([$filtro_hist]); 
    $historico = $stmt->fetchAll(); 
} else { 
    $historico = $pdo->query("SELECT * FROM historico_ativos ORDER BY id DESC LIMIT 50")->fetchAll(); 
}

$ativos_historico = $pdo->query("SELECT DISTINCT identificador FROM historico_ativos ORDER BY identificador ASC")->fetchAll(PDO::FETCH_COLUMN);

// Notificações (Alerta após as 16:30 se houver pendências)
$notificacoes = []; 
if (date('H:i') >= '16:30' && $pdo->query("SELECT COUNT(*) FROM ativos WHERE status = 'Emprestado'")->fetchColumn() > 0) { 
    $notificacoes[] = ['cor' => '#ef4444', 'bg' => '#fee2e2', 'icone' => 'fa-clock-rotate-left', 'titulo' => 'Devolução Pendente', 'texto' => 'Atenção: Existem itens não devolvidos.']; 
}
$tem_notificacao = count($notificacoes) > 0;

// Função para Desenhar os Cartões
function renderizarAtivos($itens) {
    if(empty($itens)) return "<p style='color:#94a3b8; text-align:center;'>Nenhum item.</p>";
    
    foreach($itens as $i) {
        $isB = ($i['status'] == 'Emprestado'); 
        $cor = $isB ? 'var(--danger)' : 'var(--primary-green)'; 
        $bgRow = $isB ? '#fff5f5' : 'transparent'; 
        
        echo "<div class='cartao-ativo' style='display:flex;justify-content:space-between;align-items:center;padding:12px;border-bottom:1px solid #f1f5f9;background-color:$bgRow;'>
                <div>
                    <strong class='nome-ativo' style='font-size:1.1em; color:var(--text-main);'>" . htmlspecialchars($i['identificador']) . "</strong><br>
                    <small style='color:$cor; font-weight:bold;'>{$i['status']}</small> " . ($isB ? "<small style='color:#64748b;'>(" . htmlspecialchars($i['responsavel'] ?? 'Sem Registo') . ")</small>" : "") . "
                </div>
                <div style='display:flex; align-items:center; gap:8px;'>
                    <form method='POST' style='margin:0; display:flex; gap:8px;'>
                        <input type='hidden' name='id' value='{$i['id']}'>
                        <input type='hidden' name='status_atual' value='{$i['status']}'>";
                        
        if(!$isB) {
            echo "<input type='text' name='responsavel' placeholder='Nome' required style='width:100px; padding:6px 10px;'>
                  <button type='submit' name='registrar_movimentacao' class='btn' style='padding:6px 12px;'><i class='fa-solid fa-check'></i> OK</button>"; 
        } else {
            echo "<button type='submit' name='registrar_movimentacao' class='btn' style='background:#64748b; padding:6px 12px;'><i class='fa-solid fa-rotate-left'></i> Devolver</button>"; 
        }
        
        echo "      </form>
                    <form method='POST' style='margin:0;' onsubmit=\"return confirm('Excluir este item?');\">
                        <input type='hidden' name='acao' value='excluir'>
                        <input type='hidden' name='id_excluir' value='{$i['id']}'>
                        <button type='submit' style='background:none; border:none; color:var(--danger); cursor:pointer;'><i class='fa-solid fa-trash'></i></button>
                    </form>
                </div>
              </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Portaria</title>
    <style>
        .badge-online { background: #dcfce7; color: #15803d; border-radius: 20px; padding: 6px 15px; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .topbar-right { display: flex; align-items: center; gap: 15px; }

        .tipo-selector { display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap; } 
        .tipo-card { flex:1; min-width:100px; cursor:pointer; text-align:center; } 
        .tipo-card input { display:none; } 
        .card-content { padding:15px; background:#fff; border:2px solid #e5e7eb; border-radius:12px; display:flex; flex-direction:column; align-items:center; gap:8px; transition:0.3s; } 
        .card-content i { font-size:1.5em; color:var(--text-muted); } 
        .tipo-card input:checked + .card-content { border-color:#3b82f6; background:#eff6ff; } 
        .tipo-card input:checked + .card-content i, .tipo-card input:checked + .card-content span { color:#3b82f6; font-weight:700; }
    </style>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'ativos.php'; include 'menu_nav.php'; ?>
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
                <div class="notif-header">Avisos da Portaria</div>
                <?php if(!$tem_notificacao): ?>
                    <div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Tudo devolvido!</div>
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

        <h1 style="color: var(--primary-green); margin-bottom: 25px;"><i class="fa-solid fa-door-open"></i> Portaria Inteligente</h1>
        
        <section class="stat-card" style="margin-bottom:25px; border-top:4px solid var(--primary-green);">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="var f = document.getElementById('form-ativo'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'block'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main);"><i class="fa-solid fa-plus-circle"></i> Cadastrar Novo Item na Portaria</h3>
                <i class="fa-solid fa-chevron-down seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            <form id="form-ativo" method="POST" style="display:none; margin-top:20px; padding-top:20px; border-top:1px dashed #cbd5e1;">
                <div class="tipo-selector">
                    <label class="tipo-card"><input type="radio" name="categoria" value="Chave" checked><div class="card-content"><i class="fa-solid fa-key"></i><span>Chave</span></div></label>
                    <label class="tipo-card"><input type="radio" name="categoria" value="Cartão"><div class="card-content"><i class="fa-solid fa-id-card"></i><span>Cartão</span></div></label>
                    <label class="tipo-card"><input type="radio" name="categoria" value="Guarda-chuva"><div class="card-content"><i class="fa-solid fa-umbrella"></i><span>Guarda-chuva</span></div></label>
                </div>
                <div style="display:flex; gap:10px;">
                    <input type="text" name="identificador" placeholder="Identificador (Ex: Sala 10)" required style="flex:1; padding: 12px;">
                    <button type="submit" name="add_ativo" class="btn"><i class="fa-solid fa-check"></i> Salvar Item</button>
                </div>
            </form>
        </section>

        <input type="text" id="buscaAtivos" placeholder="🔍 Pesquisar itens na portaria..." style="width:100%; margin-bottom:25px; padding:15px; border-radius:10px; border:2px solid #cbd5e1; font-size:1.05em; outline:none;" onkeyup="filtrarCards('buscaAtivos', 'cartao-ativo')">

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-bottom: 40px;">
            <div class="stat-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; border-top: 4px solid var(--primary-green);">
                <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-key" style="color:var(--primary-green);"></i><h3 style="margin:0; font-size:1.1em;">Chaves</h3>
                </div>
                <div class="scroll-box" style="padding:10px 20px; max-height:500px;"><?php renderizarAtivos($chaves); ?></div>
            </div>
            
            <div class="stat-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; border-top: 4px solid #3b82f6;">
                <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-id-card" style="color:#3b82f6;"></i><h3 style="margin:0; font-size:1.1em;">Cartões</h3>
                </div>
                <div class="scroll-box" style="padding:10px 20px; max-height:500px;"><?php renderizarAtivos($cartoes); ?></div>
            </div>
            
            <div class="stat-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; border-top: 4px solid var(--warning);">
                <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-umbrella" style="color:var(--warning);"></i><h3 style="margin:0; font-size:1.1em;">Guarda-chuvas</h3>
                </div>
                <div class="scroll-box" style="padding:10px 20px; max-height:500px;"><?php renderizarAtivos($guardas); ?></div>
            </div>
        </div>

        <div class="stat-card" style="border-top: 4px solid #64748b; padding: 0; overflow: hidden;">
            <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin: 0; font-size:1.1em; display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico</h3>
                <form method="GET" style="margin:0;">
                    <select name="filtro_historico" style="padding: 8px 12px; font-size: 0.9em; border-radius: 8px; border: 2px solid #cbd5e1; outline: none;" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <?php foreach($ativos_historico as $ah): ?>
                            <option value="<?php echo htmlspecialchars($ah); ?>" <?php echo $filtro_hist === $ah ? 'selected' : ''; ?>><?php echo htmlspecialchars($ah); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="scroll-box" style="max-height:400px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <tr><th style="padding:15px; text-align:left;">Data/Hora</th><th style="padding:15px; text-align:left;">Item</th><th style="padding:15px; text-align:left;">Ação</th><th style="padding:15px; text-align:left;">Pessoa</th><th style="padding:15px; text-align:left;">Porteiro</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historico)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:20px;">Nenhuma movimentação.</td></tr>
                        <?php else: foreach($historico as $h): 
                            $bc = ($h['acao'] == 'Empréstimo') ? '#b45309' : '#15803d'; 
                            $bg_c = ($h['acao'] == 'Empréstimo') ? '#fef3c7' : '#dcfce7'; 
                        ?>
                            <tr>
                                <td style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.9em; color:var(--text-muted);"><i class="fa-regular fa-calendar"></i> <?php echo $h['data_hora']; ?></td>
                                <td style="padding:15px; border-bottom:1px solid #f1f5f9;"><strong><?php echo htmlspecialchars($h['identificador']); ?></strong></td>
                                <td style="padding:15px; border-bottom:1px solid #f1f5f9;"><span style="background:<?php echo $bg_c; ?>; color:<?php echo $bc; ?>; padding:4px 8px; border-radius:4px; font-size:0.85em; font-weight:bold;"><?php echo $h['acao']; ?></span></td>
                                <td style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.95em;"><strong><?php echo htmlspecialchars($h['responsavel'] ?? 'Sem Registo'); ?></strong></td>
                                <td style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.9em;"><i class="fa-solid fa-user-shield" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($h['usuario']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        function toggleMenu() { const s = document.getElementById('sidebar'); const c = document.getElementById('main-content'); const o = document.getElementById('overlay'); if (window.innerWidth > 768) { if(s) s.classList.toggle('closed'); if(c) c.classList.toggle('expanded'); } else { if(s) s.classList.toggle('open'); if(o) o.classList.toggle('active'); } }
        function abrirNotificacoes() { document.getElementById('dropdown-notif').classList.toggle('show'); }
        window.onclick = function(e) { if(!e.target.matches('.btn-notif') && !e.target.closest('.btn-notif') && !e.target.closest('.dropdown-notif')) { var d = document.getElementById("dropdown-notif"); if (d && d.classList.contains('show')) d.classList.remove('show'); } }
        function filtrarCards(id, cl) { let t = document.getElementById(id).value.toLowerCase(); let c = document.querySelectorAll('.' + cl); c.forEach(function(card) { let n = card.querySelector('.nome-ativo').innerText.toLowerCase(); card.style.display = n.includes(t) ? 'flex' : 'none'; }); }
    </script>
</body>
</html>