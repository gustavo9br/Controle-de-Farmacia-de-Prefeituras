<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$sexo = isset($_GET['sexo']) ? $_GET['sexo'] : '';

try {
    $whereClauses = ['ativo = 1'];
    $params = [];
    
    // Busca por nome, CPF ou cartão SUS
    if (!empty($query)) {
        $whereClauses[] = "(nome LIKE ? OR cpf LIKE ? OR cartao_sus LIKE ?)";
        $searchParam = '%' . $query . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Filtro de sexo
    if (!empty($sexo) && in_array($sexo, ['M', 'F', 'Outro'])) {
        $whereClauses[] = "sexo = ?";
        $params[] = $sexo;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereClauses);
    
    // Buscar pacientes por nome, CPF ou cartão SUS
    if (!empty($query)) {
        $sql = "SELECT 
                    id,
                    nome,
                    cpf,
                    cartao_sus,
                    data_nascimento,
                    sexo,
                    TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade
                FROM pacientes
                $whereClause
                ORDER BY 
                    CASE 
                        WHEN nome LIKE ? THEN 1
                        WHEN cpf = ? THEN 2
                        WHEN cartao_sus = ? THEN 3
                        ELSE 4
                    END,
                    nome ASC
                LIMIT 50";
        
        $query_start = $query . '%';
        $query_exact = $query;
        $params[] = $query_start;
        $params[] = $query_exact;
        $params[] = $query_exact;
    } else {
        $sql = "SELECT 
                    id,
                    nome,
                    cpf,
                    cartao_sus,
                    data_nascimento,
                    sexo,
                    TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) AS idade
                FROM pacientes
                $whereClause
                ORDER BY nome ASC
                LIMIT 50";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pacientes' => $pacientes
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar pacientes: ' . $e->getMessage()
    ]);
}

