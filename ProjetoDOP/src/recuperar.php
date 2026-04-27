<?php
require_once 'config/database.php';

try {
    $senha_nova = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, is_admin = 1 WHERE email = 'admin@sgoi.com'");
    $stmt->execute([$senha_nova]);
    
    if ($stmt->rowCount() == 0) {
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, ?)")
            ->execute(['Administrador Master', 'admin@sgoi.com', $senha_nova, 1]);
        $mensagem = "✅ Conta de Administrador CRIADA com sucesso!";
    } else {
        $mensagem = "🔄 Senha do Administrador RESETADA com sucesso!";
    }
} catch (Exception $e) {
    die("Erro ao tentar recuperar: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperação SGOI</title>
    <style>
        body { font-family: sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 15px rgba(0,0,0,0.1); text-align: center; }
        .btn { background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #059669;"><?php echo $mensagem; ?></h2>
        <p><strong>E-mail:</strong> admin@sgoi.com</p>
        <p><strong>Senha:</strong> admin123</p>
        <a href="index.php" class="btn">Ir para o Login</a>
    </div>
</body>
</html>