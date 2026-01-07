<?php
require_once '../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$dispensacao_id = isset($data['dispensacao_id']) ? (int)$data['dispensacao_id'] : 0;
$movimentacao_id = isset($data['movimentacao_id']) ? (int)$data['movimentacao_id'] : 0;

if (empty($dispensacao_id) || empty($movimentacao_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'IDs não informados'
    ]);
    exit;
}

try {
    $conn = getConnection();
    $conn->beginTransaction();
    
    // 1. Buscar dados da dispensação
    $stmt = $conn->prepare("
        SELECT 
            d.medicamento_id,
            d.lote_id,
            d.quantidade,
            d.paciente_id
        FROM dispensacoes d
        WHERE d.id = ?
    ");
    $stmt->execute([$dispensacao_id]);
    $dispensacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dispensacao) {
        throw new Exception('Dispensação não encontrada');
    }
    
    $medicamento_id = (int)$dispensacao['medicamento_id'];
    $lote_id = (int)$dispensacao['lote_id'];
    $quantidade = (int)$dispensacao['quantidade'];
    
    // 2. Reverter estoque do lote (adicionar de volta)
    $stmt = $conn->prepare("
        UPDATE lotes 
        SET quantidade_atual = quantidade_atual + ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $lote_id]);
    
    // 3. Reverter estoque total do medicamento (adicionar de volta)
    $stmt = $conn->prepare("
        UPDATE medicamentos 
        SET estoque_atual = estoque_atual + ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $medicamento_id]);
    
    // 4. Deletar a movimentação
    $stmt = $conn->prepare("DELETE FROM movimentacoes WHERE id = ?");
    $stmt->execute([$movimentacao_id]);
    
    // 5. Deletar a dispensação
    $stmt = $conn->prepare("DELETE FROM dispensacoes WHERE id = ?");
    $stmt->execute([$dispensacao_id]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispensação deletada com sucesso. Estoque revertido.'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao deletar dispensação: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar dispensação: ' . $e->getMessage()
    ]);
}

