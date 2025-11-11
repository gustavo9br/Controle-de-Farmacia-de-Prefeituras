# Guia de Infraestrutura e Contexto do Projeto

## Visão geral do sistema
- **Projeto:** Farmácia Popular (Prefeitura) – controle de estoque e distribuição gratuita de medicamentos.
- **Objetivo:** Registrar entradas e saídas de medicamentos, acompanhar validade, estoques mínimos e gerar relatórios de movimentação.
- **Stack principal:** PHP 8.2 + MySQL 8, executando dentro de um cluster Docker Swarm com proxy reverso Traefik.

## Topologia em Docker Swarm
O ambiente é dividido em duas _stacks_ publicadas via `docker stack deploy`.

### Stack da aplicação (`farmacia`)
- **Imagem:** `gustavo9br/php8.2:latest` (Apache + PHP 8.2).
- **Rede:** participa das redes externas `externa` (exposta ao Traefik) e `interna` (tráfego interno).
- **Volume persistente:** `/root/farmacia:/var/www/html` (contém o código do projeto que você está editando aqui).
- **Variáveis de ambiente:** `TZ=America/Sao_Paulo`.
- **Healthcheck:** `curl -f http://localhost:80` a cada 30s, _timeout_ 10s, 3 tentativas, período de inicialização 40s.
- **Política de deploy:** 1 réplica, reinício em qualquer falha (delay 5s, 3 tentativas, janela 120s), restrita a _manager nodes_.
- **Integração com Traefik:**
  - Roteador HTTP redireciona `Host(farmacia.laje.app)` da entrada `web` para HTTPS via _middleware_ `redirect-to-https`.
  - Roteador HTTPS atende `farmacia.laje.app` em `websecure`, com TLS automático via `certresolver le`.
  - Serviço publicado na porta interna `80`.

### Stack de banco de dados (`farmacia-db`)
- **Serviço `mysql`:**
  - Imagem `mysql:8.0.28`.
  - Rede `interna` (acesso somente interno).
  - Volume `mysql_data` persistente montado em `/var/lib/mysql`.
  - Variáveis: `MYSQL_ROOT_PASSWORD`, `TZ`.
  - Healthcheck `mysqladmin ping` (30s, _timeout_ 10s, 5 tentativas, start period 30s).
  - Deploy com 1 réplica, mesma política de reinício da aplicação, restrita a _manager nodes_.
  - MYSQL_ROOT_PASSWORD=BAAE3A32D667F546851BED3777633
- **Serviço `phpmyadmin`:**
  - Imagem `phpmyadmin/phpmyadmin:5.2`.
  - Participa das redes `externa` e `interna`.
  - Volume `phpmyadmin_data` para sessões.
  - Variáveis principais: `PMA_HOST=mysql`, `PMA_PORT=3306`, `PMA_ARBITRARY=1`, ajustes de limite (`UPLOAD_LIMIT`, `MEMORY_LIMIT`, `MAX_EXECUTION_TIME`).
  - Healthcheck `curl -f http://localhost:80`.
  - Exposta pelo Traefik em `phpmyadmin.guga.site` com o mesmo middleware de redirecionamento HTTPS descrito acima.
  - MYSQL_ROOT_PASSWORD=BAAE3A32D667F546851BED3777633

### Redes e volumes compartilhados
- **Redes externas:** `externa` (Traefik) e `interna` (tráfego entre serviços). Ambas pré-existentes (`external: true`).
- **Volumes persistentes:** `mysql_data`, `phpmyadmin_data` e o _bind mount_ do código `/root/farmacia`.

## Banco de dados atual
Os scripts `setup_database.sql` e `config/setup.php` mantêm o esquema principal. Pontos relevantes:
- `medicamentos`: dados cadastrais com campos de preço (`preco_compra`, `preco_venda`, `margem_lucro`).
- `lotes`: controla validade e quantidade por lote; também armazena `preco_compra_unitario` e `valor_total`.
- `movimentacoes`: histórico de entradas/saídas com `valor_unitario`/`valor_total`.
- `usuarios`: autenticação e perfis (`admin`/`usuario`).
- Tabelas auxiliares em `setup_configuracoes.sql`: `fabricantes`, `tipos_medicamentos`, `categorias`, `unidades_medida`.

> **Atenção:** como se trata de uma farmácia municipal (dispensa gratuita), a próxima etapa será remover os campos de preço/valor da base e dos formulários aparados em `admin_old/`. Este documento serve como referência do estado atual antes da migração.

## Organização do código
- `admin_old/`: interface administrativa legada (Bootstrap + header padrão) ainda com campos de preço.
- `admin/`: reservado para a nova interface com menu lateral flutuante (a ser implementada agora).
- `usuario/`: fluxo para usuários-padrão registrarem saídas.
- `includes/`: autenticação, cabeçalho, rodapé compartilhados.
- `config/`: conexão (`database.php`), constantes (`config.php`) e script `setup.php` que replica o schema básico.

## Próximos passos planejados
1. **Remover metadados de preço** do schema e dos fluxos de cadastro/listagem.
2. **Construir a nova área administrativa** na pasta `admin/`, usando um componente de menu lateral flutuante reutilizável.
3. **Atualizar relatórios e movimentações** para operar apenas com quantidades, estoques e validade.

## Diretrizes de experiência e visual
- **Framework CSS:** Tailwind CSS carregado via CDN na imagem `gustavo9br/php8.2`. Utilize classes utilitárias como `rounded-3xl`, `shadow-lg`, `bg-gradient-to-br` para manter o padrão.
- **Menu lateral:** componente único localizado em `admin/includes/sidebar.php`, com estados recolhido/expandido governados por `admin/js/sidebar.js` e estilos em `css/admin_new.css`. Sempre incluir esse arquivo em novas páginas do painel.
- **Cartões e blocos de conteúdo:** use a classe `glass-card` para superfícies translúcidas com sombras suaves e cantos arredondados. Dados destacados devem aparecer em "pílulas" (`metric-pill`) ou cartões com gradiente semelhante ao dashboard de referência.
- **Layout:** páginas do admin devem criar uma "esfera" principal com cards arredondados, espaçamento amplo e sombras suaves (inspirado no mockup fornecido). Evite tabelas cruas sem estilização; se necessárias, envolva em cartões e mantenha cantos arredondados.
- **Ações rápidas:** priorize botões arredondados (`rounded-full`) e gradientes (`bg-primary-600`, `bg-accent-500`) para ações principais, com ícones em SVG inline seguindo o estilo minimalista do design de exemplo.

Mantenha este documento atualizado conforme novas alterações de infraestrutura forem aplicadas.
