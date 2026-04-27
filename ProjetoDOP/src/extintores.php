<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once '../config/database.php';

// MUDANÇA AQUI: Redireciona para ../index.php para evitar loops e permite Admins
if (!isset($_SESSION['usuario_id']) || ($_SESSION['is_admin'] != 1 && empty($_SESSION['acesso_extintores']))) {
    header("Location: ../index.php");
    exit;
}

// Processamento de Ações (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['novo_extintor'])) {
        $pdo->prepare("INSERT INTO extintores (local, tipo, data_vencimento, criado_por, data_criacao) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_POST['local'], $_POST['tipo'], $_POST['data_vencimento'], $_SESSION['usuario_nome'], date('d/m/Y H:i')]);
        header("Location: extintores.php");
        exit;
    }
    if (isset($_POST['renovar_carga'])) {
        $stmt = $pdo->prepare("SELECT local FROM extintores WHERE id = ?");
        $stmt->execute([$_POST['id_ext']]);
        $loc = $stmt->fetchColumn();
        
        $pdo->prepare("UPDATE extintores SET data_vencimento = ? WHERE id = ?")
            ->execute([$_POST['nova_data'], $_POST['id_ext']]);
            
        $pdo->prepare("INSERT INTO historico_manutencao (categoria, identificador, acao, usuario, data_hora) VALUES (?, ?, ?, ?, ?)")
            ->execute(['Extintor', $loc, 'Renovação de Carga', $_SESSION['usuario_nome'], date('d/m/Y H:i')]);
        header("Location: extintores.php");
        exit;
    }
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
        $pdo->prepare("DELETE FROM extintores WHERE id = ?")
            ->execute([$_POST['id_excluir']]);
        header("Location: extintores.php");
        exit;
    }
}

// Lógica de Triagem para as 3 Colunas
$itens = $pdo->query("SELECT * FROM extintores ORDER BY data_vencimento ASC")->fetchAll();

$vencidos = [];
$atencao = [];
$em_dia = [];
$hoje = new DateTime(date('Y-m-d'));
$notificacoes = [];

foreach ($itens as $i) {
    $venc = new DateTime($i['data_vencimento']);
    $dif = $hoje->diff($venc);
    $dias = $dif->invert ? -$dif->days : $dif->days;

    if ($dias < 0) {
        $vencidos[] = ['ext' => $i, 'dias' => abs($dias)];
        $notificacoes[] = ['cor' => '#b91c1c', 'bg' => '#fef2f2', 'icone' => 'fa-triangle-exclamation', 'titulo' => 'EXTINTOR VENCIDO!', 'texto' => "Local: {$i['local']} (Há ".abs($dias)." dias)"];
    } elseif ($dias <= 90) {
        $atencao[] = ['ext' => $i, 'dias' => $dias];
        $notificacoes[] = ['cor' => '#b45309', 'bg' => '#fffbeb', 'icone' => 'fa-fire-extinguisher', 'titulo' => 'Vencimento Próximo', 'texto' => "Local: {$i['local']} vence em $dias dias."];
    } else {
        $em_dia[] = ['ext' => $i, 'dias' => $dias];
    }
}
$tem_notificacao = count($notificacoes) > 0;

// Histórico Geral
$historico_geral = $pdo->query("SELECT * FROM historico_manutencao WHERE categoria = 'Extintor' ORDER BY id DESC LIMIT 150")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Extintores</title>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'extintores.php'; include 'menu_nav.php'; ?>

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
                <div class="notif-header">Avisos de Segurança</div>
                <?php if(!$tem_notificacao): ?>
                    <div class="notif-item" style="justify-content:center; color:var(--text-muted);"><i class="fa-solid fa-check-circle" style="color:var(--primary-green);"></i> Todos os extintores em dia!</div>
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

        <h1 style="color: var(--primary-green); margin-bottom: 20px; font-size: 1.6em;"><i class="fa-solid fa-fire-extinguisher"></i> Controle de Extintores</h1>

        <section class="stat-card" style="margin-bottom:20px; border-top: 4px solid var(--primary-green);">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" 
                 onclick="var f = document.getElementById('form-extintor'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'flex'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main);"><i class="fa-solid fa-plus-circle"></i> Cadastrar Novo Extintor</h3>
                <i class="fa-solid fa-chevron-down seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            
            <form id="form-extintor" method="POST" style="display:none; gap:15px; flex-wrap:wrap; margin-top:20px; padding-top:20px; border-top:1px dashed #cbd5e1;">
                <input type="text" name="local" placeholder="Localização (Ex: Bloco A - Piso 1)" required style="flex:2; min-width: 200px; padding: 10px;">
                <input type="text" name="tipo" placeholder="Tipo (PQS, CO2, Água)" required style="flex:1; min-width: 150px; padding: 10px;">
                
                <div style="flex:1; display:flex; align-items:center; gap:10px; background:#f8fafc; border:2px solid #e2e8f0; border-radius:10px; padding:0 10px; min-width:200px;">
                    <span style="font-size:0.8em; font-weight:bold; color:var(--text-muted);">Vencimento:</span>
                    <input type="date" name="data_vencimento" required style="border:none; background:transparent; padding:10px 5px; outline:none;">
                </div>
                <button type="submit" name="novo_extintor" class="btn" style="padding: 10px 20px;"><i class="fa-solid fa-check"></i> Salvar Extintor</button>
            </form>
        </section>

        <form id="form-imprimir" action="imprimir_extintores.php" method="POST" target="_blank" style="display:none;"></form>

        <div class="kanban-board">
            
            <div class="kanban-col" style="border-top: 4px solid var(--danger);">
                <h3>
                    <div style="display:flex; align-items:center; gap:8px; color: var(--danger);">
                        <i class="fa-solid fa-circle-xmark"></i> Vencidos (<?php echo count($vencidos); ?>)
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:0.75em; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" onchange="marcarTodos(this, 'check-vermelho')" style="accent-color:var(--danger);"> Todos
                        </label>
                        <button type="submit" form="form-imprimir" class="btn" style="background:#3b82f6; padding:4px 8px; font-size:0.7em;" title="Imprimir Selecionados">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </div>
                </h3>
                
                <div class="kanban-cards-area">
                    <?php if(empty($vencidos)) echo "<p style='text-align:center; color:#94a3b8; font-size:0.8em; margin-top:20px;'>Nenhum extintor vencido.</p>"; ?>
                    <?php foreach($vencidos as $item): ?>
                        <div class="os-card" style="border-left-color: var(--danger);">
                            <div class="os-header">
                                <div style="display:flex; align-items:flex-start; gap:8px;">
                                    <input type="checkbox" name="ext_imprimir[]" value="<?php echo $item['ext']['id']; ?>" class="check-imprimir check-vermelho" form="form-imprimir" style="width:16px; height:16px; accent-color:var(--danger); margin-top:2px;">
                                    <h4 class="os-title"><?php echo htmlspecialchars($item['ext']['local']); ?></h4>
                                </div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja excluir?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id_excluir" value="<?php echo $item['ext']['id']; ?>">
                                    <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:0;"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                            <div class="os-meta"><i class="fa-solid fa-fire"></i> Tipo: <?php echo htmlspecialchars($item['ext']['tipo']); ?></div>
                            <div class="os-meta" style="color:var(--danger); font-weight:bold;"><i class="fa-solid fa-triangle-exclamation"></i> Vencido há <?php echo $item['dias']; ?> dias</div>
                            <div class="os-actions" style="justify-content: flex-end;">
                                <button type="button" class="btn-action btn-action-urgente" onclick="document.getElementById('renovar-<?php echo $item['ext']['id']; ?>').style.display='flex'">
                                    Renovar Carga <i class="fa-solid fa-rotate"></i>
                                </button>
                            </div>
                            <form id="renovar-<?php echo $item['ext']['id']; ?>" method="POST" style="display:none; align-items:center; gap:10px; margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                                <input type="hidden" name="id_ext" value="<?php echo $item['ext']['id']; ?>">
                                <input type="date" name="nova_data" required style="width:100%; height:32px; padding:5px 10px; border-radius:6px; border:2px solid #cbd5e1;">
                                <button type="submit" name="renovar_carga" class="btn" style="height:32px; padding:0 15px; border-radius:6px;">OK</button>
                                <button type="button" class="btn" style="height:32px; padding:0 15px; background:#94a3b8; border-radius:6px;" onclick="this.parentElement.style.display='none'">X</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 4px solid var(--warning);">
                <h3>
                    <div style="display:flex; align-items:center; gap:8px; color: var(--warning);">
                        <i class="fa-solid fa-triangle-exclamation"></i> Vencendo (<?php echo count($atencao); ?>)
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:0.75em; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" onchange="marcarTodos(this, 'check-amarelo')" style="accent-color:var(--warning);"> Todos
                        </label>
                        <button type="submit" form="form-imprimir" class="btn" style="background:#3b82f6; padding:4px 8px; font-size:0.7em;" title="Imprimir Selecionados">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </div>
                </h3>
                <div class="kanban-cards-area">
                    <?php if(empty($atencao)) echo "<p style='text-align:center; color:#94a3b8; font-size:0.8em; margin-top:20px;'>Nenhum extintor vencendo em breve.</p>"; ?>
                    <?php foreach($atencao as $item): ?>
                        <div class="os-card" style="border-left-color: var(--warning);">
                            <div class="os-header">
                                <div style="display:flex; align-items:flex-start; gap:8px;">
                                    <input type="checkbox" name="ext_imprimir[]" value="<?php echo $item['ext']['id']; ?>" class="check-imprimir check-amarelo" form="form-imprimir" style="width:16px; height:16px; accent-color:var(--warning); margin-top:2px;">
                                    <h4 class="os-title"><?php echo htmlspecialchars($item['ext']['local']); ?></h4>
                                </div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja excluir?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id_excluir" value="<?php echo $item['ext']['id']; ?>">
                                    <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:0;"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                            <div class="os-meta"><i class="fa-solid fa-fire"></i> Tipo: <?php echo htmlspecialchars($item['ext']['tipo']); ?></div>
                            <div class="os-meta" style="color:var(--warning); font-weight:bold;"><i class="fa-regular fa-calendar-xmark"></i> Vence em <?php echo $item['dias']; ?> dias</div>
                            <div class="os-actions" style="justify-content: flex-end;">
                                <button type="button" class="btn-action btn-action-atencao" onclick="document.getElementById('renovar-<?php echo $item['ext']['id']; ?>').style.display='flex'">
                                    Agendar Recarga <i class="fa-solid fa-rotate"></i>
                                </button>
                            </div>
                            <form id="renovar-<?php echo $item['ext']['id']; ?>" method="POST" style="display:none; align-items:center; gap:10px; margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                                <input type="hidden" name="id_ext" value="<?php echo $item['ext']['id']; ?>">
                                <input type="date" name="nova_data" required style="width:100%; height:32px; padding:5px 10px; border-radius:6px; border:2px solid #cbd5e1;">
                                <button type="submit" name="renovar_carga" class="btn" style="height:32px; padding:0 15px; border-radius:6px;">OK</button>
                                <button type="button" class="btn" style="height:32px; padding:0 15px; background:#94a3b8; border-radius:6px;" onclick="this.parentElement.style.display='none'">X</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="kanban-col" style="border-top: 4px solid var(--primary-green);">
                <h3>
                    <div style="display:flex; align-items:center; gap:8px; color: var(--primary-green);">
                        <i class="fa-solid fa-check-circle"></i> Em Dia (<?php echo count($em_dia); ?>)
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="font-size:0.75em; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" onchange="marcarTodos(this, 'check-verde')" style="accent-color:var(--primary-green);"> Todos
                        </label>
                        <button type="submit" form="form-imprimir" class="btn" style="background:#3b82f6; padding:4px 8px; font-size:0.7em;" title="Imprimir Selecionados">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </div>
                </h3>
                <div class="kanban-cards-area">
                    <?php if(empty($em_dia)) echo "<p style='text-align:center; color:#94a3b8; font-size:0.8em; margin-top:20px;'>Nenhum extintor seguro.</p>"; ?>
                    <?php foreach($em_dia as $item): ?>
                        <div class="os-card" style="border-left-color: var(--primary-green);">
                            <div class="os-header">
                                <div style="display:flex; align-items:flex-start; gap:8px;">
                                    <input type="checkbox" name="ext_imprimir[]" value="<?php echo $item['ext']['id']; ?>" class="check-imprimir check-verde" form="form-imprimir" style="width:16px; height:16px; accent-color:var(--primary-green); margin-top:2px;">
                                    <h4 class="os-title"><?php echo htmlspecialchars($item['ext']['local']); ?></h4>
                                </div>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja excluir?')">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id_excluir" value="<?php echo $item['ext']['id']; ?>">
                                    <button type="submit" style="background:none; border:none; color:var(--text-muted); cursor:pointer; padding:0;"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                            <div class="os-meta"><i class="fa-solid fa-fire"></i> Tipo: <?php echo htmlspecialchars($item['ext']['tipo']); ?></div>
                            <div class="os-meta" style="color:var(--primary-green); font-weight:bold;"><i class="fa-regular fa-calendar-check"></i> Válido por <?php echo $item['dias']; ?> dias</div>
                            <div class="os-actions" style="justify-content: flex-end;">
                                <button type="button" class="btn-action btn-action-normal" onclick="document.getElementById('renovar-<?php echo $item['ext']['id']; ?>').style.display='flex'">
                                    Corrigir Data <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                            <form id="renovar-<?php echo $item['ext']['id']; ?>" method="POST" style="display:none; align-items:center; gap:10px; margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                                <input type="hidden" name="id_ext" value="<?php echo $item['ext']['id']; ?>">
                                <input type="date" name="nova_data" required style="width:100%; height:32px; padding:5px 10px; border-radius:6px; border:2px solid #cbd5e1;">
                                <button type="submit" name="renovar_carga" class="btn" style="height:32px; padding:0 15px; border-radius:6px;">OK</button>
                                <button type="button" class="btn" style="height:32px; padding:0 15px; background:#94a3b8; border-radius:6px;" onclick="this.parentElement.style.display='none'">X</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="stat-card" style="padding:0; overflow:hidden; border-top:4px solid #64748b; margin-top: 25px;">
            <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
                <h3 style="margin:0; font-size:1.1em;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico de Trocas e Manutenção</h3>
                <input type="text" id="buscaHistorico" placeholder="🔍 Pesquisar local, responsável..." onkeyup="filtrarHistorico()">
            </div>
            <div class="scroll-box" style="max-height: 350px;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <tr>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Data e Hora</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Localização do Extintor</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Ação Realizada</th>
                            <th style="padding:12px 15px; text-align:left; font-size:0.85em;">Responsável (Sistema)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($historico_geral)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">Nenhum registo encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach($historico_geral as $hg): ?>
                            <tr class="linha-historico">
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.85em; color:var(--text-muted);">
                                    <i class="fa-regular fa-calendar"></i> <?php echo $hg['data_hora']; ?>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.9em;">
                                    <strong><?php echo htmlspecialchars($hg['identificador']); ?></strong>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9;">
                                    <span style="background:#dcfce7; color:#15803d; padding:4px 8px; border-radius:4px; font-size:0.8em; font-weight:bold;"><?php echo $hg['acao']; ?></span>
                                </td>
                                <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; font-size:0.85em;">
                                    <i class="fa-solid fa-user-tag" style="color:#cbd5e1;"></i> <?php echo htmlspecialchars($hg['usuario']); ?>
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
        
        function marcarTodos(source, className) { 
            let checkboxes = document.querySelectorAll('.' + className); 
            checkboxes.forEach(cb => { 
                if(cb.closest('.os-card').style.display !== 'none') { 
                    cb.checked = source.checked; 
                } 
            }); 
        }
    </script>
</body>
</html>