<?php
require_once '../../includes/auth.php';
requireRole(['admin', 'usuario']);

header('Content-Type: application/json');

$conn = getConnection();

// Receber dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$paciente_id = (int)($data['paciente_id'] ?? 0);
$numero_receita = trim($data['numero_receita'] ?? '');
$data_emissao = trim($data['data_emissao'] ?? '');
$data_validade = trim($data['data_validade'] ?? '');
$medicamento_id = (int)($data['medicamento_id'] ?? 0);
$quantidade_por_retirada = (int)($data['quantidade_por_retirada'] ?? 0);
$numero_retiradas = (int)($data['numero_retiradas'] ?? 0);
$intervalo_dias = (int)($data['intervalo_dias'] ?? 30);
$observacoes = trim($data['observacoes'] ?? '');
$usuario_id = (int)$_SESSION['user_id'];

// Validações
if (empty($paciente_id) || empty($numero_receita) || empty($data_emissao) || empty($data_validade)) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não informados']);
    exit;
}

if (empty($medicamento_id) || $quantidade_por_retirada <= 0 || $numero_retiradas <= 0) {
    echo json_encode(['success' => false, 'message' => 'Medicamento e quantidades devem ser válidos']);
    exit;
}

if ($numero_retiradas > 12) {
    echo json_encode(['success' => false, 'message' => 'Número máximo de retiradas é 12']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Verificar se número de receita já existe
    $stmt = $conn->prepare("SELECT id FROM receitas WHERE numero_receita = ?");
    $stmt->execute([$numero_receita]);
    if ($stmt->fetch()) {
        throw new Exception('Número de receita já existe');
    }
    
    // 1. Inserir receita
    $stmt = $conn->prepare("
        INSERT INTO receitas (paciente_id, numero_receita, data_emissao, data_validade, observacoes, status)
        VALUES (?, ?, ?, ?, ?, 'ativa')
    ");
    $stmt->execute([$paciente_id, $numero_receita, $data_emissao, $data_validade, $observacoes ?: null]);
    $receita_id = $conn->lastInsertId();
    
    // 2. Criar os itens da receita (múltiplas retiradas do mesmo medicamento)
    $quantidade_total = $quantidade_por_retirada * $numero_retiradas;
    
    // Criar um item de receita para cada retirada
    for ($i = 0; $i < $numero_retiradas; $i++) {
        $stmt = $conn->prepare("
            INSERT INTO receitas_itens (
                receita_id, 
                medicamento_id, 
                quantidade_autorizada, 
                quantidade_retirada, 
                intervalo_dias,
                observacoes
            )
            VALUES (?, ?, ?, 0, ?, ?)
        ");
        
        $obs_item = "Retirada " . ($i + 1) . " de " . $numero_retiradas;
        $stmt->execute([
            $receita_id,
            $medicamento_id,
            $quantidade_por_retirada,
            $intervalo_dias,
            $obs_item
        ]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Receita cadastrada com sucesso!',
        'receita_id' => $receita_id,
        'numero_receita' => $numero_receita,
        'total_retiradas' => $numero_retiradas,
        'quantidade_total' => $quantidade_total
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erro ao salvar receita: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar receita: ' . $e->getMessage()
    ]);
}
