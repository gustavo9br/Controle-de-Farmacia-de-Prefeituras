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

$receita_id = (int)($data['receita_id'] ?? 0);
$paciente_id = (int)($data['paciente_id'] ?? 0);
$tipo_receita = trim($data['tipo_receita'] ?? 'azul');
$numero_receita = trim($data['numero_receita'] ?? '');
$data_emissao = trim($data['data_emissao'] ?? '');
$data_validade = trim($data['data_validade'] ?? '');
$medicamento_id = (int)($data['medicamento_id'] ?? 0);
$quantidade_por_retirada = (int)($data['quantidade_por_retirada'] ?? 0);
$numero_retiradas = (int)($data['numero_retiradas'] ?? 0);
$intervalo_dias = (int)($data['intervalo_dias'] ?? 30);
$observacoes = trim($data['observacoes'] ?? '');
$datas_planejadas = $data['datas_planejadas'] ?? [];
$usuario_id = (int)$_SESSION['user_id'];

// Validações
if (empty($receita_id)) {
    echo json_encode(['success' => false, 'message' => 'ID da receita não informado']);
    exit;
}

if (empty($paciente_id) || empty($data_emissao) || empty($data_validade)) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não informados']);
    exit;
}

// Se for receita azul, número é obrigatório
if ($tipo_receita === 'azul' && empty($numero_receita)) {
    echo json_encode(['success' => false, 'message' => 'Número da receita azul é obrigatório']);
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
    
    // Verificar se a receita existe
    $stmt = $conn->prepare("SELECT * FROM receitas WHERE id = ?");
    $stmt->execute([$receita_id]);
    $receita_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receita_existente) {
        throw new Exception('Receita não encontrada');
    }
    
    // Verificar se número de receita azul já existe (exceto a própria receita)
    if ($tipo_receita === 'azul' && $numero_receita !== $receita_existente['numero_receita']) {
        $stmt = $conn->prepare("SELECT id FROM receitas WHERE numero_receita = ? AND tipo_receita = 'azul' AND id != ?");
        $stmt->execute([$numero_receita, $receita_id]);
        if ($stmt->fetch()) {
            throw new Exception('Número de receita azul já existe');
        }
    }
    
    // 1. Atualizar receita
    $stmt = $conn->prepare("
        UPDATE receitas 
        SET paciente_id = ?,
            tipo_receita = ?,
            numero_receita = ?,
            data_emissao = ?,
            data_validade = ?,
            observacoes = ?,
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $paciente_id, 
        $tipo_receita, 
        $numero_receita, 
        $data_emissao, 
        $data_validade, 
        $observacoes ?: null,
        $receita_id
    ]);
    
    // 2. Buscar itens existentes para verificar se há dispensações
    $stmt = $conn->prepare("
        SELECT 
            ri.id,
            ri.medicamento_id,
            ri.quantidade_autorizada,
            ri.intervalo_dias,
            COALESCE((SELECT COUNT(*) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as tem_dispensacoes,
            COALESCE((SELECT SUM(quantidade) FROM receitas_retiradas WHERE receita_item_id = ri.id), 0) as total_retiradas
        FROM receitas_itens ri
        WHERE ri.receita_id = ?
        ORDER BY ri.id
    ");
    $stmt->execute([$receita_id]);
    $itens_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separar itens com e sem dispensações
    $itens_com_dispensacoes = [];
    $itens_sem_dispensacoes = [];
    
    foreach ($itens_existentes as $item) {
        if ($item['tem_dispensacoes'] > 0) {
            $itens_com_dispensacoes[] = $item;
        } else {
            $itens_sem_dispensacoes[] = $item;
        }
    }
    
    // 3. Atualizar intervalo_dias de TODOS os itens (com e sem dispensações) se mudou
    foreach ($itens_existentes as $item_existente) {
        if ($item_existente['intervalo_dias'] != $intervalo_dias) {
            $stmt = $conn->prepare("
                UPDATE receitas_itens 
                SET intervalo_dias = ?, atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$intervalo_dias, $item_existente['id']]);
        }
    }
    
    // 4. Atualizar quantidade_autorizada dos itens SEM dispensações
    foreach ($itens_sem_dispensacoes as $item_sem_disp) {
        if ($item_sem_disp['quantidade_autorizada'] != $quantidade_por_retirada) {
            $stmt = $conn->prepare("
                UPDATE receitas_itens 
                SET quantidade_autorizada = ?, atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantidade_por_retirada, $item_sem_disp['id']]);
        }
    }
    
    // 5. Calcular quantos itens precisamos no total
    $total_itens_necessarios = $numero_retiradas;
    $total_itens_existentes = count($itens_existentes);
    $total_itens_com_dispensacoes = count($itens_com_dispensacoes);
    $total_itens_sem_dispensacoes = count($itens_sem_dispensacoes);
    
    // 6. Se temos mais itens do que necessário, deletar apenas os sem dispensações (em excesso)
    $itens_para_deletar = 0;
    if ($total_itens_existentes > $total_itens_necessarios) {
        $itens_para_deletar = $total_itens_existentes - $total_itens_necessarios;
        // Deletar apenas itens sem dispensações, começando pelos últimos
        if ($itens_para_deletar > 0 && !empty($itens_sem_dispensacoes)) {
            $itens_para_deletar = min($itens_para_deletar, count($itens_sem_dispensacoes));
            $itens_a_deletar = array_slice($itens_sem_dispensacoes, -$itens_para_deletar);
            $ids_para_deletar = array_column($itens_a_deletar, 'id');
            if (!empty($ids_para_deletar)) {
                $placeholders = implode(',', array_fill(0, count($ids_para_deletar), '?'));
                $stmt = $conn->prepare("DELETE FROM receitas_itens WHERE id IN ($placeholders)");
                $stmt->execute($ids_para_deletar);
            }
        }
    }
    
    // 7. Calcular quantos itens novos precisam ser criados
    $itens_para_criar = max(0, $total_itens_necessarios - ($total_itens_existentes - $itens_para_deletar));
    
    // 8. Criar novos itens apenas se necessário
    $numero_item_atual = $total_itens_existentes - $itens_para_deletar + 1;
    for ($i = 0; $i < $itens_para_criar; $i++) {
        $stmt = $conn->prepare("
            INSERT INTO receitas_itens (
                receita_id, 
                medicamento_id, 
                quantidade_autorizada, 
                intervalo_dias,
                observacoes
            )
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $obs_item = "Retirada " . $numero_item_atual . " de " . $numero_retiradas;
        $stmt->execute([
            $receita_id,
            $medicamento_id,
            $quantidade_por_retirada,
            $intervalo_dias,
            $obs_item
        ]);
        
        $numero_item_atual++;
    }
    
    // 7. Se reduziu o número de retiradas, deletar itens extras sem dispensações
    if ($total_itens_com_dispensacoes > $numero_retiradas) {
        // Não podemos deletar itens com dispensações, então apenas avisar
        $itens_para_remover = $total_itens_com_dispensacoes - $numero_retiradas;
        // Não fazemos nada aqui, pois não podemos deletar itens com dispensações
    }
    
    $conn->commit();
    
    // Montar mensagem informativa
    $mensagem = 'Receita atualizada com sucesso!';
    if (!empty($itens_com_dispensacoes)) {
        $mensagem .= ' ' . count($itens_com_dispensacoes) . ' item(ns) com dispensações foram mantidos intactos.';
    }
    if ($itens_para_criar > 0) {
        $mensagem .= ' ' . $itens_para_criar . ' novo(s) item(ns) criado(s).';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $mensagem,
        'receita_id' => $receita_id,
        'numero_receita' => $numero_receita,
        'total_retiradas' => $numero_retiradas,
        'quantidade_total' => $quantidade_por_retirada * $numero_retiradas,
        'itens_mantidos' => count($itens_com_dispensacoes),
        'itens_criados' => $itens_para_criar
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao editar receita: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao editar receita: ' . $e->getMessage()
    ]);
}

