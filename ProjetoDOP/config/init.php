<?php
// config/init.php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/database.php';

// Função auxiliar para redirecionar se não estiver logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../index.php");
        exit;
    }
}

// Função auxiliar para verificar permissões específicas
function verificarPermissao($permissao) {
    verificarLogin();
    if (empty($_SESSION[$permissao]) && empty($_SESSION['is_admin'])) {
        header("Location: painel.php");
        exit;
    }
}
?>