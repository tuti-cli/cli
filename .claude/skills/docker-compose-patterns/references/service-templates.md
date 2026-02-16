# Service Templates Reference

Complete service stub templates for common Docker services used in Tuti CLI stacks.

## Database Services

### PostgreSQL
```yaml
# @section: base
postgres:
  image: postgres:16-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_postgres
  environment:
    POSTGRES_DB: ${DB_DATABASE:-tuti}
    POSTGRES_USER: ${DB_USERNAME:-tuti}
    POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    PGDATA: /var/lib/postgresql/data/pgdata
  volumes:
    - postgres_data:/var/lib/postgresql/data
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-tuti} -d ${DB_DATABASE:-tuti}"]
    interval: 5s
    timeout: 5s
    retries: 5
    start_period: 10s
  networks:
    - app-network

# @section: dev
postgres:
  ports:
    - "${POSTGRES_PORT:-5432}:5432"

# @section: volumes
postgres_data:
  name: ${PROJECT_NAME}_${APP_ENV}_postgres_data
  driver: local

# @section: env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
POSTGRES_PORT=5432
```

### MySQL
```yaml
# @section: base
mysql:
  image: mysql:8.0
  container_name: ${PROJECT_NAME}_${APP_ENV}_mysql
  environment:
    MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
    MYSQL_DATABASE: ${DB_DATABASE:-tuti}
    MYSQL_USER: ${DB_USERNAME:-tuti}
    MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
  volumes:
    - mysql_data:/var/lib/mysql
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${DB_ROOT_PASSWORD:-rootsecret}"]
    interval: 5s
    timeout: 5s
    retries: 5
    start_period: 30s
  networks:
    - app-network

# @section: dev
mysql:
  ports:
    - "${MYSQL_PORT:-3306}:3306"

# @section: volumes
mysql_data:
  name: ${PROJECT_NAME}_${APP_ENV}_mysql_data
  driver: local

# @section: env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
MYSQL_PORT=3306
```

### MariaDB
```yaml
# @section: base
mariadb:
  image: mariadb:11
  container_name: ${PROJECT_NAME}_${APP_ENV}_mariadb
  environment:
    MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-rootsecret}
    MYSQL_DATABASE: ${DB_DATABASE:-tuti}
    MYSQL_USER: ${DB_USERNAME:-tuti}
    MYSQL_PASSWORD: ${DB_PASSWORD:-secret}
  volumes:
    - mariadb_data:/var/lib/mysql
  healthcheck:
    test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
    interval: 5s
    timeout: 5s
    retries: 5
    start_period: 30s
  networks:
    - app-network

# @section: dev
mariadb:
  ports:
    - "${MARIADB_PORT:-3306}:3306"

# @section: volumes
mariadb_data:
  name: ${PROJECT_NAME}_${APP_ENV}_mariadb_data
  driver: local

# @section: env
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
MARIADB_PORT=3306
```

## Cache Services

### Redis
```yaml
# @section: base
redis:
  image: redis:7-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_redis
  command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
  volumes:
    - redis_data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
redis:
  ports:
    - "${REDIS_PORT:-6379}:6379"

# @section: volumes
redis_data:
  name: ${PROJECT_NAME}_${APP_ENV}_redis_data
  driver: local

# @section: env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### Memcached
```yaml
# @section: base
memcached:
  image: memcached:1.6-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_memcached
  command: memcached -m 64
  healthcheck:
    test: ["CMD", "echo", "stats", "|", "nc", "localhost", "11211"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
memcached:
  ports:
    - "${MEMCACHED_PORT:-11211}:11211"

# @section: volumes
# No persistent volumes needed for Memcached

# @section: env
MEMCACHED_HOST=memcached
MEMCACHED_PORT=11211
```

## Search Services

### Meilisearch
```yaml
# @section: base
meilisearch:
  image: getmeili/meilisearch:v1.8
  container_name: ${PROJECT_NAME}_${APP_ENV}_meilisearch
  environment:
    MEILI_MASTER_KEY: ${MEILI_MASTER_KEY:-masterKey}
    MEILI_ENV: development
    MEILI_NO_ANALYTICS: "true"
  volumes:
    - meilisearch_data:/meili_data
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:7700/health"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
meilisearch:
  ports:
    - "${MEILISEARCH_PORT:-7700}:7700"

# @section: volumes
meilisearch_data:
  name: ${PROJECT_NAME}_${APP_ENV}_meilisearch_data
  driver: local

# @section: env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey
MEILI_MASTER_KEY=masterKey
MEILISEARCH_PORT=7700
```

### Typesense
```yaml
# @section: base
typesense:
  image: typesense/typesense:0.25
  container_name: ${PROJECT_NAME}_${APP_ENV}_typesense
  environment:
    TYPESENSE_DATA_DIR: /data
    TYPESENSE_API_KEY: ${TYPESENSE_API_KEY:-typesenseKey}
    TYPESENSE_ENABLE_CORS: "true"
  volumes:
    - typesense_data:/data
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:8108/health"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
typesense:
  ports:
    - "${TYPESENSE_PORT:-8108}:8108"

# @section: volumes
typesense_data:
  name: ${PROJECT_NAME}_${APP_ENV}_typesense_data
  driver: local

# @section: env
TYPESENSE_HOST=typesense
TYPESENSE_PORT=8108
TYPESENSE_API_KEY=typesenseKey
```

### Elasticsearch
```yaml
# @section: base
elasticsearch:
  image: docker.elastic.co/elasticsearch/elasticsearch:8.12.0
  container_name: ${PROJECT_NAME}_${APP_ENV}_elasticsearch
  environment:
    discovery.type: single-node
    ES_JAVA_OPTS: "-Xms512m -Xmx512m"
    xpack.security.enabled: "false"
  volumes:
    - elasticsearch_data:/usr/share/elasticsearch/data
  healthcheck:
    test: ["CMD-SHELL", "curl -f http://localhost:9200/_cluster/health || exit 1"]
    interval: 10s
    timeout: 10s
    retries: 5
    start_period: 30s
  networks:
    - app-network

# @section: dev
elasticsearch:
  ports:
    - "${ELASTICSEARCH_PORT:-9200}:9200"

# @section: volumes
elasticsearch_data:
  name: ${PROJECT_NAME}_${APP_ENV}_elasticsearch_data
  driver: local

# @section: env
SCOUT_DRIVER=elastic
ELASTICSEARCH_HOST=elasticsearch
ELASTICSEARCH_PORT=9200
```

## Mail Services

### Mailpit
```yaml
# @section: base
mailpit:
  image: axllent/mailpit:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_mailpit
  environment:
    MP_SMTP_BIND_ADDR: 0.0.0.0:1025
    MP_UI_BIND_ADDR: 0.0.0.0:8025
    MP_MAX_MESSAGES: 5000
  volumes:
    - mailpit_data:/data
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:8025/live"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
mailpit:
  ports:
    - "${MAILPIT_SMTP_PORT:-1025}:1025"
    - "${MAILPIT_WEB_PORT:-8025}:8025"

# @section: volumes
mailpit_data:
  name: ${PROJECT_NAME}_${APP_ENV}_mailpit_data
  driver: local

# @section: env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAILPIT_SMTP_PORT=1025
MAILPIT_WEB_PORT=8025
```

### Mailhog (Alternative)
```yaml
# @section: base
mailhog:
  image: mailhog/mailhog:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_mailhog
  healthcheck:
    test: ["CMD-SHELL", "echo | nc localhost 1025 || exit 1"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
mailhog:
  ports:
    - "${MAILHOG_SMTP_PORT:-1025}:1025"
    - "${MAILHOG_WEB_PORT:-8025}:8025"

# @section: volumes
# No persistent volumes needed for Mailhog

# @section: env
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAILHOG_SMTP_PORT=1025
MAILHOG_WEB_PORT=8025
```

## Storage Services

### MinIO
```yaml
# @section: base
minio:
  image: minio/minio:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_minio
  environment:
    MINIO_ROOT_USER: ${MINIO_ROOT_USER:-minioadmin}
    MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD:-minioadmin}
  command: server /data --console-address ":9001"
  volumes:
    - minio_data:/data
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
minio:
  ports:
    - "${MINIO_API_PORT:-9000}:9000"
    - "${MINIO_CONSOLE_PORT:-9001}:9001"

# @section: volumes
minio_data:
  name: ${PROJECT_NAME}_${APP_ENV}_minio_data
  driver: local

# @section: env
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=local
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin
MINIO_API_PORT=9000
MINIO_CONSOLE_PORT=9001
```

## Queue/Worker Services

### RabbitMQ
```yaml
# @section: base
rabbitmq:
  image: rabbitmq:3.13-management-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_rabbitmq
  environment:
    RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-guest}
    RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-guest}
  volumes:
    - rabbitmq_data:/var/lib/rabbitmq
  healthcheck:
    test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
    interval: 10s
    timeout: 10s
    retries: 5
    start_period: 30s
  networks:
    - app-network

# @section: dev
rabbitmq:
  ports:
    - "${RABBITMQ_PORT:-5672}:5672"
    - "${RABBITMQ_MGMT_PORT:-15672}:15672"

# @section: volumes
rabbitmq_data:
  name: ${PROJECT_NAME}_${APP_ENV}_rabbitmq_data
  driver: local

# @section: env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_MGMT_PORT=15672
```

## Monitoring Services

### Grafana
```yaml
# @section: base
grafana:
  image: grafana/grafana:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_grafana
  environment:
    GF_SECURITY_ADMIN_USER: ${GRAFANA_ADMIN_USER:-admin}
    GF_SECURITY_ADMIN_PASSWORD: ${GRAFANA_ADMIN_PASSWORD:-admin}
    GF_USERS_ALLOW_SIGN_UP: "false"
  volumes:
    - grafana_data:/var/lib/grafana
  healthcheck:
    test: ["CMD-SHELL", "curl -f http://localhost:3000/api/health || exit 1"]
    interval: 10s
    timeout: 10s
    retries: 5
  networks:
    - app-network

# @section: dev
grafana:
  ports:
    - "${GRAFANA_PORT:-3000}:3000"

# @section: volumes
grafana_data:
  name: ${PROJECT_NAME}_${APP_ENV}_grafana_data
  driver: local

# @section: env
GRAFANA_PORT=3000
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=admin
```

### Prometheus
```yaml
# @section: base
prometheus:
  image: prom/prometheus:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_prometheus
  command:
    - '--config.file=/etc/prometheus/prometheus.yml'
    - '--storage.tsdb.path=/prometheus'
    - '--web.enable-lifecycle'
  volumes:
    - prometheus_data:/prometheus
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:9090/-/healthy"]
    interval: 10s
    timeout: 10s
    retries: 5
  networks:
    - app-network

# @section: dev
prometheus:
  ports:
    - "${PROMETHEUS_PORT:-9090}:9090"

# @section: volumes
prometheus_data:
  name: ${PROJECT_NAME}_${APP_ENV}_prometheus_data
  driver: local

# @section: env
PROMETHEUS_PORT=9090
```

## Utility Services

### Adminer (Database UI)
```yaml
# @section: base
adminer:
  image: adminer:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_adminer
  environment:
    ADMINER_DEFAULT_SERVER: ${DB_HOST:-postgres}
    ADMINER_DESIGN: pepa-linha-dark
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:8080/"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
adminer:
  ports:
    - "${ADMINER_PORT:-8080}:8080"

# @section: volumes
# No persistent volumes needed for Adminer

# @section: env
ADMINER_PORT=8080
```

### Redis Commander
```yaml
# @section: base
redis-commander:
  image: rediscommander/redis-commander:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_redis-commander
  environment:
    REDIS_HOSTS: local:redis:6379
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:8081/"]
    interval: 5s
    timeout: 5s
    retries: 5
  depends_on:
    redis:
      condition: service_healthy
  networks:
    - app-network

# @section: dev
redis-commander:
  ports:
    - "${REDIS_COMMANDER_PORT:-8081}:8081"

# @section: volumes
# No persistent volumes needed for Redis Commander

# @section: env
REDIS_COMMANDER_PORT=8081
```

## Service Registry Template

Complete `registry.json` template:

```json
{
  "databases": {
    "postgres": {
      "file": "databases/postgres.stub",
      "name": "PostgreSQL",
      "description": "PostgreSQL 16 database server",
      "default": true
    },
    "mysql": {
      "file": "databases/mysql.stub",
      "name": "MySQL",
      "description": "MySQL 8.0 database server",
      "default": false
    },
    "mariadb": {
      "file": "databases/mariadb.stub",
      "name": "MariaDB",
      "description": "MariaDB 11 database server",
      "default": false
    }
  },
  "cache": {
    "redis": {
      "file": "cache/redis.stub",
      "name": "Redis",
      "description": "Redis 7 cache and session server",
      "default": true
    },
    "memcached": {
      "file": "cache/memcached.stub",
      "name": "Memcached",
      "description": "Memcached caching server",
      "default": false
    }
  },
  "search": {
    "meilisearch": {
      "file": "search/meilisearch.stub",
      "name": "Meilisearch",
      "description": "Meilisearch search engine (fast, typo-tolerant)",
      "default": true
    },
    "typesense": {
      "file": "search/typesense.stub",
      "name": "Typesense",
      "description": "Typesense search engine",
      "default": false
    },
    "elasticsearch": {
      "file": "search/elasticsearch.stub",
      "name": "Elasticsearch",
      "description": "Elasticsearch search and analytics engine",
      "default": false
    }
  },
  "mail": {
    "mailpit": {
      "file": "mail/mailpit.stub",
      "name": "Mailpit",
      "description": "Mailpit SMTP testing server with web UI",
      "default": true
    }
  },
  "storage": {
    "minio": {
      "file": "storage/minio.stub",
      "name": "MinIO",
      "description": "MinIO S3-compatible object storage",
      "default": true
    }
  },
  "queue": {
    "rabbitmq": {
      "file": "queue/rabbitmq.stub",
      "name": "RabbitMQ",
      "description": "RabbitMQ message broker with management UI",
      "default": true
    }
  },
  "monitoring": {
    "grafana": {
      "file": "monitoring/grafana.stub",
      "name": "Grafana",
      "description": "Grafana monitoring and visualization",
      "default": false
    },
    "prometheus": {
      "file": "monitoring/prometheus.stub",
      "name": "Prometheus",
      "description": "Prometheus time-series database",
      "default": false
    }
  },
  "utilities": {
    "adminer": {
      "file": "utilities/adminer.stub",
      "name": "Adminer",
      "description": "Database management web UI",
      "default": false
    },
    "redis-commander": {
      "file": "utilities/redis-commander.stub",
      "name": "Redis Commander",
      "description": "Redis web management UI",
      "default": false
    }
  }
}