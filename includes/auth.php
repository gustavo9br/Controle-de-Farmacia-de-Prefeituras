<?php
// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivo de configurações
require_once __DIR__ . '/../config/config.php';

/**
 * Verifica se o usuário está autenticado
 * 
 * @return bool True se o usuário estiver autenticado, false caso contrário
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    if (!isLoggedIn()) {
        return null;
    }
    return strtolower(trim($_SESSION['user_nivel'] ?? '')) ?: null;
}

function userHasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }

    $role = getUserRole();
    if ($role === null) {
        return false;
    }

    if (is_array($roles)) {
        $normalized = array_map(function ($item) {
            return strtolower(trim($item));
        }, $roles);
        return in_array($role, $normalized, true);
    }

    return $role === strtolower(trim($roles));
}

function isAdmin() {
    return userHasRole('admin');
}

function isMedico() {
    return userHasRole('medico');
}

function isHospital() {
    return userHasRole('hospital');
}

function requireRole($roles) {
    requireLogin();

    if (!userHasRole($roles)) {
        $_SESSION['error'] = "Você não tem permissão para acessar esta página.";
        redirectAfterLogin();
    }
}

/**
 * Redireciona para a página de login se o usuário não estiver autenticado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Você precisa fazer login para acessar esta página.";
        header("Location: /login.php");
        exit;
    }
}

function requireAdmin() {
    requireRole('admin');
}

/**
 * Registra o login do usuário na sessão
 * 
 * @param array $user Dados do usuário
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_email'] = $user['email'];
    
    // Corrigido: usar o campo 'tipo' do banco de dados em vez de 'nivel'
    if (isset($user['tipo'])) {
        $_SESSION['user_nivel'] = $user['tipo']; // Usar o campo 'tipo' do banco de dados
    } else if (isset($user['nivel'])) {
        $_SESSION['user_nivel'] = $user['nivel']; // Compatibilidade com versões anteriores
    } else {
        $_SESSION['user_nivel'] = 'usuario'; // Valor padrão se não definido
    }
    
    // Log para depuração
    error_log("Login realizado: ID {$user['id']}, Nome: {$user['nome']}, Tipo/Nivel: {$_SESSION['user_nivel']}");
    
    // Atualizar o último acesso
    $conn = getConnection();
    try {
        $stmt = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $user['id']);
        $stmt->execute();
    } catch (PDOException $e) {
        // Apenas registre o erro, não impacta na funcionalidade principal
    }
}

/**
 * Faz logout do usuário
 */
function logoutUser() {
    // Limpar todas as variáveis de sessão
    $_SESSION = array();
    
    // Destruir a sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Obtém mensagem de erro da sessão
 * 
 * @return string Mensagem de erro
 */
function getErrorMessage() {
    $error = '';
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    return $error;
}

/**
 * Obtém mensagem de sucesso da sessão
 * 
 * @return string Mensagem de sucesso
 */
function getSuccessMessage() {
    $success = '';
    if (isset($_SESSION['success'])) {
        $success = $_SESSION['success'];
        unset($_SESSION['success']);
    }
    return $success;
}

/**
 * Redireciona para a página apropriada após o login
 */
function redirectAfterLogin() {
    $role = getUserRole();

    switch ($role) {
        case 'admin':
            header("Location: admin/index.php");
            break;
        case 'medico':
            header("Location: medico/index.php");
            break;
        case 'hospital':
            header("Location: hospital/index.php");
            break;
        default:
            header("Location: usuario/index.php");
            break;
    }
    exit;
}

/**
 * Verifica se o usuário tem permissão para realizar uma ação
 * 
 * @param string $permissao Nome da permissão a ser verificada
 * @return bool True se o usuário tem permissão, false caso contrário
 */
function temPermissao($permissao) {
    // Administradores têm todas as permissões
    if (isAdmin()) {
        return true;
    }
    
    // Para implementar permissões mais granulares, pode-se criar uma tabela de permissões
    // e verificar aqui
    
    return false;
}

/**
 * Gera um token CSRF para proteção de formulários
 * 
 * @return string Token CSRF
 */
function gerarCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verifica se o token CSRF é válido
 * 
 * @param string $token Token a ser verificado
 * @return bool True se o token for válido, false caso contrário
 */
function verificarCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    return true;
}
?>
