<?php
// config/database.php

try {
    // Liga ao banco de dados SQLite na mesma pasta deste ficheiro
    $pdo = new PDO("sqlite:" . __DIR__ . "/banco.sqlite");
    
    // Configurações de erro e modo de busca padrão
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // =========================================================================
    // CRIAÇÃO AUTOMÁTICA DE TABELAS (Caso não existam)
    // =========================================================================

    // 1. Tabela de Utilizadores
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        senha TEXT NOT NULL,
        is_admin INTEGER DEFAULT 0,
        acesso_dashboard INTEGER DEFAULT 0,
        acesso_portaria INTEGER DEFAULT 0,
        acesso_os INTEGER DEFAULT 0,
        acesso_estoque INTEGER DEFAULT 0,
        acesso_metas INTEGER DEFAULT 0,
        acesso_extintores INTEGER DEFAULT 0,
        acesso_filtros INTEGER DEFAULT 0,
        acesso_solicitacoes INTEGER DEFAULT 0
    )");

    // 2. Tabela de Ordens de Serviço (O.S.)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ordens_servico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT NOT NULL,
        local TEXT NOT NULL,
        solicitante TEXT NOT NULL,
        status TEXT DEFAULT 'Pendente',
        criado_por TEXT,
        data_criacao TEXT
    )");

    // 3. Tabela de Solicitações (Triagem)
    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        descricao TEXT NOT NULL,
        local TEXT NOT NULL,
        solicitante TEXT NOT NULL,
        status TEXT DEFAULT 'Aguardando',
        analisado_por TEXT,
        data_analise TEXT,
        data_criacao TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Tabela de Estoque
    $pdo->exec("CREATE TABLE IF NOT EXISTS estoque (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL,
        quantidade INTEGER DEFAULT 0,
        unidade TEXT NOT NULL
    )");

    // 5. Tabela de Histórico de Estoque
    $pdo->exec("CREATE TABLE IF NOT EXISTS historico_estoque (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto TEXT NOT NULL,
        acao TEXT NOT NULL,
        quantidade INTEGER DEFAULT 0,
        usuario TEXT NOT NULL,
        data_hora TEXT NOT NULL
    )");

    // 6. Tabelas de Segurança (Extintores e Filtros)
    $pdo->exec("CREATE TABLE IF NOT EXISTS extintores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local TEXT NOT NULL,
        tipo TEXT NOT NULL,
        data_vencimento TEXT NOT NULL,
        criado_por TEXT,
        data_criacao TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS filtros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        local TEXT NOT NULL,
        tipo TEXT NOT NULL,
        data_vencimento TEXT NOT NULL,
        criado_por TEXT,
        data_criacao TEXT
    )");

    // 7. Tabela de Histórico Geral de Manutenção (Filtros, Extintores, O.S.)
    $pdo->exec("CREATE TABLE IF NOT EXISTS historico_manutencao (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        categoria TEXT NOT NULL,
        identificador TEXT NOT NULL,
        acao TEXT NOT NULL,
        usuario TEXT NOT NULL,
        data_hora TEXT NOT NULL
    )");

    // 8. Tabela de Metas DOP
    $pdo->exec("CREATE TABLE IF NOT EXISTS metas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titulo TEXT NOT NULL,
        data_limite TEXT NOT NULL,
        status TEXT DEFAULT 'Pendente',
        orcamento REAL DEFAULT 0,
        gasto_real REAL DEFAULT 0,
        criado_por TEXT,
        data_criacao TEXT
    )");

    // 9. Tabela de Ativos (Portaria)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ativos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identificador TEXT NOT NULL,
        categoria TEXT,
        responsavel TEXT,
        status TEXT DEFAULT 'Emprestado'
    )");

    // 10. A TABELA QUE FALTAVA (Histórico da Portaria)
    $pdo->exec("CREATE TABLE IF NOT EXISTS historico_ativos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identificador TEXT NOT NULL,
        acao TEXT NOT NULL,
        responsavel TEXT NOT NULL,
        usuario TEXT NOT NULL,
        data_hora TEXT NOT NULL
    )");

    // =========================================================================
    // CRIAÇÃO DO ADMINISTRADOR PADRÃO
    // =========================================================================
    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($total_usuarios == 0) {
        $senha_padrao = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha, is_admin) VALUES (?, ?, ?, ?)")
            ->execute(['Administrador', 'admin@sgoi.com', $senha_padrao, 1]);
    }

} catch (PDOException $e) {
    error_log("Erro Fatal SQLite (SGOI): " . $e->getMessage());
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px; color:#b91c1c;'>
            <h2>Erro de Conexão</h2>
            <p>Ocorreu um erro interno ao ligar à base de dados.</p>
         </div>");
}
?>