<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
$medicamento_id = isset($_GET['medicamento_id']) ? (int)$_GET['medicamento_id'] : 0;

if (empty($paciente_id) || empty($medicamento_id)) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

try {
    // Buscar itens de receitas ativas para este paciente e medicamento
    $sql = "SELECT 
                ri.id,
                ri.receita_id,
                ri.quantidade_autorizada,
                ri.quantidade_retirada,
                ri.ultima_retirada,
                ri.intervalo_dias,
                r.data_validade,
                m.nome as medicamento_nome,
                DATEDIFF(CURDATE(), ri.ultima_retirada) as dias_desde_ultima
            FROM receitas_itens ri
            INNER JOIN receitas r ON r.id = ri.receita_id
            INNER JOIN medicamentos m ON m.id = ri.medicamento_id
            WHERE r.paciente_id = :paciente_id
            AND ri.medicamento_id = :medicamento_id
            AND r.status = 'ativa'
            AND r.data_validade >= CURDATE()
            AND ri.quantidade_retirada < ri.quantidade_autorizada
            ORDER BY r.data_validade ASC, ri.id ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':paciente_id' => $paciente_id,
        ':medicamento_id' => $medicamento_id
    ]);
    
    $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar receitas que respeitam o intervalo mínimo
    $receitasDisponiveis = array_filter($receitas, function($rec) {
        // Se nunca retirou, pode retirar
        if (empty($rec['ultima_retirada'])) {
            return true;
        }
        // Se já passou o intervalo mínimo, pode retirar
        return $rec['dias_desde_ultima'] >= $rec['intervalo_dias'];
    });
    
    // Formatar datas
    foreach ($receitasDisponiveis as &$rec) {
        $rec['data_validade'] = formatarData($rec['data_validade']);
        if ($rec['ultima_retirada']) {
            $rec['ultima_retirada'] = formatarData($rec['ultima_retirada']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'receitas' => array_values($receitasDisponiveis)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar receitas: ' . $e->getMessage()
    ]);
}
