<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario', 'medico', 'hospital']);

header('Content-Type: application/json');

try {
    $medicamento_id = $_GET['medicamento_id'] ?? null;
    
    if (!$medicamento_id) {
        echo json_encode(['success' => false, 'message' => 'ID do medicamento não informado']);
        exit;
    }
    
    $db = getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            numero_lote,
            data_validade,
            DATE_FORMAT(data_validade, '%d/%m/%Y') as data_validade_formatada,
            quantidade_atual,
            DATEDIFF(data_validade, NOW()) as dias_validade
        FROM lotes
        WHERE medicamento_id = ?
          AND quantidade_atual > 0
          AND data_validade >= CURDATE()
        ORDER BY data_validade ASC
    ");
    
    $stmt->execute([$medicamento_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lotes' => $lotes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar lotes: ' . $e->getMessage()
    ]);
}
