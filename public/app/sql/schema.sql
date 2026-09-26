-- Pizza Slice Pezinok – MariaDB schema (MariaDB 10.3+ / MySQL 5.7+)
-- Import via phpMyAdmin, or let /install/ run it for you.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(60)  NOT NULL,
  name        VARCHAR(80)  NOT NULL,
  subtitle    VARCHAR(120) NOT NULL DEFAULT '',
  icon        VARCHAR(10)  NOT NULL DEFAULT 'slice',
  is_special  TINYINT(1)   NOT NULL DEFAULT 0,
  is_visible  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id    INT UNSIGNED NOT NULL,
  name           VARCHAR(100) NOT NULL,
  description    VARCHAR(300) NOT NULL DEFAULT '',
  badge          VARCHAR(30)  NOT NULL DEFAULT '',
  unit_label     VARCHAR(30)  NOT NULL DEFAULT '',
  price_cents    INT UNSIGNED NOT NULL,
  image          VARCHAR(120) NULL,
  allow_toppings TINYINT(1)   NOT NULL DEFAULT 0,
  is_available   TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_item_cat (category_id, sort_order),
  CONSTRAINT fk_item_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS toppings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(60)  NOT NULL,
  price_cents  INT UNSIGNED NOT NULL,
  is_available TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code            CHAR(6)      NOT NULL,
  view_token_hash CHAR(64)     NOT NULL,
  order_date      DATE         NOT NULL,
  daily_no        SMALLINT UNSIGNED NOT NULL,
  status          VARCHAR(12)  NOT NULL DEFAULT 'new',
  fulfillment     VARCHAR(10)  NOT NULL DEFAULT 'pickup',
  requested_time  CHAR(5)      NULL,
  payment_method  VARCHAR(10)  NOT NULL,
  customer_enc    TEXT         NULL,
  item_count      SMALLINT UNSIGNED NOT NULL,
  subtotal_cents  INT UNSIGNED NOT NULL,
  delivery_cents  INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents     INT UNSIGNED NOT NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  anonymized_at   DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_code (code),
  UNIQUE KEY uq_order_daily (order_date, daily_no),
  KEY idx_order_status (status, created_at),
  KEY idx_order_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  toppings    VARCHAR(500) NOT NULL DEFAULT '',
  unit_cents  INT UNSIGNED NOT NULL,
  qty         SMALLINT UNSIGNED NOT NULL,
  line_cents  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(50) NOT NULL,
  v TEXT        NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL,
  last_login_at DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  selector       CHAR(24)     NOT NULL,
  validator_hash CHAR(64)     NOT NULL,
  user_id        INT UNSIGNED NOT NULL,
  user_agent     VARCHAR(200) NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL,
  last_seen_at   DATETIME     NOT NULL,
  expires_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sess_selector (selector),
  KEY idx_sess_expires (expires_at),
  CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket     VARCHAR(20) NOT NULL,
  ident      CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rl (bucket, ident, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
