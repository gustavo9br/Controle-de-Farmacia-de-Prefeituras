# Mapeamento de Colunas - Sistema Farmácia

## ⚠️ IMPORTANTE: Diferenças entre Nomes Esperados vs Nomes Reais

### Tabela: `medicamentos`
| Nome Esperado no Código | Nome Real no Banco | Solução |
|------------------------|-------------------|---------|
| `apresentacao` | `descricao` | Usar `m.descricao as apresentacao` |

**Estrutura real:**
- id
- nome
- **descricao** ✓ (NÃO é "apresentacao")
- codigo_barras
- fabricante_id
- tipo_id
- categoria_id
- unidade_medida_id
- estoque_minimo
- estoque_atual
- preco_compra
- preco_venda
- margem_lucro
- ativo
- criado_em
- atualizado_em

---

### Tabela: `lotes`
| Nome Esperado no Código | Nome Real no Banco | Solução |
|------------------------|-------------------|---------|
| `quantidade_disponivel` | `quantidade_atual` | Usar `l.quantidade_atual` |
| `lote` | `numero_lote` | Usar `l.numero_lote` |

**Estrutura real:**
- id
- medicamento_id
- **numero_lote** ✓ (NÃO é "lote")
- data_recebimento
- data_validade
- quantidade_caixas
- quantidade_por_caixa
- quantidade_total
- **quantidade_atual** ✓ (NÃO é "quantidade_disponivel")
- preco_compra_unitario
- valor_total
- fornecedor
- nota_fiscal
- observacoes
- criado_em
- atualizado_em

---

## ✅ Arquivos Corrigidos (03/10/2025)

### APIs Corrigidas:
1. **`admin/api/buscar_medicamento.php`**
   - `m.apresentacao` → `m.descricao as apresentacao`
   - `l.quantidade_disponivel` → `l.quantidade_atual`

2. **`admin/api/test_buscar_medicamento.php`**
   - `m.apresentacao` → `m.descricao as apresentacao`
   - `l.quantidade_disponivel` → `l.quantidade_atual`

3. **`admin/api/processar_dispensacao.php`**
   - `SELECT quantidade_disponivel` → `SELECT quantidade_atual`
   - `UPDATE lotes SET quantidade_disponivel` → `UPDATE lotes SET quantidade_atual`

4. **`admin/paciente_historico.php`**
   - `m.apresentacao` → `m.descricao as apresentacao` (2 queries)
   - `l.lote` → `l.numero_lote`

---

## 📝 Regras para Futuras Queries

### ✅ CORRETO:
```sql
SELECT 
    m.descricao as apresentacao,  -- Alias para compatibilidade
    l.quantidade_atual,
    l.numero_lote
FROM medicamentos m
LEFT JOIN lotes l ON l.medicamento_id = m.id
WHERE l.quantidade_atual > 0
```

### ❌ ERRADO:
```sql
SELECT 
    m.apresentacao,  -- COLUNA NÃO EXISTE!
    l.quantidade_disponivel,  -- COLUNA NÃO EXISTE!
    l.lote  -- COLUNA NÃO EXISTE!
FROM medicamentos m
LEFT JOIN lotes l ON l.medicamento_id = m.id
```

---

## 🔍 Como Verificar Estruturas de Tabelas

```bash
# Medicamentos
docker exec mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n \
  mysql -u root -pBAAE3A32D667F546851BED3777633 farmacia \
  -e "DESCRIBE medicamentos;"

# Lotes
docker exec mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n \
  mysql -u root -pBAAE3A32D667F546851BED3777633 farmacia \
  -e "DESCRIBE lotes;"

# Todas as tabelas
docker exec mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n \
  mysql -u root -pBAAE3A32D667F546851BED3777633 farmacia \
  -e "SHOW TABLES;"
```

---

---

### Tabela: `movimentacoes`

**Estrutura criada em 03/10/2025:**
- id
- medicamento_id
- lote_id
- tipo (ENUM: entrada, saida, ajuste, devolucao, vencimento, dispensacao)
- quantidade
- quantidade_anterior
- quantidade_posterior
- motivo
- observacoes
- usuario_id
- data_movimentacao
- criado_em

---

## 📊 Status de Correção

- ✅ Busca de medicamentos (AJAX)
- ✅ Processamento de dispensação
- ✅ Histórico de pacientes
- ✅ APIs de teste/debug
- ✅ Tabela movimentacoes criada
- ✅ Botões de quantidade melhorados (tamanho e design)
- ⚠️ Verificar demais páginas conforme necessário

**Data da última atualização:** 03/10/2025