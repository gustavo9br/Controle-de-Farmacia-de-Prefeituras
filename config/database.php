<?php
/**
 * Configuração do Banco de Dados
 * 
 * Este arquivo contém as configurações necessárias para conexão com o banco de dados
 */

// Credenciais do banco de dados
define('DB_SERVER', 'mysql');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'BAAE3A32D667F546851BED3777633');
define('DB_NAME', 'farmacia');

// Configuração de timezone para o Brasil
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros (apenas em desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Conecta ao banco de dados usando PDO
 * 
 * @return PDO Conexão com o banco de dados
 */
function getConnection() {
    try {
        // Criar DSN para o banco de dados
        $dsn = 'mysql:host=' . DB_SERVER . ';charset=utf8mb4';
        
        // Conectar sem especificar um banco de dados
        $conn = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
        
        // Configurar o PDO para lançar exceções em caso de erro
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Verificar se o banco de dados existe, se não, criá-lo
        try {
            $conn->query("USE " . DB_NAME);
        } catch (PDOException $e) {
            // Banco de dados não existe, criar
            $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $conn->exec($sql);
            $conn->query("USE " . DB_NAME);
        }
        
        return $conn;
    } catch (PDOException $e) {
        die("ERRO: Não foi possível conectar ao MySQL. " . $e->getMessage());
    }
}
?>
