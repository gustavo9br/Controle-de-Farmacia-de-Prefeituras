<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

try {
    $codigo_barras = $_GET['codigo_barras'] ?? null;
    $medicamento_id = isset($_GET['medicamento_id']) ? (int)$_GET['medicamento_id'] : null;
    
    if (!$codigo_barras) {
        echo json_encode(['success' => false, 'message' => 'Código de barras não informado']);
        exit;
    }
    
    if (!$medicamento_id) {
        echo json_encode(['success' => false, 'message' => 'ID do medicamento não informado']);
        exit;
    }
    
    $db = getConnection();
    
    // Buscar lote pelo código de barras e medicamento
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.numero_lote,
            l.data_validade,
            DATE_FORMAT(l.data_validade, '%d/%m/%Y') as data_validade_formatada,
            l.quantidade_atual,
            DATEDIFF(l.data_validade, NOW()) as dias_validade,
            cb.codigo as codigo_barras
        FROM lotes l
        INNER JOIN codigos_barras cb ON cb.id = l.codigo_barras_id
        WHERE cb.codigo = ?
          AND l.medicamento_id = ?
          AND l.quantidade_atual > 0
          AND l.data_validade >= CURDATE()
        ORDER BY l.data_validade ASC
        LIMIT 1
    ");
    
    $stmt->execute([$codigo_barras, $medicamento_id]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lote) {
        echo json_encode([
            'success' => true,
            'lote' => $lote
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Lote não encontrado para este código de barras e medicamento'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar lote: ' . $e->getMessage()
    ]);
}

