<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$codigo_barras = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

if (empty($codigo_barras)) {
    echo json_encode(['success' => false, 'message' => 'Código de barras não informado']);
    exit;
}

try {
    // Buscar medicamento por código de barras
    $sql = "SELECT DISTINCT
                m.id,
                m.nome,
                m.descricao,
                m.estoque_atual,
                m.estoque_minimo,
                cb.codigo as codigo_barras
            FROM medicamentos m
            INNER JOIN codigos_barras cb ON cb.medicamento_id = m.id
            WHERE cb.codigo = ?
            AND m.ativo = 1
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codigo_barras]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($medicamento) {
        echo json_encode([
            'success' => true,
            'medicamento' => $medicamento
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum medicamento encontrado com este código de barras'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar medicamento: ' . $e->getMessage()
    ]);
}

