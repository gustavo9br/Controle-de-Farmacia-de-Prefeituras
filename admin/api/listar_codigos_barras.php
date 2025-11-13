<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();
$medicamento_id = isset($_GET['medicamento_id']) ? (int)$_GET['medicamento_id'] : 0;

if ($medicamento_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do medicamento inválido']);
    exit;
}

try {
    $sql = "SELECT id, codigo, criado_em 
            FROM codigos_barras 
            WHERE medicamento_id = ? 
            ORDER BY codigo ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$medicamento_id]);
    $codigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'codigos' => $codigos
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar códigos de barras: ' . $e->getMessage()
    ]);
}

