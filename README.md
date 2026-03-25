# Event Loader System

A centralized event loading mechanism that collects events from multiple sources (REST, SOAP, FTP/CSV) into a single MySQL database, with Redis distributed locking.

## 📋 Prerequisites

Before running the project, you need to have installed:

- **Docker** (version 20.10 or newer)
- **Docker Compose** (version 2.0 or newer)

## 🚀 Getting Started

### 1. Clone the project

```bash
git clone <repository-url>
cd capslock
```

### 2. Start Docker containers

The project uses Docker Compose to run all required services (web application, MySQL, Redis).

```bash
docker compose up -d
```

This command will:
- Build the Docker image for the web application (PHP 8.1 + Apache)
- Start MySQL 8.0 server
- Start Redis 7 server
- Automatically install Composer dependencies (via Dockerfile)
- Initialize MySQL database with the required schema

**Note:** You don't need to run `composer install` manually - it's automatically executed during the Docker image build process.

### 3. Check container status

```bash
docker compose ps
```

You should see 3 running services:
- `capslock-web-1` (web application)
- `capslock-mysql-1` (MySQL database)
- `capslock-redis-1` (Redis)

### 4. View logs

To follow logs for all services:

```bash
docker compose logs -f
```

To follow logs for web application only:

```bash
docker compose logs -f web
```

## 🌐 Accessing the Application

After successful startup, the application is available at:

**http://localhost:8080**

### Available routes:

- **`GET /`** - Home page with documentation and architecture overview
- **`GET /run`** - Start the event loader process (infinite loop)
- **`GET /stop`** - Stop all running loader processes

⚠️ **Note:** The `/run` route starts an infinite loop and will not return a response until stopped. Use `/stop` to send a stop signal to all running loader instances.

## 🔧 Configuration

### Environment variables

All configuration variables are defined in `docker compose.yml`:

```yaml
# Web application
APACHE_DOCUMENT_ROOT=/var/www/html/public
DB_HOST=mysql
DB_NAME=events
DB_USER=root
DB_PASS=secret
REDIS_HOST=redis
REDIS_PORT=6379

# MySQL
MYSQL_ROOT_PASSWORD=secret
MYSQL_DATABASE=events
```

### Ports

- **Web application:** `8080` → `80`
- **MySQL:** `3306` → `3306`
- **Redis:** `6379` → `6379`

## 📊 Database

The MySQL database is automatically initialized with the following schema:

```sql
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_event_id INT UNSIGNED NOT NULL,
    source_name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    occurred_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source_name (source_name),
    INDEX idx_source_event_id (source_event_id),
    UNIQUE KEY unique_source_event (source_name, source_event_id)
);
```

### Access MySQL database

```bash
docker compose exec mysql mysql -uroot -psecret events
```

## 🔴 Redis

Redis is used for the distributed locking mechanism which provides:
- Prevention of duplicate requests to the same source
- Rate limiting (200ms minimum between requests)
- Automatic lock release after 30 seconds

### Access Redis CLI

```bash
docker compose exec redis redis-cli
```

## 🏗️ Architecture

### System components:

- **EventSourceInterface** - Contract for fetching events from remote sources
- **EventStoreInterface** - Contract for persisting events to storage
- **LockManagerInterface** - Contract for distributed locking
- **EventLoader** - Main coordinator that executes the round-robin loop
- **EventSourceCollection** - Type-safe collection of event sources

### Implemented sources:

- **RestEventSource** - REST API (JSON over HTTP)
- **SoapEventSource** - SOAP API (XML over HTTP)
- **FtpCsvEventSource** - FTP CSV files

## 🛠️ Useful Commands

### Stop the project

```bash
docker compose down
```

### Stop and remove volumes (database and Redis data)

```bash
docker compose down -v
```

### Rebuild Docker image

```bash
docker compose build --no-cache
docker compose up -d
```

### Install new Composer dependencies

```bash
docker compose exec web composer install
```

### Access web container (bash)

```bash
docker compose exec web bash
```

### View Apache logs

```bash
docker compose exec web tail -f /var/log/apache2/error.log
```

### Restart services

```bash
docker compose restart web
```

## 📁 Project Structure

```
capslock/
├── database/
│   └── schema.sql              # MySQL schema
├── public/
│   ├── .htaccess              # Apache rewrite rules
│   └── index.php              # Entry point
├── src/
│   ├── DTO/
│   │   └── EventDataDTO.php
│   ├── Loader/
│   │   └── EventLoader.php
│   ├── Lock/
│   │   ├── Contract/
│   │   │   └── LockManagerInterface.php
│   │   └── RedisLockManager.php
│   ├── Routes/
│   │   └── web.php
│   ├── Source/
│   │   ├── Contract/
│   │   │   └── EventSourceInterface.php
│   │   ├── Exception/
│   │   │   └── SourceUnavailableException.php
│   │   ├── EventSourceCollection.php
│   │   ├── RestEventSource.php
│   │   ├── SoapEventSource.php
│   │   └── FtpCsvEventSource.php
│   └── Store/
│       ├── Contract/
│       │   └── EventStoreInterface.php
│       └── MysqlEventStore.php
├── .dockerignore
├── .gitignore
├── composer.json
├── docker compose.yml
├── Dockerfile
└── README.md
```

## 🔄 How the Event Loader Works

1. The loader iterates through all configured sources in a **round-robin** loop
2. For each source, it attempts to **acquire a distributed lock** via Redis
3. If the lock is acquired, it fetches new events (using `lastEventId` as cursor)
4. If the lock is held by another instance or the **200ms cooldown** hasn't elapsed - the source is skipped
5. On source failure, the error is logged and the loader moves to the next source

### Parallel Execution

Multiple loader instances can run simultaneously (even on different servers). Redis ensures:
- **No duplicates** - only one instance can work with a source at a time
- **200ms rate limit** - minimum interval between requests to the same source
- **Crash safety** - locks auto-expire after 30 seconds if the holder dies

## 🐛 Troubleshooting

### Containers won't start

```bash
docker compose down -v
docker compose up -d --build
```

### MySQL error "Connection refused"

Wait a few seconds for MySQL to fully start:

```bash
docker compose logs mysql
```

### Redis error

Check if the Redis container is running:

```bash
docker compose ps redis
docker compose logs redis
```

### Composer dependencies not installed

```bash
docker compose exec web composer install
docker compose restart web
```

## 📝 Dependencies

- **PHP:** ^8.1
- **Slim Framework:** ^4.11
- **Slim PSR-7:** ^1.6
- **Symfony Lock:** ^6.4
- **ext-redis:** *
- **ext-pdo:** *
