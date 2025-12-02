<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

try {
    $medicamento_id = isset($_GET['medicamento_id']) ? (int)$_GET['medicamento_id'] : 0;
    $codigo_barras = isset($_GET['codigo_barras']) ? trim($_GET['codigo_barras']) : '';
    
    if ($medicamento_id <= 0 || empty($codigo_barras)) {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos', 'lotes' => []]);
        exit;
    }
    
    $conn = getConnection();
    
    // Buscar o código de barras ID
    $stmt = $conn->prepare("SELECT id FROM codigos_barras WHERE medicamento_id = ? AND codigo = ?");
    $stmt->execute([$medicamento_id, $codigo_barras]);
    $codigo_barras_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$codigo_barras_row) {
        // Código de barras não existe ainda, retornar vazio
        echo json_encode(['success' => true, 'lotes' => []]);
        exit;
    }
    
    $codigo_barras_id = (int)$codigo_barras_row['id'];
    
    // Buscar todos os lotes deste código de barras e medicamento
    $stmt = $conn->prepare("
        SELECT 
            l.id,
            l.numero_lote,
            l.data_recebimento,
            DATE_FORMAT(l.data_recebimento, '%d/%m/%Y') as data_recebimento_formatada,
            l.data_validade,
            DATE_FORMAT(l.data_validade, '%d/%m/%Y') as data_validade_formatada,
            l.quantidade_atual,
            l.fornecedor,
            l.nota_fiscal,
            l.observacoes,
            DATEDIFF(l.data_validade, CURDATE()) as dias_para_vencer
        FROM lotes l
        WHERE l.medicamento_id = ?
          AND l.codigo_barras_id = ?
        ORDER BY l.data_validade ASC, l.numero_lote ASC
    ");
    
    $stmt->execute([$medicamento_id, $codigo_barras_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lotes' => $lotes
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar lotes: ' . $e->getMessage(),
        'lotes' => []
    ]);
}

