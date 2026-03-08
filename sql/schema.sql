CREATE TABLE IF NOT EXISTS orders (
    id         VARCHAR(32) PRIMARY KEY,
    user_id    VARCHAR(64) NOT NULL,
    amount     INT         NOT NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS produced_zero (
    id         VARCHAR(32) PRIMARY KEY,
    type       VARCHAR(64) NOT NULL,
    message    JSON        NOT NULL,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id (id)
);

CREATE TABLE IF NOT EXISTS consumed_zero (
    producer_id VARCHAR(64) PRIMARY KEY,
    last_id     VARCHAR(32) NOT NULL
);

CREATE TABLE IF NOT EXISTS processed_events (
    event_id     VARCHAR(32) PRIMARY KEY,
    type         VARCHAR(64) NOT NULL,
    processed_at DATETIME    DEFAULT CURRENT_TIMESTAMP
);
