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
        $whereClauses[] = "(p.nome LIKE ? OR p.cpf LIKE ? OR p.cartao_sus LIKE ? OR REPLACE(p.cartao_sus, ' ', '') LIKE ? OR r.numero_receita LIKE ?)";
        $searchParam = '%' . $query . '%';
        $searchParamClean = '%' . preg_replace('/[^0-9]/', '', $query) . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParamClean;
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
                p.id as paciente_id,
                p.nome as paciente_nome,
                p.cpf as paciente_cpf,
                (SELECT COUNT(*) FROM receitas_itens WHERE receita_id = r.id) as total_itens,
                (SELECT COUNT(*) FROM receitas_itens ri 
                 WHERE ri.receita_id = r.id 
                 AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada) as itens_pendentes
            FROM receitas r
            INNER JOIN pacientes p ON p.id = r.paciente_id
            $whereClause
            ORDER BY r.criado_em DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar datas planejadas e itens pendentes para cada receita
    foreach ($receitas as &$receita) {
        // Adicionar paciente_id
        $receita['paciente_id'] = $receita['paciente_id'] ?? null;
        
        // Buscar datas planejadas
        $sqlDatas = "SELECT 
                        rrp.data_planejada,
                        rrp.numero_retirada,
                        ri.id as receita_item_id,
                        ri.medicamento_id,
                        ri.quantidade_autorizada,
                        m.nome as medicamento_nome,
                        COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as retiradas_feitas,
                        CASE WHEN EXISTS (
                            SELECT 1 FROM receitas_retiradas rr 
                            WHERE rr.receita_item_id = rrp.receita_item_id 
                            AND (rr.data_planejada = rrp.data_planejada OR (rr.data_planejada IS NULL AND DATE(rr.criado_em) = rrp.data_planejada))
                        ) THEN 1 ELSE 0 END as ja_dispensada
                     FROM receitas_retiradas_planejadas rrp
                     INNER JOIN receitas_itens ri ON ri.id = rrp.receita_item_id
                     INNER JOIN medicamentos m ON m.id = ri.medicamento_id
                     WHERE ri.receita_id = ?
                     ORDER BY rrp.data_planejada ASC";
        
        $stmtDatas = $conn->prepare($sqlDatas);
        $stmtDatas->execute([$receita['id']]);
        $receita['datas_planejadas'] = $stmtDatas->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar primeiro item pendente
        $sqlItens = "SELECT 
                        ri.id,
                        ri.medicamento_id,
                        ri.quantidade_autorizada,
                        m.nome as medicamento_nome
                     FROM receitas_itens ri
                     INNER JOIN medicamentos m ON m.id = ri.medicamento_id
                     WHERE ri.receita_id = ?
                       AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada
                     LIMIT 1";
        
        $stmtItens = $conn->prepare($sqlItens);
        $stmtItens->execute([$receita['id']]);
        $receita['primeiro_item_pendente'] = $stmtItens->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    unset($receita);
    
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

