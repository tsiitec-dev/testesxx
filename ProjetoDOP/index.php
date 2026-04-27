<?php
session_start();
// O index usa o database.php direto para evitar loops de redirecionamento
require_once 'config/database.php'; 
$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($u && password_verify($_POST['senha'], $u['senha'])) {
            $_SESSION['usuario_id'] = $u['id'];
            $_SESSION['usuario_nome'] = $u['nome'];
            $_SESSION['is_admin'] = (int)$u['is_admin'];
            $_SESSION['acesso_dashboard'] = (int)($u['acesso_dashboard'] ?? 0);
            $_SESSION['acesso_portaria'] = (int)($u['acesso_portaria'] ?? 0);
            $_SESSION['acesso_os'] = (int)($u['acesso_os'] ?? 0);
            $_SESSION['acesso_estoque'] = (int)($u['acesso_estoque'] ?? 0);
            $_SESSION['acesso_metas'] = (int)($u['acesso_metas'] ?? 0);
            $_SESSION['acesso_extintores'] = (int)($u['acesso_extintores'] ?? 0);
            $_SESSION['acesso_filtros'] = (int)($u['acesso_filtros'] ?? 0);
            
            // ADICIONADO: Guarda a permissão de solicitações na sessão
            $_SESSION['acesso_solicitacoes'] = (int)($u['acesso_solicitacoes'] ?? 0);
            
            // Redirecionamento Inteligente (Blindado)
            if ($_SESSION['is_admin'] === 1 || $_SESSION['acesso_dashboard'] === 1) { header("Location: src/painel.php"); exit; }
            elseif ($_SESSION['acesso_solicitacoes'] === 1) { header("Location: src/solicitacoes.php"); exit; }
            elseif ($_SESSION['acesso_extintores'] === 1) { header("Location: src/extintores.php"); exit; }
            elseif ($_SESSION['acesso_filtros'] === 1) { header("Location: src/filtros.php"); exit; }
            elseif ($_SESSION['acesso_os'] === 1) { header("Location: src/os_kanban.php"); exit; }
            elseif ($_SESSION['acesso_portaria'] === 1) { header("Location: src/ativos.php"); exit; }
            elseif ($_SESSION['acesso_estoque'] === 1) { header("Location: src/estoque.php"); exit; }
            elseif ($_SESSION['acesso_metas'] === 1) { header("Location: src/metas.php"); exit; }
            else {
                // Se o usuário não tiver NENHUMA permissão, mostra um erro em vez de loop infinito
                $erro = "Sua conta não possui permissões ativas. Peça ao administrador para liberar seus acessos.";
                session_unset();
                session_destroy();
            }
        } else {
            $erro = "E-mail ou senha incorretos. Tente novamente!";
        }
    } catch (PDOException $e) {
        // PROTEÇÃO: Em vez de ecrã fatal, mostra um aviso amigável
        $erro = "Erro no sistema: Não foi possível acessar a base de dados. Verifique a configuração.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGOI - Login de Acesso</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ESTILOS EXCLUSIVOS DO LOGIN (Blindado contra falhas) */
        :root {
            --green-main: #059669;
            --green-dark: #064e3b;
            --green-light: #10b981;
            --text-dark: #0f172a;
            --text-gray: #64748b;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--green-main) 0%, #022c22 100%);
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.6s ease-out;
        }

        .login-card {
            background: #ffffff;
            padding: 45px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-area {
            margin-bottom: 25px;
        }
        
        .img-logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
            object-fit: contain;
        }

        h1 { color: var(--text-dark); font-size: 1.8rem; font-weight: 800; margin-bottom: 5px; letter-spacing: -0.5px; }
        p.subtitle { color: var(--text-gray); font-size: 0.95rem; margin-bottom: 5px; font-weight: 500; }
        
        .badge-versao {
            display: inline-block;
            background: #ecfdf5;
            color: var(--green-main);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-bottom: 25px;
            border: 1px solid #a7f3d0;
        }

        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group i.icone-input { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; transition: 0.3s; }
        .input-group input { width: 100%; padding: 15px 15px 15px 45px; font-size: 1rem; border: 2px solid #e2e8f0; border-radius: 12px; background: #f8fafc; color: var(--text-dark); outline: none; transition: all 0.3s ease; font-family: inherit; }
        .input-group input:focus { border-color: var(--green-light); background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .input-group input:focus + i.icone-input { color: var(--green-main); }
        
        .btn-eye { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; padding: 5px; transition: 0.2s; }
        .btn-eye:hover { color: var(--text-dark); }

        .btn-login { width: 100%; padding: 16px; font-size: 1.05rem; font-weight: 700; color: #ffffff; background: var(--green-main); border: none; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 10px; }
        .btn-login:hover { background: var(--green-dark); transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.3); }

        .btn-chamado { display: block; width: 100%; padding: 14px; margin-top: 15px; font-size: 0.95rem; font-weight: 700; color: var(--text-dark); background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; text-decoration: none; transition: all 0.2s ease; }
        .btn-chamado:hover { background: #f1f5f9; border-color: #cbd5e1; }

        .erro-alerta { background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 8px; text-align: left; }

        .footer-text { margin-top: 35px; font-size: 0.8rem; color: var(--text-gray); line-height: 1.5; }
        .footer-text a { color: var(--green-main); font-weight: 700; text-decoration: none; }
        .footer-text a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="logo-area">
                <img src="logo.png" alt="SGOI Logo" class="img-logo">
                <h1>SGOI</h1>
                <p class="subtitle">Gestão Operacional Integrada</p>
                <span class="badge-versao">Versão 1.0</span>
            </div>

            <?php if($erro): ?>
                <div class="erro-alerta">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <div class="input-group">
                    <input type="email" name="email" placeholder="Seu E-mail Corporativo" required autocomplete="email">
                    <i class="fa-solid fa-envelope icone-input"></i>
                </div>
                
                <div class="input-group">
                    <input type="password" id="campo-senha" name="senha" placeholder="Sua Senha" required autocomplete="current-password">
                    <i class="fa-solid fa-lock icone-input"></i>
                    <i class="fa-solid fa-eye btn-eye" id="btn-senha" onclick="mostrarOcultarSenha()"></i>
                </div>

                <button type="submit" class="btn-login">
                    ENTRAR NO SISTEMA <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>

            <a href="src/abrir_chamado.php" class="btn-chamado">
                <i class="fa-solid fa-headset" style="color: #f59e0b; margin-right: 5px;"></i> Abrir um Chamado Externo
            </a>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> Acesso Restrito DOP | Versão 1.0<br>
                Desenvolvido por <a href="https://tsii.com.br" target="_blank">TSII</a>
            </div>

        </div>
    </div>

    <script>
        function mostrarOcultarSenha() {
            var input = document.getElementById("campo-senha");
            var icon = document.getElementById("btn-senha");
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>