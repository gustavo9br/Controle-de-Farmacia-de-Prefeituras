<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query vazia']);
    exit;
}

try {
    // Buscar lotes por número do lote, código de barras, nome do medicamento ou fornecedor
    $sql = "SELECT 
                l.id,
                l.numero_lote,
                l.data_recebimento,
                l.data_validade,
                l.quantidade_atual,
                l.fornecedor,
                l.nota_fiscal,
                m.id as medicamento_id,
                m.nome as medicamento_nome,
                cb.codigo as codigo_barras,
                DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
            FROM lotes l
            INNER JOIN medicamentos m ON l.medicamento_id = m.id
            LEFT JOIN codigos_barras cb ON l.codigo_barras_id = cb.id
            WHERE (
                l.numero_lote LIKE :query_like
                OR cb.codigo LIKE :query_like
                OR m.nome LIKE :query_like
                OR l.fornecedor LIKE :query_like
            )
            ORDER BY 
                CASE 
                    WHEN l.numero_lote = :query_exact THEN 1
                    WHEN cb.codigo = :query_exact THEN 2
                    WHEN l.numero_lote LIKE :query THEN 3
                    WHEN cb.codigo LIKE :query THEN 4
                    ELSE 5
                END,
                l.data_validade ASC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $query_like = '%' . $query . '%';
    $query_exact = $query;
    
    $stmt->execute([
        ':query_like' => $query_like,
        ':query_exact' => $query_exact,
        ':query' => $query
    ]);
    
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lotes' => $lotes
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar lotes: ' . $e->getMessage()
    ]);
}

