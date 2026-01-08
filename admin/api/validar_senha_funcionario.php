<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['senha'])) {
    echo json_encode(['success' => false, 'message' => 'Senha não informada']);
    exit;
}

$senha = trim($data['senha']);

// Validar se a senha é numérica
if (!ctype_digit($senha)) {
    echo json_encode(['success' => false, 'message' => 'A senha deve conter apenas números']);
    exit;
}

try {
    $conn = getConnection();
    
    // Buscar todos os funcionários ativos
    $stmt = $conn->prepare("SELECT id, nome, senha FROM funcionarios WHERE ativo = 1");
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar senha de cada funcionário
    foreach ($funcionarios as $funcionario) {
        if (password_verify($senha, $funcionario['senha'])) {
            echo json_encode([
                'success' => true,
                'funcionario' => [
                    'id' => $funcionario['id'],
                    'nome' => $funcionario['nome']
                ]
            ]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
    
} catch (Exception $e) {
    error_log('Erro ao validar senha do funcionário: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao validar senha']);
}
?>

