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

// Validações
$receita_item_id = isset($data['receita_item_id']) ? (int)$data['receita_item_id'] : 0;
$medicamento_id = isset($data['medicamento_id']) ? (int)$data['medicamento_id'] : 0;
$lote_id = isset($data['lote_id']) ? (int)$data['lote_id'] : 0;
$quantidade = isset($data['quantidade']) ? (int)$data['quantidade'] : 0;
$observacoes = isset($data['observacoes']) ? trim($data['observacoes']) : '';
$receita_id = isset($data['receita_id']) ? (int)$data['receita_id'] : 0;
$paciente_id = isset($data['paciente_id']) ? (int)$data['paciente_id'] : 0;

if ($receita_item_id <= 0 || $medicamento_id <= 0 || $lote_id <= 0 || $quantidade <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$conn = getConnection();
$usuario_id = $_SESSION['user_id'];

try {
    $conn->beginTransaction();
    
    // 1. Verificar se o item da receita existe e tem quantidade disponível
    $stmt = $conn->prepare("
        SELECT 
            ri.*,
            r.status as receita_status,
            (ri.quantidade_autorizada - ri.quantidade_retirada) as quantidade_disponivel
        FROM receitas_itens ri
        INNER JOIN receitas r ON r.id = ri.receita_id
        WHERE ri.id = ? AND ri.medicamento_id = ?
    ");
    $stmt->execute([$receita_item_id, $medicamento_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Item da receita não encontrado');
    }
    
    if ($item['receita_status'] !== 'ativa') {
        throw new Exception('Receita não está ativa');
    }
    
    if ($item['quantidade_disponivel'] < $quantidade) {
        throw new Exception('Quantidade solicitada maior que a disponível');
    }
    
    // 2. Verificar se o lote existe e tem estoque suficiente
    $stmt = $conn->prepare("
        SELECT * FROM lotes 
        WHERE id = ? AND medicamento_id = ? AND quantidade_atual >= ? AND data_validade >= CURDATE()
    ");
    $stmt->execute([$lote_id, $medicamento_id, $quantidade]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lote) {
        throw new Exception('Lote não disponível ou estoque insuficiente');
    }
    
    // 3. Registrar a retirada na tabela receitas_retiradas
    $stmt = $conn->prepare("
        INSERT INTO receitas_retiradas 
        (receita_item_id, lote_id, quantidade, usuario_id, observacoes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $receita_item_id,
        $lote_id,
        $quantidade,
        $usuario_id,
        $observacoes
    ]);
    
    $retirada_id = $conn->lastInsertId();
    
    // 4. Atualizar quantidade retirada no item da receita
    $stmt = $conn->prepare("
        UPDATE receitas_itens 
        SET quantidade_retirada = quantidade_retirada + ?
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $receita_item_id]);
    
    // 5. Atualizar estoque do lote
    $stmt = $conn->prepare("
        UPDATE lotes 
        SET quantidade_atual = quantidade_atual - ?
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $lote_id]);
    
    // 6. Atualizar estoque atual do medicamento
    $stmt = $conn->prepare("
        UPDATE medicamentos 
        SET estoque_atual = estoque_atual - ?
        WHERE id = ?
    ");
    $stmt->execute([$quantidade, $medicamento_id]);
    
    // 7. Registrar na tabela dispensacoes (histórico geral)
    $stmt = $conn->prepare("
        INSERT INTO dispensacoes 
        (paciente_id, medicamento_id, lote_id, quantidade, usuario_id, observacoes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $paciente_id,
        $medicamento_id,
        $lote_id,
        $quantidade,
        $usuario_id,
        'Dispensação via receita #' . $receita_id . ($observacoes ? ' - ' . $observacoes : '')
    ]);
    
    $dispensacao_id = $conn->lastInsertId();
    
    // 8. Registrar movimentação de saída
    $stmt = $conn->prepare("
        INSERT INTO movimentacoes 
        (medicamento_id, lote_id, tipo, quantidade, usuario_id, observacoes)
        VALUES (?, ?, 'saida', ?, ?, ?)
    ");
    $stmt->execute([
        $medicamento_id,
        $lote_id,
        $quantidade,
        $usuario_id,
        'Dispensação receita #' . $receita_id . ' - Paciente: ' . $paciente_id
    ]);
    
    // 9. Verificar se todos os itens da receita foram totalmente dispensados
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN quantidade_retirada >= quantidade_autorizada THEN 1 ELSE 0 END) as completos
        FROM receitas_itens
        WHERE receita_id = ?
    ");
    $stmt->execute([$receita_id]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se todos os itens foram completamente dispensados, marcar receita como finalizada
    if ($status && $status['total'] == $status['completos']) {
        $stmt = $conn->prepare("UPDATE receitas SET status = 'finalizada' WHERE id = ?");
        $stmt->execute([$receita_id]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Medicamento dispensado com sucesso',
        'retirada_id' => $retirada_id,
        'dispensacao_id' => $dispensacao_id
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
