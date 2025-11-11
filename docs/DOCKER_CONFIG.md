# Configuração Docker - Sistema Farmácia

## Informações do Ambiente

**Data:** 3 de outubro de 2025  
**Sistema:** Farmácia - Controle de Medicamentos  
**URL de Produção:** https://farmacia.laje.app  
**PhpMyAdmin:** https://phpmyadmin.guga.site  

## Configuração dos Containers

### Container PHP (Aplicação)
- **Imagem:** gustavo9br/php8.2:latest
- **Container ID:** farmacia_farmacia.1.vzuggyk0y62j50ous72o8w0lr
- **Volume:** `/root/farmacia:/var/www/html`
- **Porta Interna:** 80
- **Networks:** externa, interna

### Container MySQL (Banco de Dados)
- **Imagem:** mysql:8.0.28
- **Container ID:** mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n
- **Senha Root:** BAAE3A32D667F546851BED3777633
- **Database:** farmacia
- **Volume:** mysql_data:/var/lib/mysql
- **Networks:** interna apenas

### Container PhpMyAdmin
- **Imagem:** phpmyadmin/phpmyadmin:5.2
- **Container ID:** mysql_phpmyadmin.1.apwvpqs0vjuq7ieoplje8rp56
- **Host MySQL:** mysql (container name)
- **Networks:** externa, interna

## Docker Compose - Aplicação (farmacia)

```yaml
version: '3.8'

# 🌐 Networks
networks:
  externa:
    external: true
  interna:
    external: true

services:
  # 🐘 PHP Application - Controle de Votos
  farmacia:
    image: gustavo9br/php8.2:latest
    networks:
      - externa
      - interna
    environment:
      - TZ=America/Sao_Paulo
    volumes:
      - /root/farmacia:/var/www/html
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    deploy:
      mode: replicated
      replicas: 1
      restart_policy:
        condition: any
        delay: 5s
        max_attempts: 3
        window: 120s
      placement:
        constraints: [ node.role == manager ]
      labels:
        # 🚀 Labels do Traefik para aplicação
        traefik.enable: "true"
        traefik.docker.network: "externa"
        
        # Configuração HTTP para redirecionamento HTTPS
        traefik.http.routers.farmacia-http.rule: "Host(`farmacia.laje.app`)"
        traefik.http.routers.farmacia-http.entrypoints: "web"
        traefik.http.routers.farmacia-http.middlewares: "redirect-to-https"
        
        # Configuração HTTPS principal
        traefik.http.routers.farmacia.rule: "Host(`farmacia.laje.app`)"
        traefik.http.routers.farmacia.entrypoints: "websecure"
        traefik.http.routers.farmacia.tls: "true"
        traefik.http.routers.farmacia.tls.certresolver: "le"
        traefik.http.routers.farmacia.service: "farmacia"
        
        # Configuração do serviço
        traefik.http.services.farmacia.loadbalancer.server.port: "80"
        
        # Middleware para redirecionamento HTTPS
        traefik.http.middlewares.redirect-to-https.redirectscheme.scheme: "https"
        traefik.http.middlewares.redirect-to-https.redirectscheme.permanent: "true"
      # Recursos comentados para VPS com 22 cores e 12GB RAM
      # resources:
      #   limits:
      #     memory: 512M
      #   reservations:
      #     memory: 256M
```

## Docker Compose - MySQL + PhpMyAdmin

```yaml
version: '3.8'

# 🌐 Networks
networks:
  externa:
    external: true
  interna:
    external: true

# 💾 Volumes
volumes:
  mysql_data:
    driver: local
  phpmyadmin_data:
    driver: local

services:
  # 🗄️ MySQL Database Server (Centralizado)
  mysql:
    image: mysql:8.0.28
    networks:
      - interna
    environment:
      - MYSQL_ROOT_PASSWORD=BAAE3A32D667F546851BED3777633
      - TZ=America/Sao_Paulo
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 30s
    deploy:
      mode: replicated
      replicas: 1
      restart_policy:
        condition: any
        delay: 5s
        max_attempts: 3
        window: 120s
      placement:
        constraints: [ node.role == manager ]
      # Recursos comentados
      # resources:
      #   limits:
      #     memory: 1G
      #   reservations:
      #     memory: 512M

  # 🔧 phpMyAdmin (Administração centralizada)
  phpmyadmin:
    image: phpmyadmin/phpmyadmin:5.2
    networks:
      - externa
      - interna
    environment:
      - PMA_HOST=mysql
      - PMA_PORT=3306
      # Para produção - Login com credenciais obrigatórias
      - PMA_ARBITRARY=1
      - PMA_CONTROLHOST=mysql
      - TZ=America/Sao_Paulo
      - UPLOAD_LIMIT=64M
      - MEMORY_LIMIT=512M
      - MAX_EXECUTION_TIME=300
    volumes:
      - phpmyadmin_data:/sessions
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s
    depends_on:
      - mysql
    deploy:
      mode: replicated
      replicas: 1
      restart_policy:
        condition: any
        delay: 5s
        max_attempts: 3
        window: 120s
      placement:
        constraints: [ node.role == manager ]
      labels:
        # 🗄️ Labels do Traefik para phpMyAdmin
        traefik.enable: "true"
        traefik.docker.network: "externa"
        
        # Configuração HTTP para redirecionamento HTTPS
        traefik.http.routers.db-phpmyadmin-http.rule: "Host(`phpmyadmin.guga.site`)"
        traefik.http.routers.db-phpmyadmin-http.entrypoints: "web"
        traefik.http.routers.db-phpmyadmin-http.middlewares: "redirect-to-https"
        
        # Configuração HTTPS principal
        traefik.http.routers.db-phpmyadmin.rule: "Host(`phpmyadmin.guga.site`)"
        traefik.http.routers.db-phpmyadmin.entrypoints: "websecure"
        traefik.http.routers.db-phpmyadmin.tls: "true"
        traefik.http.routers.db-phpmyadmin.tls.certresolver: "le"
        traefik.http.routers.db-phpmyadmin.service: "db-phpmyadmin"
        
        # Configuração do serviço
        traefik.http.services.db-phpmyadmin.loadbalancer.server.port: "80"
        
        # Middleware para redirecionamento HTTPS
        traefik.http.middlewares.redirect-to-https.redirectscheme.scheme: "https"
        traefik.http.middlewares.redirect-to-https.redirectscheme.permanent: "true"
        
        # Middleware de autenticação básica (opcional - descomente se quiser dupla proteção)
        # traefik.http.routers.db-phpmyadmin.middlewares: "db-auth"
        # traefik.http.middlewares.db-auth.basicauth.users: "admin:$$2y$$10$$..."
      # Recursos comentados
      # resources:
      #   limits:
      #     memory: 256M
      #   reservations:
      #     memory: 128M
```

## Comandos Úteis

### Acesso aos Containers
```bash
# Container PHP
docker exec -it farmacia_farmacia.1.vzuggyk0y62j50ous72o8w0lr bash

# Container MySQL
docker exec -it mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n bash

# Executar queries MySQL
docker exec mysql_mysql.1.im1qwdj6kfwmtuc4vrow5mz3n mysql -u root -pBAAE3A32D667F546851BED3777633 farmacia -e "QUERY"
```

### Estrutura do Banco de Dados
- **Database:** farmacia
- **Tabelas principais:**
  - medicamentos
  - lotes  
  - pacientes
  - receitas
  - receitas_itens
  - dispensacoes
  - movimentacoes ✅ (Criada em 03/10/2025)
  - usuarios

### Problemas Resolvidos (03/10/2025)

#### ✅ Busca AJAX de medicamentos não funcionava
- **Issue:** Busca AJAX na página `admin/index.php` retornava erro SQL
- **Causa:** Query SQL usava coluna `m.apresentacao` mas a coluna correta é `m.descricao`
- **Solução:** Alterado para `m.descricao as apresentacao` em todas as APIs:
  - `/admin/api/buscar_medicamento.php`
  - `/admin/api/test_buscar_medicamento.php`
  - `/admin/paciente_historico.php`
- **Status:** ✅ RESOLVIDO

**Nota Importante:** A tabela `medicamentos` usa `descricao` como campo, não `apresentacao`. Sempre usar `m.descricao as apresentacao` nas queries.

### Próximos Passos
1. Verificar logs do container PHP
2. Testar API diretamente via navegador
3. Verificar console JavaScript para erros
4. Validar autenticação de sessão