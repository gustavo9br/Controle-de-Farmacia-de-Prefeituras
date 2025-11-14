<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();

// Receber dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['receita_id'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$receita_id = (int)$data['receita_id'];

if ($receita_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da receita inválido']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Verificar se a receita existe
    $stmt = $conn->prepare("SELECT id, status FROM receitas WHERE id = ?");
    $stmt->execute([$receita_id]);
    $receita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receita) {
        throw new Exception('Receita não encontrada');
    }
    
    // Verificar se a receita está finalizada
    if ($receita['status'] === 'finalizada') {
        throw new Exception('Não é possível deletar uma receita finalizada. Considere cancelar a receita se necessário.');
    }
    
    // Verificar se há retiradas já realizadas
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_retiradas
        FROM receitas_retiradas rr
        INNER JOIN receitas_itens ri ON ri.id = rr.receita_item_id
        WHERE ri.receita_id = ?
    ");
    $stmt->execute([$receita_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['total_retiradas'] > 0) {
        throw new Exception('Não é possível deletar uma receita que já possui dispensações realizadas. Considere cancelar a receita.');
    }
    
    // Deletar a receita (ON DELETE CASCADE vai deletar automaticamente):
    // - receitas_itens (que vai deletar receitas_retiradas_planejadas e receitas_retiradas em cascata)
    $stmt = $conn->prepare("DELETE FROM receitas WHERE id = ?");
    $stmt->execute([$receita_id]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Receita deletada com sucesso!'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao deletar receita: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar receita: ' . $e->getMessage()
    ]);
}

