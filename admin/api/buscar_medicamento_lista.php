<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterEstoque = isset($_GET['filter_estoque']) ? $_GET['filter_estoque'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'nome';

$allowedSorts = [
    'nome' => 'm.nome',
    'estoque_atual' => 'm.estoque_atual',
    'proxima_validade' => 'proxima_validade'
];

$sortColumn = $allowedSorts[$sort] ?? $allowedSorts['nome'];

try {
    $whereClauses = [];
    $params = [];
    
    // Busca por nome, descrição ou código de barras
    if (!empty($query)) {
        $whereClauses[] = '(m.nome LIKE ? OR m.descricao LIKE ? OR cb.codigo LIKE ?)';
        $searchParam = '%' . $query . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Filtro de estoque
    if ($filterEstoque === 'baixo') {
        $whereClauses[] = 'm.estoque_atual > 0 AND m.estoque_atual <= m.estoque_minimo';
    } elseif ($filterEstoque === 'zerado') {
        $whereClauses[] = 'm.estoque_atual <= 0';
    } elseif ($filterEstoque === 'disponivel') {
        $whereClauses[] = 'm.estoque_atual > m.estoque_minimo';
    }
    
    $whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // Query principal
    $sql = "SELECT DISTINCT m.*,
                (SELECT MIN(l.data_validade) FROM lotes l WHERE l.medicamento_id = m.id AND l.quantidade_atual > 0) AS proxima_validade,
                (SELECT DATEDIFF(MIN(l.data_validade), CURDATE()) FROM lotes l WHERE l.medicamento_id = m.id AND l.quantidade_atual > 0) AS dias_para_vencer
            FROM medicamentos m
            LEFT JOIN codigos_barras cb ON cb.medicamento_id = m.id
            $whereClause
            ORDER BY $sortColumn ASC
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'medicamentos' => $medicamentos
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar medicamentos: ' . $e->getMessage()
    ]);
}

