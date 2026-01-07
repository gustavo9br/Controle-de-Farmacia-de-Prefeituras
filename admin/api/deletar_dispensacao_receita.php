<?php
require_once '../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$retirada_id = isset($data['retirada_id']) ? (int)$data['retirada_id'] : 0;
$dispensacao_id = isset($data['dispensacao_id']) ? (int)$data['dispensacao_id'] : 0;

if (empty($retirada_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID da retirada não informado'
    ]);
    exit;
}

try {
    $conn = getConnection();
    $conn->beginTransaction();
    
    // 1. Buscar dados da retirada
    $stmt = $conn->prepare("
        SELECT 
            rr.lote_id,
            rr.quantidade,
            ri.medicamento_id
        FROM receitas_retiradas rr
        INNER JOIN receitas_itens ri ON ri.id = rr.receita_item_id
        WHERE rr.id = ?
    ");
    $stmt->execute([$retirada_id]);
    $retirada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$retirada) {
        throw new Exception('Retirada não encontrada');
    }
    
    $medicamento_id = (int)$retirada['medicamento_id'];
    $lote_id = (int)$retirada['lote_id'];
    $quantidade = (int)$retirada['quantidade'];
    
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
    
    // 4. Deletar a dispensação se existir
    if ($dispensacao_id > 0) {
        $stmt = $conn->prepare("DELETE FROM dispensacoes WHERE id = ?");
        $stmt->execute([$dispensacao_id]);
    }
    
    // 5. Deletar a movimentação relacionada (se existir)
    $stmt = $conn->prepare("
        DELETE FROM movimentacoes 
        WHERE medicamento_id = ? 
        AND lote_id = ? 
        AND quantidade = ?
        AND tipo IN ('dispensacao', 'saida')
        AND observacoes LIKE ?
        LIMIT 1
    ");
    $stmt->execute([
        $medicamento_id, 
        $lote_id, 
        $quantidade,
        '%receita%'
    ]);
    
    // 6. Deletar a retirada
    $stmt = $conn->prepare("DELETE FROM receitas_retiradas WHERE id = ?");
    $stmt->execute([$retirada_id]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispensação deletada com sucesso. Estoque revertido.'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao deletar dispensação de receita: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar dispensação: ' . $e->getMessage()
    ]);
}

