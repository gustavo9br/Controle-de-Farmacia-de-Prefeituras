<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$action = $_POST['action'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';

if (!verificarCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
    exit;
}

try {
    switch ($action) {
        case 'add':
            $medicamento_id = (int)$_POST['medicamento_id'];
            $codigo = trim($_POST['codigo'] ?? '');
            
            if (empty($codigo)) {
                echo json_encode(['success' => false, 'message' => 'Código de barras é obrigatório']);
                exit;
            }
            
            // Verificar se já existe para este medicamento
            $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
            $stmt->execute([$medicamento_id, $codigo]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este código de barras já está cadastrado para este medicamento']);
                exit;
            }
            
            $stmt = $conn->prepare("INSERT INTO codigos_barras (medicamento_id, codigo) VALUES (?, ?)");
            $stmt->execute([$medicamento_id, $codigo]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Código de barras adicionado com sucesso',
                'id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'edit':
            $id = (int)$_POST['id'];
            $codigo = trim($_POST['codigo'] ?? '');
            
            if (empty($codigo)) {
                echo json_encode(['success' => false, 'message' => 'Código de barras é obrigatório']);
                exit;
            }
            
            // Buscar medicamento_id do código
            $stmt = $conn->prepare("SELECT medicamento_id FROM codigos_barras WHERE id = ?");
            $stmt->execute([$id]);
            $codigo_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$codigo_data) {
                echo json_encode(['success' => false, 'message' => 'Código de barras não encontrado']);
                exit;
            }
            
            // Verificar se já existe outro código igual para este medicamento
            $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ? AND id != ?");
            $stmt->execute([$codigo_data['medicamento_id'], $codigo, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Este código de barras já está cadastrado para este medicamento']);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE codigos_barras SET codigo = ? WHERE id = ?");
            $stmt->execute([$codigo, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Código de barras atualizado com sucesso'
            ]);
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            
            // Verificar se o código está sendo usado em lotes
            $stmt = $conn->prepare("SELECT COUNT(*) FROM lotes WHERE codigo_barras_id = ?");
            $stmt->execute([$id]);
            $uso = (int)$stmt->fetchColumn();
            
            if ($uso > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Este código de barras está sendo usado em $uso lote(s) e não pode ser removido"
                ]);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM codigos_barras WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Código de barras removido com sucesso'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

