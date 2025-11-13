<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario', 'medico', 'hospital']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query) || strlen($query) < 1) {
    echo json_encode(['success' => false, 'message' => 'Query vazia', 'debug' => 'Nenhuma query fornecida']);
    exit;
}

try {
    // Buscar medicamentos por código de barras (na tabela codigos_barras) ou nome
    // Trazer também o lote com validade mais próxima e quantidade disponível
    $sql = "SELECT DISTINCT
                m.id,
                m.nome,
                m.descricao as apresentacao,
                m.estoque_atual,
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
                 LIMIT 1) as lote_id,
                (SELECT GROUP_CONCAT(cb.codigo SEPARATOR ', ') 
                 FROM codigos_barras cb 
                 WHERE cb.medicamento_id = m.id 
                 LIMIT 3) as codigos_barras,
                (SELECT cb.id 
                 FROM codigos_barras cb 
                 WHERE cb.medicamento_id = m.id 
                 AND cb.codigo LIKE :query_like 
                 LIMIT 1) as codigo_barras_id_match
            FROM medicamentos m
            WHERE m.ativo = 1
            AND (
                EXISTS (
                    SELECT 1 
                    FROM codigos_barras cb 
                    WHERE cb.medicamento_id = m.id 
                    AND cb.codigo LIKE :query_like
                )
                OR m.nome LIKE :query_like
                OR m.descricao LIKE :query_like
            )
            ORDER BY 
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM codigos_barras cb 
                        WHERE cb.medicamento_id = m.id 
                        AND cb.codigo = :query_exact
                    ) THEN 1
                    WHEN EXISTS (
                        SELECT 1 
                        FROM codigos_barras cb 
                        WHERE cb.medicamento_id = m.id 
                        AND cb.codigo LIKE :query_start
                    ) THEN 2
                    WHEN EXISTS (
                        SELECT 1 
                        FROM codigos_barras cb 
                        WHERE cb.medicamento_id = m.id 
                        AND cb.codigo LIKE :query_like
                    ) THEN 3
                    WHEN m.nome LIKE :query_start THEN 4
                    ELSE 5
                END,
                m.nome ASC
            LIMIT 10";
    
    $query_like = '%' . $query . '%';
    $query_start = $query . '%';
    $query_exact = $query;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':query_like' => $query_like,
        ':query_start' => $query_start,
        ':query_exact' => $query_exact
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
