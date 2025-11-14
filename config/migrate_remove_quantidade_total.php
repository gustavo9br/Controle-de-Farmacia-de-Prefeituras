<?php
/**
 * Script de migração para remover a coluna quantidade_total da tabela lotes
 * 
 * IMPORTANTE: Execute este script apenas uma vez!
 * 
 * Como usar:
 * 1. Faça backup do banco de dados antes de executar
 * 2. Execute: php config/migrate_remove_quantidade_total.php
 */

require_once __DIR__ . '/database.php';

try {
    $conn = getConnection();
    
    echo "Iniciando migração para remover coluna quantidade_total...\n";
    
    // Verificar se a coluna existe
    $stmt = $conn->query("SHOW COLUMNS FROM lotes LIKE 'quantidade_total'");
    $colunaExiste = $stmt->fetch();
    
    if (!$colunaExiste) {
        echo "A coluna quantidade_total não existe na tabela lotes. Nada a fazer.\n";
        exit(0);
    }
    
    echo "Coluna quantidade_total encontrada. Removendo...\n";
    
    // Remover a coluna
    $conn->exec("ALTER TABLE lotes DROP COLUMN quantidade_total");
    
    echo "✓ Coluna quantidade_total removida com sucesso!\n";
    echo "Migração concluída.\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

