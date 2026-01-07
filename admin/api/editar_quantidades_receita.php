<?php
require_once '../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$receita_id = isset($data['receita_id']) ? (int)$data['receita_id'] : 0;
$quantidades = isset($data['quantidades']) ? $data['quantidades'] : [];

if (empty($receita_id) || empty($quantidades)) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $conn = getConnection();
    $conn->beginTransaction();
    
    // Verificar se a receita existe e não tem dispensações
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_retiradas
        FROM receitas_retiradas rr
        INNER JOIN receitas_itens ri ON ri.id = rr.receita_item_id
        WHERE ri.receita_id = ?
    ");
    $stmt->execute([$receita_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total_retiradas'] > 0) {
        throw new Exception('Não é possível editar quantidades de uma receita que já possui dispensações. Delete as dispensações primeiro.');
    }
    
    // Atualizar quantidades
    foreach ($quantidades as $item_id => $quantidade) {
        $item_id = (int)$item_id;
        $quantidade = (int)$quantidade;
        
        if ($quantidade < 1) {
            throw new Exception('Quantidade deve ser maior que zero');
        }
        
        // Verificar se o item pertence à receita
        $stmt = $conn->prepare("
            SELECT id FROM receitas_itens 
            WHERE id = ? AND receita_id = ?
        ");
        $stmt->execute([$item_id, $receita_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Item não encontrado ou não pertence a esta receita');
        }
        
        // Atualizar quantidade
        $stmt = $conn->prepare("
            UPDATE receitas_itens 
            SET quantidade_autorizada = ? 
            WHERE id = ?
        ");
        $stmt->execute([$quantidade, $item_id]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Quantidades atualizadas com sucesso'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao editar quantidades: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

