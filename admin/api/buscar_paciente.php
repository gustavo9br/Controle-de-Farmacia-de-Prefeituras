<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario', 'medico', 'hospital']);

header('Content-Type: application/json');

$conn = getConnection();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$medicamento_id = isset($_GET['med_id']) ? (int)$_GET['med_id'] : 0;

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query vazia']);
    exit;
}

try {
    // Buscar pacientes por nome, CPF ou cartão SUS
    if ($medicamento_id > 0) {
        // Contar quantas receitas ativas o paciente tem para o medicamento selecionado
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.cpf,
                    p.cartao_sus,
                    (SELECT COUNT(DISTINCT r.id)
                     FROM receitas r
                     INNER JOIN receitas_itens ri ON ri.receita_id = r.id
                     WHERE r.paciente_id = p.id
                     AND r.status = 'ativa'
                     AND r.data_validade >= CURDATE()
                     AND ri.medicamento_id = :med_id
                     AND ri.quantidade_retirada < ri.quantidade_autorizada
                    ) as receitas_ativas
                FROM pacientes p
                WHERE p.ativo = 1
                AND (
                    p.nome LIKE :query
                    OR p.cpf LIKE :query_exact
                    OR p.cartao_sus LIKE :query_exact
                )
                ORDER BY p.nome ASC
                LIMIT 15";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':query' => '%' . $query . '%',
            ':query_exact' => $query,
            ':med_id' => $medicamento_id
        ]);
    } else {
        // Busca simples sem filtro de medicamento
        $sql = "SELECT 
                    p.id,
                    p.nome,
                    p.cpf,
                    p.cartao_sus
                FROM pacientes p
                WHERE p.ativo = 1
                AND (
                    p.nome LIKE :query
                    OR p.cpf LIKE :query_exact
                    OR p.cartao_sus LIKE :query_exact
                )
                ORDER BY p.nome ASC
                LIMIT 15";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':query' => '%' . $query . '%',
            ':query_exact' => $query
        ]);
    }
    
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
