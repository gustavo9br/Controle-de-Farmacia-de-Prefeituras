<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

try {
    $paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
    
    if (empty($paciente_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID do paciente não informado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $conn = getConnection();
    
    // Buscar itens pendentes de receitas ativas para o paciente
    $sql = "
        SELECT 
            ri.id as receita_item_id,
            ri.receita_id, 
            ri.quantidade_autorizada,
            ri.intervalo_dias,
            r.data_validade, 
            r.data_emissao,
            r.numero_receita,
            m.nome as medicamento_nome, 
            m.descricao as apresentacao, 
            m.id as medicamento_id,
            COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as total_retiradas,
            (ri.quantidade_autorizada - COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0)) as quantidade_pendente,
            (SELECT MAX(rr.criado_em) 
             FROM receitas_retiradas rr 
             WHERE rr.receita_item_id = ri.id) as ultima_retirada,
            (SELECT MIN(rrp.data_planejada)
             FROM receitas_retiradas_planejadas rrp
             WHERE rrp.receita_item_id = ri.id
             AND NOT EXISTS (
                 SELECT 1 FROM receitas_retiradas rr
                 WHERE rr.receita_item_id = rrp.receita_item_id
                 AND (rr.data_planejada = rrp.data_planejada OR (rr.data_planejada IS NULL AND DATE(rr.criado_em) = rrp.data_planejada))
             )
            ) as proxima_retirada
        FROM receitas_itens ri
        INNER JOIN receitas r ON r.id = ri.receita_id
        INNER JOIN medicamentos m ON m.id = ri.medicamento_id
        WHERE r.paciente_id = ?
        AND r.status = 'ativa'
        AND r.data_validade >= CURDATE()
        AND COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) < ri.quantidade_autorizada
        ORDER BY r.data_validade ASC, ri.id ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$paciente_id]);
    $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar datas
    foreach ($pendentes as &$item) {
        $item['data_validade'] = $item['data_validade'] ? date('d/m/Y', strtotime($item['data_validade'])) : null;
        $item['proxima_retirada'] = $item['proxima_retirada'] ? date('d/m/Y', strtotime($item['proxima_retirada'])) : null;
    }
    unset($item);
    
    echo json_encode([
        'success' => true,
        'pendentes' => $pendentes,
        'total' => count($pendentes)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("API ERROR buscar_medicamentos_pendentes_paciente: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar medicamentos pendentes: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
