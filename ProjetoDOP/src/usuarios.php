<?php
require_once '../config/init.php';

// =========================================================================
// AUTO-CORREÇÃO DO BANCO DE DADOS (SQLite)
// Garante que todas as colunas de permissão existem na tabela.
// =========================================================================
try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN acesso_solicitacoes INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN acesso_extintores INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE usuarios ADD COLUMN acesso_filtros INTEGER DEFAULT 0"); } catch (Exception $e) {}
// =========================================================================

if (!isset($_SESSION['usuario_id']) || $_SESSION['is_admin'] != 1) { header("Location: painel.php"); exit; }

$user_edit = null; $erro_msg = "";
if (isset($_GET['editar'])) { $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?"); $stmt->execute([$_GET['editar']]); $user_edit = $stmt->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir') { 
        if ($_POST['id_excluir'] != $_SESSION['usuario_id']) { 
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$_POST['id_excluir']]); 
            header("Location: usuarios.php"); exit; 
        } 
    }
    
    if (isset($_POST['salvar_usuario'])) {
        $email = trim($_POST['email']); $id_edit = $_POST['id_edit'] ?? '';
        $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?"); 
        $stmt_check->execute([$email, $id_edit]);
        
        if ($stmt_check->fetch()) { 
            $erro_msg = "Este e-mail já está cadastrado!"; 
        } else {
            $senhaHash = !empty($_POST['senha']) ? password_hash($_POST['senha'], PASSWORD_DEFAULT) : null;
            $ad = isset($_POST['is_admin']) ? 1 : 0; 
            
            // Definição de permissões baseada no checkbox do formulário
            if ($ad == 1) { 
                $d = 1; $p = 1; $o = 1; $e = 1; $m = 1; $ex = 1; $fl = 1; $sol = 1; 
            } else { 
                $d = isset($_POST['acesso_dash']) ? 1 : 0; 
                $p = isset($_POST['acesso_port']) ? 1 : 0; 
                $o = isset($_POST['acesso_os']) ? 1 : 0; 
                $e = isset($_POST['acesso_est']) ? 1 : 0; 
                $m = isset($_POST['acesso_metas']) ? 1 : 0; 
                $ex = isset($_POST['acesso_extintores']) ? 1 : 0; 
                $fl = isset($_POST['acesso_filtros']) ? 1 : 0;
                $sol = isset($_POST['acesso_solicitacoes']) ? 1 : 0; 
            }

            if (!empty($id_edit)) { 
                // Atualização de usuário existente
                $pdo->prepare("UPDATE usuarios SET nome=?, email=?, is_admin=?, acesso_dashboard=?, acesso_portaria=?, acesso_os=?, acesso_estoque=?, acesso_metas=?, acesso_extintores=?, acesso_filtros=?, acesso_solicitacoes=? WHERE id=?")
                    ->execute([trim($_POST['nome']), $email, $ad, $d, $p, $o, $e, $m, $ex, $fl, $sol, $id_edit]); 
                if ($senhaHash) { 
                    $pdo->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$senhaHash, $id_edit]); 
                }
                
                // ATUALIZA A SESSÃO NA HORA SE VOCÊ EDITAR O SEU PRÓPRIO UTILIZADOR
                if ($id_edit == $_SESSION['usuario_id']) {
                    $_SESSION['is_admin'] = $ad;
                    $_SESSION['acesso_dashboard'] = $d;
                    $_SESSION['acesso_portaria'] = $p;
                    $_SESSION['acesso_os'] = $o;
                    $_SESSION['acesso_estoque'] = $e;
                    $_SESSION['acesso_metas'] = $m;
                    $_SESSION['acesso_extintores'] = $ex;
                    $_SESSION['acesso_filtros'] = $fl;
                    $_SESSION['acesso_solicitacoes'] = $sol;
                }

            } else { 
                // Inserção de novo usuário
                $pdo->prepare("INSERT INTO usuarios (nome, email, senha, is_admin, acesso_dashboard, acesso_portaria, acesso_os, acesso_estoque, acesso_metas, acesso_extintores, acesso_filtros, acesso_solicitacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([trim($_POST['nome']), $email, $senhaHash, $ad, $d, $p, $o, $e, $m, $ex, $fl, $sol]); 
            }
            header("Location: usuarios.php"); exit;
        }
    }
}
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>SGOI - Usuários</title>
</head>
<body class="layout-app">
    <div class="sidebar-overlay" id="overlay" onclick="toggleMenu()"></div>
    <?php $pagina_atual = 'usuarios.php'; include 'menu_nav.php'; ?>
    
    <main class="content" id="main-content">
        <header class="topbar" style="margin-bottom: 20px;">
            <button class="hamburger" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i> Menu</button>
            <div class="topbar-right">
                <span class="badge-online">
                    <i class="fa-solid fa-circle" style="font-size: 0.6rem;"></i> 
                    <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?> (Online)
                </span>
                </div>
        </header>
        
        <h1 style="color: var(--primary-green); margin-bottom: 25px;"><i class="fa-solid fa-users-gear"></i> Gestão de Colaboradores</h1>
        
        <?php if(!empty($erro_msg)): ?><div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:25px; border-left:5px solid #b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $erro_msg; ?></div><?php endif; ?>
        
        <section class="stat-card" style="margin-bottom:30px; border-top:4px solid var(--primary-green);">
            <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="var f = document.getElementById('form-usuario'); var i = this.querySelector('.seta'); if(f.style.display === 'none') { f.style.display = 'block'; i.classList.replace('fa-chevron-down', 'fa-chevron-up'); } else { f.style.display = 'none'; i.classList.replace('fa-chevron-up', 'fa-chevron-down'); }">
                <h3 style="margin:0; color: var(--text-main);"><i class="fa-solid <?php echo $user_edit ? 'fa-user-pen' : 'fa-plus-circle'; ?>"></i> <?php echo $user_edit ? "Editar Colaborador" : "Cadastrar Novo Colaborador"; ?></h3>
                <i class="fa-solid <?php echo $user_edit ? 'fa-chevron-up' : 'fa-chevron-down'; ?> seta" style="color: var(--text-muted); font-size: 1.2em;"></i>
            </div>
            
            <form id="form-usuario" method="POST" style="display:<?php echo $user_edit ? 'block' : 'none'; ?>; margin-top:20px; padding-top:20px; border-top: 1px dashed #cbd5e1;">
                <input type="hidden" name="id_edit" value="<?php echo $user_edit['id'] ?? ''; ?>">
                <div style="display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;">
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($user_edit['nome'] ?? ''); ?>" placeholder="Nome Completo" required style="flex:1; min-width: 200px;">
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_edit['email'] ?? ''); ?>" placeholder="E-mail" required style="flex:1; min-width: 200px;">
                    <input type="password" name="senha" placeholder="Senha" <?php echo $user_edit ? '' : 'required'; ?> style="flex:1; min-width: 150px;">
                </div>
                
                <div style="margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:10px; display:flex; gap:20px; flex-wrap:wrap; border: 2px solid #e2e8f0;">
                    <strong style="color: var(--text-main);"><i class="fa-solid fa-shield-halved"></i> Permissões:</strong>
                    <label><input type="checkbox" class="perm-check" name="acesso_dash" value="1" <?php echo ($user_edit && $user_edit['acesso_dashboard']) ? 'checked' : ''; ?>> Dashboard</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_metas" value="1" <?php echo ($user_edit && $user_edit['acesso_metas']) ? 'checked' : ''; ?>> Metas</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_port" value="1" <?php echo ($user_edit && $user_edit['acesso_portaria']) ? 'checked' : ''; ?>> Portaria</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_solicitacoes" value="1" <?php echo ($user_edit && $user_edit['acesso_solicitacoes']) ? 'checked' : ''; ?>> Solicitações</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_os" value="1" <?php echo ($user_edit && $user_edit['acesso_os']) ? 'checked' : ''; ?>> O.S.</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_est" value="1" <?php echo ($user_edit && $user_edit['acesso_estoque']) ? 'checked' : ''; ?>> Estoque</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_extintores" value="1" <?php echo ($user_edit && $user_edit['acesso_extintores']) ? 'checked' : ''; ?>> Extintores</label>
                    <label><input type="checkbox" class="perm-check" name="acesso_filtros" value="1" <?php echo ($user_edit && $user_edit['acesso_filtros']) ? 'checked' : ''; ?>> Filtros</label>
                    <label style="color: var(--danger); font-weight: bold;"><input type="checkbox" name="is_admin" value="1" <?php echo ($user_edit && $user_edit['is_admin']) ? 'checked' : ''; ?> onchange="if(this.checked) document.querySelectorAll('.perm-check').forEach(cb => cb.checked = true);"> ACESSO TOTAL</label>
                </div>
                <button type="submit" name="salvar_usuario" class="btn"><i class="fa-solid fa-floppy-disk"></i> Gravar Colaborador</button>
            </form>
        </section>

        <div class="stat-card" style="padding:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse;">
                <thead><tr><th style="padding:18px; text-align:left;">Colaborador</th><th style="padding:18px; text-align:left;">Permissões Ativas</th><th style="padding:18px; text-align:center;">Ações</th></tr></thead>
                <tbody>
                <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td style="padding:18px; border-bottom:1px solid #f1f5f9;"><strong><?php echo htmlspecialchars($u['nome']); ?></strong><br><small><?php echo htmlspecialchars($u['email']); ?></small></td>
                        <td style="padding:18px; border-bottom:1px solid #f1f5f9;"><div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <?php if($u['is_admin']) echo "<span style='font-size:10px; background:#fee2e2; color:#b91c1c; padding:4px 8px; border-radius:4px; font-weight:800;'><i class='fa-solid fa-crown'></i> ADMIN</span>"; 
                                else {
                                    if($u['acesso_dashboard']) echo "<span style='font-size:10px; background:#e0f2fe; color:#0369a1; padding:3px 6px; border-radius:4px; font-weight:bold;'>DASH</span>";
                                    if($u['acesso_metas']) echo "<span style='font-size:10px; background:#f3e8ff; color:#6d28d9; padding:3px 6px; border-radius:4px; font-weight:bold;'>METAS</span>";
                                    if($u['acesso_portaria']) echo "<span style='font-size:10px; background:#dcfce7; color:#15803d; padding:3px 6px; border-radius:4px; font-weight:bold;'>PORT</span>";
                                    if($u['acesso_solicitacoes'] ?? false) echo "<span style='font-size:10px; background:#ecfdf5; color:#059669; padding:3px 6px; border-radius:4px; font-weight:bold;'>SOLIC</span>";
                                    if($u['acesso_os']) echo "<span style='font-size:10px; background:#fef3c7; color:#b45309; padding:3px 6px; border-radius:4px; font-weight:bold;'>O.S.</span>";
                                    if($u['acesso_estoque']) echo "<span style='font-size:10px; background:#f1f5f9; color:#475569; padding:3px 6px; border-radius:4px; font-weight:bold;'>EST</span>";
                                    if($u['acesso_extintores']) echo "<span style='font-size:10px; background:#fee2e2; color:#ef4444; padding:3px 6px; border-radius:4px; font-weight:bold;'>EXT</span>";
                                    if($u['acesso_filtros']) echo "<span style='font-size:10px; background:#e0f2fe; color:#0ea5e9; padding:3px 6px; border-radius:4px; font-weight:bold;'>FILT</span>";
                                    
                                    $has_any = $u['acesso_dashboard'] || $u['acesso_metas'] || $u['acesso_portaria'] || $u['acesso_os'] || $u['acesso_estoque'] || $u['acesso_extintores'] || $u['acesso_filtros'] || ($u['acesso_solicitacoes'] ?? false);
                                    if(!$has_any) echo "<span style='font-size:10px; background:#f1f5f9; color:#94a3b8; padding:3px 6px; border-radius:4px; font-weight:bold;'><i class='fa-solid fa-lock'></i> SEM ACESSO</span>";
                                } ?>
                        </div></td>
                        <td style="padding:18px; border-bottom:1px solid #f1f5f9; text-align:center;"><div style="display:flex; justify-content:center; gap:15px;"><a href="?editar=<?php echo $u['id']; ?>" style="color:#64748b;"><i class="fa-solid fa-user-pen"></i></a><?php if($u['id'] != $_SESSION['usuario_id']): ?><form method="POST" style="margin:0; display:inline;"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id_excluir" value="<?php echo $u['id']; ?>"><button type="submit" style="background:none; border:none; color:var(--danger); cursor:pointer;"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        function toggleMenu() { document.getElementById('sidebar').classList.toggle('closed'); document.getElementById('main-content').classList.toggle('expanded'); }
    </script>
</body>
</html>