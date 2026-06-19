-- Schéma initial du livre d'or.
-- Chargé automatiquement par MariaDB au PREMIER démarrage du conteneur
-- (montage dans /docker-entrypoint-initdb.d/). Idempotent grâce à IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS messages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author     VARCHAR(60)   NOT NULL,
    body       VARCHAR(1000) NOT NULL,
    created_at DATETIME      NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_messages_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
