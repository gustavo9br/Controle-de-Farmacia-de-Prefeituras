<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $whereClauses = ['1=1'];
    $params = [];
    
    if (!empty($query)) {
        $whereClauses[] = "(p.nome LIKE ? OR p.cpf LIKE ? OR r.numero_receita LIKE ?)";
        $searchParam = '%' . $query . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['ativa', 'finalizada', 'vencida', 'cancelada'])) {
        $whereClauses[] = "r.status = ?";
        $params[] = $status;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);
    
    // Buscar receitas
    $sql = "SELECT 
                r.*,
                p.nome as paciente_nome,
                p.cpf as paciente_cpf,
                (SELECT COUNT(*) FROM receitas_itens WHERE receita_id = r.id) as total_itens,
                (SELECT COUNT(*) FROM receitas_itens WHERE receita_id = r.id AND quantidade_retirada < quantidade_autorizada) as itens_pendentes
            FROM receitas r
            INNER JOIN pacientes p ON p.id = r.paciente_id
            $whereClause
            ORDER BY r.criado_em DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'receitas' => $receitas
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar receitas: ' . $e->getMessage()
    ]);
}

