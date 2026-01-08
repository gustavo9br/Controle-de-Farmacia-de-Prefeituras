-- ============================================
-- Migração: Adicionar funcionario_id na tabela dispensacoes
-- ============================================
-- Data: 2025-01-XX
-- Descrição: Adiciona campo funcionario_id para identificar qual funcionário fez a dispensação
-- IMPORTANTE: Execute este arquivo no phpMyAdmin após fazer backup do banco de dados
-- IMPORTANTE: Execute PRIMEIRO o arquivo migrate_funcionarios.sql
-- ============================================

-- Verificar se a coluna já existe antes de adicionar
SET @db_name = DATABASE();
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'dispensacoes' 
    AND COLUMN_NAME = 'funcionario_id'
);

-- Adicionar coluna funcionario_id se não existir
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `dispensacoes` 
     ADD COLUMN `funcionario_id` int(11) DEFAULT NULL AFTER `usuario_id`,
     ADD KEY `idx_funcionario_id` (`funcionario_id`),
     ADD CONSTRAINT `fk_dispensacoes_funcionario` 
         FOREIGN KEY (`funcionario_id`) 
         REFERENCES `funcionarios` (`id`) 
         ON DELETE SET NULL 
         ON UPDATE CASCADE',
    'SELECT "Coluna funcionario_id já existe na tabela dispensacoes" AS mensagem'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================

