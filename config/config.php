<?php
/**
 * Configurações Globais do Sistema
 */

// Informações básicas do sistema
define('SYSTEM_NAME', 'Farmácia - Controle de Estoque');
define('SYSTEM_VERSION', '1.0.0');
define('COMPANY_NAME', 'Farmácia');
define('COMPANY_SITE', 'https://farmacia.gustavo.uk');

// Configurações de diretórios
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// URL base do sistema
define('BASE_URL', 'https://farmacia.gustavo.uk');

// Configurações de alerta para medicamentos próximos ao vencimento
define('ALERTA_VENCIMENTO_CRITICO', 30);  // Em dias (alerta vermelho)
define('ALERTA_VENCIMENTO_ATENCAO', 60);  // Em dias (alerta amarelo)
define('ALERTA_VENCIMENTO_NORMAL', 90);   // Em dias (alerta azul)

// Configurações de estoque mínimo
define('ESTOQUE_MINIMO_PADRAO', 10);      // Estoque mínimo padrão para novos medicamentos

// Definições para paginação
define('ITENS_POR_PAGINA', 20);

// Formato de data e hora
define('DATA_FORMAT', 'd/m/Y');
define('DATA_HORA_FORMAT', 'd/m/Y H:i');
define('MYSQL_DATE_FORMAT', 'Y-m-d');

// Configurações de segurança
define('TOKEN_SECRET', 'farmacia_gustavo_2025');
define('SESSION_TIMEOUT', 3600); // 1 hora em segundos

// Incluir outros arquivos de configuração
require_once 'database.php';

/**
 * Função para formatar data no padrão brasileiro
 * 
 * @param string $data Data no formato MySQL (YYYY-MM-DD)
 * @return string Data formatada (DD/MM/YYYY)
 */
function formatarData($data) {
    if (empty($data)) return '';
    return date(DATA_FORMAT, strtotime($data));
}

/**
 * Função para formatar valor monetário
 * 
 * @param float $valor Valor a ser formatado
 * @return string Valor formatado (R$ 0,00)
 */
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Função para calcular dias até o vencimento
 * 
 * @param string $dataValidade Data de validade no formato MySQL (YYYY-MM-DD)
 * @return int Número de dias até o vencimento (negativo se já venceu)
 */
function diasAteVencimento($dataValidade) {
    $hoje = new DateTime();
    $vencimento = new DateTime($dataValidade);
    $diferenca = $hoje->diff($vencimento);
    
    // Se já venceu, retorna negativo
    if ($hoje > $vencimento) {
        return -$diferenca->days;
    }
    
    return $diferenca->days;
}

/**
 * Função para determinar a classe CSS de alerta com base nos dias até o vencimento
 * 
 * @param int $dias Número de dias até o vencimento
 * @return string Nome da classe CSS
 */
function classeAlertaVencimento($dias) {
    if ($dias < 0) {
        return 'bg-danger'; // Já vencido
    } else if ($dias <= ALERTA_VENCIMENTO_CRITICO) {
        return 'bg-danger'; // Crítico
    } else if ($dias <= ALERTA_VENCIMENTO_ATENCAO) {
        return 'bg-warning'; // Atenção
    } else if ($dias <= ALERTA_VENCIMENTO_NORMAL) {
        return 'bg-info'; // Normal
    } else {
        return 'bg-success'; // Longe do vencimento
    }
}

/**
 * Função para gerar um token de segurança
 * 
 * @return string Token de segurança
 */
function gerarToken() {
    return md5(uniqid(rand(), true) . TOKEN_SECRET);
}
?>
