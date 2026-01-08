-- ============================================
-- Migração: Tabela de Funcionários
-- ============================================
-- Data: 2025-01-XX
-- Descrição: Cria tabela de funcionários para controlar quem faz as dispensações físicas
-- IMPORTANTE: Execute este arquivo no phpMyAdmin após fazer backup do banco de dados
-- IMPORTANTE: Execute este arquivo ANTES do migrate_dispensacoes_funcionario.sql
-- ============================================

-- Criar tabela de funcionários se não existir
CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL COMMENT 'Senha numérica criptografada com password_hash()',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- FIM DA MIGRAÇÃO
-- ============================================

