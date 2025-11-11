<?php
// Teste simples da API sem autenticação
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/database.php';
    $conn = getConnection();
    
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'message' => 'Query vazia', 'debug' => 'Nenhuma query fornecida']);
        exit;
    }
    
    // Buscar medicamentos por código de barras ou nome
    $sql = "SELECT 
                m.id,
                m.nome,
                m.descricao as apresentacao,
                m.codigo_barras,
                m.estoque_atual,
                (SELECT l.id 
                 FROM lotes l 
                 WHERE l.medicamento_id = m.id 
                 AND l.quantidade_atual > 0 
                 AND l.data_validade >= CURDATE()
                 ORDER BY l.data_validade ASC 
                 LIMIT 1) as lote_id
            FROM medicamentos m
            WHERE m.ativo = 1
            AND (
                m.codigo_barras LIKE :query
                OR m.nome LIKE :query_like
            )
            AND m.estoque_atual > 0
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
            'total_found' => count($medicamentos)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar medicamentos: ' . $e->getMessage(),
        'debug' => [
            'query' => $query ?? 'undefined',
            'error_code' => $e->getCode()
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro geral: ' . $e->getMessage(),
        'debug' => [
            'query' => $query ?? 'undefined'
        ]
    ]);
}
?>