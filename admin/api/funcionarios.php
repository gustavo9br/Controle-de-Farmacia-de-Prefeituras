<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Listar funcionários
    try {
        $stmt = $conn->query("SELECT id, nome, ativo, criado_em FROM funcionarios ORDER BY nome");
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'funcionarios' => $funcionarios
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    $action = $data['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $nome = trim($data['nome'] ?? '');
                $senha = trim($data['senha'] ?? '');
                
                if (empty($nome) || empty($senha)) {
                    echo json_encode(['success' => false, 'message' => 'Nome e senha são obrigatórios']);
                    exit;
                }
                
                // Validar se a senha é numérica
                if (!ctype_digit($senha)) {
                    echo json_encode(['success' => false, 'message' => 'A senha deve conter apenas números']);
                    exit;
                }
                
                // Verificar se já existe funcionário com o mesmo nome
                $stmt = $conn->prepare("SELECT id FROM funcionarios WHERE nome = ?");
                $stmt->execute([$nome]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Já existe um funcionário com este nome']);
                    exit;
                }
                
                // Hash da senha
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO funcionarios (nome, senha, ativo) VALUES (?, ?, 1)");
                $stmt->execute([$nome, $senhaHash]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Funcionário cadastrado com sucesso',
                    'id' => $conn->lastInsertId()
                ]);
                break;
                
            case 'update':
                $id = (int)($data['id'] ?? 0);
                $nome = trim($data['nome'] ?? '');
                $senha = trim($data['senha'] ?? '');
                
                if ($id <= 0 || empty($nome)) {
                    echo json_encode(['success' => false, 'message' => 'ID e nome são obrigatórios']);
                    exit;
                }
                
                // Verificar se já existe outro funcionário com o mesmo nome
                $stmt = $conn->prepare("SELECT id FROM funcionarios WHERE nome = ? AND id != ?");
                $stmt->execute([$nome, $id]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Já existe outro funcionário com este nome']);
                    exit;
                }
                
                if (!empty($senha)) {
                    // Validar se a senha é numérica
                    if (!ctype_digit($senha)) {
                        echo json_encode(['success' => false, 'message' => 'A senha deve conter apenas números']);
                        exit;
                    }
                    
                    // Hash da senha
                    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE funcionarios SET nome = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $senhaHash, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE funcionarios SET nome = ? WHERE id = ?");
                    $stmt->execute([$nome, $id]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Funcionário atualizado com sucesso'
                ]);
                break;
                
            case 'toggle_status':
                $id = (int)($data['id'] ?? 0);
                $ativo = (int)($data['ativo'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }
                
                $novoStatus = $ativo ? 0 : 1;
                $stmt = $conn->prepare("UPDATE funcionarios SET ativo = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Status atualizado com sucesso'
                ]);
                break;
                
            case 'delete':
                $id = (int)($data['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    exit;
                }
                
                // Verificar se há dispensações vinculadas a este funcionário
                $stmt = $conn->prepare("SELECT COUNT(*) FROM dispensacoes WHERE funcionario_id = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Não é possível excluir. Este funcionário possui ' . $count . ' dispensação(ões) registrada(s)'
                    ]);
                    exit;
                }
                
                $stmt = $conn->prepare("DELETE FROM funcionarios WHERE id = ?");
                $stmt->execute([$id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Funcionário excluído com sucesso'
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        }
    } catch (Exception $e) {
        error_log('Erro ao processar funcionário: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao processar: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método não permitido']);
?>

