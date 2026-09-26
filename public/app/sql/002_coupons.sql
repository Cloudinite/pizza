-- Migration 2: discount coupons. Applied automatically by app/lib/db.php (db_migrate).

CREATE TABLE IF NOT EXISTS coupons (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code              VARCHAR(32)  NOT NULL,
  type              VARCHAR(16)  NOT NULL DEFAULT 'percent',
  value             INT UNSIGNED NOT NULL DEFAULT 0,
  min_order_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  max_uses          INT UNSIGNED NULL,
  used_count        INT UNSIGNED NOT NULL DEFAULT 0,
  valid_from        DATE         NULL,
  valid_to          DATE         NULL,
  once_per_customer TINYINT(1)   NOT NULL DEFAULT 0,
  is_active         TINYINT(1)   NOT NULL DEFAULT 1,
  note              VARCHAR(120) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_uses (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id  INT UNSIGNED NOT NULL,
  phone_hash CHAR(64)     NOT NULL,
  order_id   INT UNSIGNED NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cu_coupon_phone (coupon_id, phone_hash),
  CONSTRAINT fk_cu_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
