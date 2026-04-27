<?php
date_default_timezone_set('America/Sao_Paulo'); 
require_once '../config/database.php';

// Cria a tabela de solicitações automaticamente caso ela ainda não exista no banco
$pdo->exec("CREATE TABLE IF NOT EXISTS solicitacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    descricao TEXT, 
    local TEXT, 
    solicitante TEXT, 
    status TEXT, 
    data_criacao TEXT
)");

$status_msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $desc = trim($_POST['descricao']);
    $loc = trim($_POST['local']);
    $sol = trim($_POST['solicitante']);
    
    if(!empty($desc) && !empty($loc) && !empty($sol)) {
        try {
            // Agora insere na tabela nova com o status 'Aguardando'
            $pdo->prepare("INSERT INTO solicitacoes (descricao, local, solicitante, status, data_criacao) VALUES (?, ?, ?, 'Aguardando', ?)")->execute([$desc, $loc, $sol, date('d/m/Y H:i')]);
            $status_msg = "sucesso";
        } catch (Exception $e) {
            $status_msg = "erro";
        }
    } else {
        $status_msg = "vazio";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Nova Solicitação</title>
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); margin: 0; padding: 20px; box-sizing: border-box; } 
        .chamado-card { background: var(--white); padding: 40px; border-radius: 24px; width: 100%; max-width: 550px; box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1); border-top: 6px solid #3b82f6; } 
        .header-chamado { text-align: center; margin-bottom: 30px; }
        .header-chamado img { height: 80px; margin-bottom: 15px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .input-group { margin-bottom: 20px; text-align: left; } 
        .input-group label { display: block; font-size: 0.9em; font-weight: bold; color: var(--text-main); margin-bottom: 8px; }
        .input-group input, .input-group textarea { width: 100%; padding: 14px; font-size: 1rem; border: 2px solid #cbd5e1; border-radius: 10px; background: #f8fafc; outline: none; transition: 0.3s; font-family: inherit; resize: vertical; }
        .input-group input:focus, .input-group textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); background: #fff; }
        .btn-enviar { width: 100%; padding: 16px; font-size: 1.1em; background: #3b82f6; color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-enviar:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3); background: #2563eb; }
        .alert-box { padding: 20px; border-radius: 12px; margin-bottom: 25px; text-align: center; font-weight: bold; }
        .alert-sucesso { background: #dcfce7; color: #15803d; border: 2px solid #22c55e; }
        .alert-erro { background: #fee2e2; color: #b91c1c; border: 2px solid #ef4444; }
        .btn-voltar { display: inline-block; margin-top: 20px; color: var(--text-muted); text-decoration: none; font-weight: bold; font-size: 0.9em; transition: 0.2s; }
        .btn-voltar:hover { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="chamado-card">
        <div class="header-chamado">
            <img src="../logo.png" alt="SGOI Logo">
            <h1 style="color:var(--text-main); margin:0; font-size: 1.8em;">Central de Solicitações</h1>
            <p style="color:var(--text-muted); margin-top: 5px;">Departamento de Operações</p>
        </div>
        
        <?php if($status_msg == "sucesso"): ?>
            <div class="alert-box alert-sucesso">
                <i class="fa-solid fa-circle-check" style="font-size: 2em; display:block; margin-bottom:10px;"></i>
                Solicitação enviada com sucesso!<br>
                <span style="font-size:0.85em; font-weight:normal; color:#166534; margin-top:5px; display:block;">Nossa equipe já recebeu a notificação no painel.</span>
            </div>
            <div style="text-align:center;">
                <a href="abrir_chamado.php" class="btn-enviar" style="background:#e2e8f0; color:#475569; text-decoration:none;">Abrir outra solicitação</a>
            </div>
        <?php else: ?>
            
            <?php if($status_msg == "erro" || $status_msg == "vazio"): ?>
                <div class="alert-box alert-erro"><i class="fa-solid fa-triangle-exclamation"></i> Preencha todos os campos corretamente!</div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="input-group">
                    <label>Seu Nome Completo:</label>
                    <input type="text" name="solicitante" placeholder="Ex: João Silva" required>
                </div>
                <div class="input-group">
                    <label>Localização / Sala:</label>
                    <input type="text" name="local" placeholder="Ex: Bloco A - Sala 15" required>
                </div>
                <div class="input-group">
                    <label>O que precisa ser feito?</label>
                    <textarea name="descricao" rows="4" placeholder="Descreva o problema ou solicitação em detalhes..." required></textarea>
                </div>
                <button type="submit" class="btn-enviar"><i class="fa-solid fa-paper-plane"></i> ENVIAR SOLICITAÇÃO</button>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center;">
            <a href="../index.php" class="btn-voltar"><i class="fa-solid fa-arrow-left"></i> Voltar para Login Restrito</a>
        </div>
    </div>
</body>
</html>