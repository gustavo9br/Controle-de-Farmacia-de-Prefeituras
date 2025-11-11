<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario', 'medico', 'hospital']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query vazia', 'debug' => 'Nenhuma query fornecida']);
    exit;
}

try {
    // Buscar medicamentos por código de barras ou nome
    // Trazer também o lote com validade mais próxima e quantidade disponível
    $sql = "SELECT 
                m.id,
                m.nome,
                m.descricao as apresentacao,
                m.codigo_barras,
                m.estoque_atual,
                f.nome as fabricante,
                COALESCE(
                    (SELECT SUM(l.quantidade_atual) 
                     FROM lotes l 
                     WHERE l.medicamento_id = m.id 
                     AND l.quantidade_atual > 0 
                     AND l.data_validade >= CURDATE()
                    ), 0
                ) as estoque_total,
                (SELECT l.id 
                 FROM lotes l 
                 WHERE l.medicamento_id = m.id 
                 AND l.quantidade_atual > 0 
                 AND l.data_validade >= CURDATE()
                 ORDER BY l.data_validade ASC 
                 LIMIT 1) as lote_id
            FROM medicamentos m
            LEFT JOIN fabricantes f ON m.fabricante_id = f.id
            WHERE m.ativo = 1
            AND (
                m.codigo_barras LIKE :query
                OR m.nome LIKE :query_like
            )
            ORDER BY 
                CASE 
                    WHEN m.codigo_barras = :query_exact THEN 1
                    WHEN m.codigo_barras LIKE :query THEN 2
                    ELSE 3
                END,
                m.nome ASC
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':query' => $query,
        ':query_like' => '%' . $query . '%',
        ':query_exact' => $query
    ]);
    
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'medicamentos' => $medicamentos,
        'debug' => [
            'query' => $query,
            'query_like' => '%' . $query . '%',
            'total_found' => count($medicamentos),
            'sql_executed' => true
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar medicamentos: ' . $e->getMessage(),
        'debug' => [
            'query' => $query,
            'error_code' => $e->getCode(),
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
