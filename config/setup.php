<?php
/**
 * Script para criação das tabelas do sistema
 */
require_once 'database.php';

// Obter conexão com o banco de dados
$conn = getConnection();

// Array com as consultas SQL para criar as tabelas necessárias
$tabelas = [
    // Tabela de medicamentos
    "CREATE TABLE IF NOT EXISTS `medicamentos` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(255) NOT NULL,
        `descricao` TEXT,
        `codigo_barras` VARCHAR(50),
        `fabricante` VARCHAR(255),
        `tipo` VARCHAR(100),
        `categoria` VARCHAR(100),
        `unidade_medida` VARCHAR(50),
        `quantidade_por_unidade` INT,
        `preco_compra` DECIMAL(10,2),
        `preco_venda` DECIMAL(10,2),
        `estoque_minimo` INT DEFAULT 10,
        `estoque_atual` INT DEFAULT 0,
        `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_medicamento_nome` (`nome`),
        INDEX `idx_medicamento_codigo` (`codigo_barras`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Tabela de lotes de medicamentos (para controle de validade)
    "CREATE TABLE IF NOT EXISTS `lotes` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `medicamento_id` INT NOT NULL,
        `numero_lote` VARCHAR(50) NOT NULL,
        `data_fabricacao` DATE,
        `data_validade` DATE NOT NULL,
        `quantidade_caixas` INT NOT NULL,
        `quantidade_por_caixa` INT NOT NULL,
        `quantidade_total` INT NOT NULL,
        `quantidade_atual` INT NOT NULL,
        `data_recebimento` DATE NOT NULL,
        `preco_compra` DECIMAL(10,2),
        `observacoes` TEXT,
        `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`medicamento_id`) REFERENCES `medicamentos`(`id`) ON DELETE CASCADE,
        INDEX `idx_lote_numero` (`numero_lote`),
        INDEX `idx_lote_validade` (`data_validade`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Tabela de pessoas (clientes, fornecedores, funcionários)
    "CREATE TABLE IF NOT EXISTS `pessoas` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(255) NOT NULL,
        `cpf` VARCHAR(14) NOT NULL,
        `rg` VARCHAR(20),
        `telefone` VARCHAR(20),
        `email` VARCHAR(255),
        `endereco` TEXT,
        `tipo` ENUM('cliente', 'fornecedor', 'funcionario') NOT NULL,
        `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_cpf` (`cpf`),
        INDEX `idx_pessoa_nome` (`nome`),
        INDEX `idx_pessoa_tipo` (`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Tabela de movimentações de estoque (entradas e saídas)
    "CREATE TABLE IF NOT EXISTS `movimentacoes` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `tipo` ENUM('entrada', 'saida') NOT NULL,
        `lote_id` INT NOT NULL,
        `medicamento_id` INT NOT NULL,
        `pessoa_id` INT NOT NULL,
        `quantidade` INT NOT NULL,
        `data_movimentacao` DATETIME NOT NULL,
        `numero_nota` VARCHAR(50),
        `preco_unitario` DECIMAL(10,2),
        `valor_total` DECIMAL(10,2),
        `motivo` VARCHAR(255),
        `observacoes` TEXT,
        `data_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`lote_id`) REFERENCES `lotes`(`id`),
        FOREIGN KEY (`medicamento_id`) REFERENCES `medicamentos`(`id`),
        FOREIGN KEY (`pessoa_id`) REFERENCES `pessoas`(`id`),
        INDEX `idx_movimentacao_tipo` (`tipo`),
        INDEX `idx_movimentacao_data` (`data_movimentacao`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Tabela de usuários do sistema
    "CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `nome` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `senha` VARCHAR(255) NOT NULL,
        `nivel` ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
        `status` ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
        `ultimo_acesso` DATETIME,
        `data_cadastro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_usuario_email` (`email`),
        INDEX `idx_usuario_nivel` (`nivel`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Criar cada tabela
try {
    foreach ($tabelas as $sql) {
        $conn->exec($sql);
    }
    
    // Verificar se há algum usuário admin cadastrado
    $stmt = $conn->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'admin'");
    $adminCount = $stmt->fetchColumn();
    
    // Se não houver administrador, criar um padrão
    if ($adminCount == 0) {
        // Senha padrão: admin123
        $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO usuarios (nome, email, senha, nivel) 
                VALUES ('Administrador', 'admin@farmacia.com', :senha, 'admin')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':senha', $senhaHash);
        $stmt->execute();
        
        echo "<strong>AVISO:</strong> Usuário administrador padrão criado.<br>";
        echo "Email: admin@farmacia.com<br>";
        echo "Senha: admin123<br><br>";
    }
    
    echo "Tabelas criadas/verificadas com sucesso!";
} catch(PDOException $e) {
    echo "Erro ao criar tabelas: " . $e->getMessage();
}
?>
