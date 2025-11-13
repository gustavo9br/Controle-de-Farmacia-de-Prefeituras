<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario', 'medico', 'hospital']);

header('Content-Type: application/json');

try {
    $medicamento_id = $_GET['medicamento_id'] ?? null;
    $codigo_barras_id = isset($_GET['codigo_barras_id']) ? (int)$_GET['codigo_barras_id'] : null;
    
    if (!$medicamento_id) {
        echo json_encode(['success' => false, 'message' => 'ID do medicamento não informado']);
        exit;
    }
    
    $db = getConnection();
    
    // Se foi fornecido codigo_barras_id, filtrar apenas lotes desse código de barras
    if ($codigo_barras_id && $codigo_barras_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.numero_lote,
                l.data_validade,
                DATE_FORMAT(l.data_validade, '%d/%m/%Y') as data_validade_formatada,
                l.quantidade_atual,
                DATEDIFF(l.data_validade, NOW()) as dias_validade
            FROM lotes l
            WHERE l.medicamento_id = ?
              AND l.codigo_barras_id = ?
              AND l.quantidade_atual > 0
              AND l.data_validade >= CURDATE()
            ORDER BY l.data_validade ASC
        ");
        
        $stmt->execute([$medicamento_id, $codigo_barras_id]);
    } else {
        // Buscar todos os lotes do medicamento
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.numero_lote,
                l.data_validade,
                DATE_FORMAT(l.data_validade, '%d/%m/%Y') as data_validade_formatada,
                l.quantidade_atual,
                DATEDIFF(l.data_validade, NOW()) as dias_validade
            FROM lotes l
            WHERE l.medicamento_id = ?
              AND l.quantidade_atual > 0
              AND l.data_validade >= CURDATE()
            ORDER BY l.data_validade ASC
        ");
        
        $stmt->execute([$medicamento_id]);
    }
    
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
