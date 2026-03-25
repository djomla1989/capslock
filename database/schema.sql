CREATE TABLE IF NOT EXISTS events (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
