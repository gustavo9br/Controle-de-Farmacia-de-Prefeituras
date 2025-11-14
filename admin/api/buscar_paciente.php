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
    // Normalizar query para busca case-insensitive
    $query_lower = strtolower($query);
    $query_pattern = '%' . $query . '%';
    $query_lower_pattern = '%' . $query_lower . '%';
    $query_clean = '%' . preg_replace('/[^0-9]/', '', $query) . '%';
    
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
                     AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada
                    ) as receitas_ativas
                FROM pacientes p
                WHERE p.ativo = 1
                AND (
                    LOWER(p.nome) LIKE :query_lower
                    OR p.nome LIKE :query
                    OR p.cpf LIKE :query
                    OR REPLACE(REPLACE(p.cpf, '.', ''), '-', '') LIKE :query_clean
                    OR p.cartao_sus LIKE :query
                    OR REPLACE(p.cartao_sus, ' ', '') LIKE :query_clean
                )
                ORDER BY 
                    CASE 
                        WHEN LOWER(p.nome) LIKE :query_start THEN 1
                        WHEN LOWER(p.nome) LIKE :query_lower THEN 2
                        ELSE 3
                    END,
                    p.nome ASC
                LIMIT 15";
        
        $query_start = $query_lower . '%';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':query' => $query_pattern,
            ':query_lower' => $query_lower_pattern,
            ':query_start' => $query_start,
            ':query_clean' => $query_clean,
            ':med_id' => $medicamento_id
        ]);
    } else {
        // Busca simples sem filtro de medicamento
        // Verificar se a query é numérica (CPF/SUS) ou texto (nome)
        $isNumeric = preg_match('/^[0-9\s\.\-]+$/', $query);
        
        if ($isNumeric) {
            // Se for numérico, buscar por CPF e SUS
            $sql = "SELECT 
                        p.id,
                        p.nome,
                        p.cpf,
                        p.cartao_sus
                    FROM pacientes p
                    WHERE p.ativo = 1
                    AND (
                        p.cpf LIKE :query
                        OR REPLACE(REPLACE(p.cpf, '.', ''), '-', '') LIKE :query_clean
                        OR p.cartao_sus LIKE :query
                        OR REPLACE(p.cartao_sus, ' ', '') LIKE :query_clean
                    )
                    ORDER BY p.nome ASC
                    LIMIT 15";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':query' => $query_pattern,
                ':query_clean' => $query_clean
            ]);
        } else {
            // Se for texto, buscar principalmente por nome
            $sql = "SELECT 
                        p.id,
                        p.nome,
                        p.cpf,
                        p.cartao_sus
                    FROM pacientes p
                    WHERE p.ativo = 1
                    AND (
                        LOWER(p.nome) LIKE :query_lower
                        OR p.nome LIKE :query
                    )
                    ORDER BY 
                        CASE 
                            WHEN LOWER(p.nome) LIKE :query_start THEN 1
                            WHEN LOWER(p.nome) LIKE :query_lower THEN 2
                            ELSE 3
                        END,
                        p.nome ASC
                    LIMIT 15";
            
            $query_start = $query_lower . '%';
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':query' => $query_pattern,
                ':query_lower' => $query_lower_pattern,
                ':query_start' => $query_start
            ]);
        }
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
