-- Adds checkout-time inventory reservations so concurrent buyers cannot oversell artwork.

CREATE TABLE IF NOT EXISTS sales_inventory_reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    artwork_id BIGINT UNSIGNED NOT NULL,
    cart_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    status ENUM('reserved','completed','released','expired') NOT NULL DEFAULT 'reserved',
    expires_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_inventory_reservation_order_artwork (order_id, artwork_id),
    KEY idx_sales_inventory_reservation_artwork_status_expiry (artwork_id, status, expires_at),
    KEY idx_sales_inventory_reservation_cart_status (cart_id, status),
    KEY idx_sales_inventory_reservation_expiry (status, expires_at),
    CONSTRAINT fk_sales_inventory_reservation_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_inventory_reservation_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_inventory_reservation_cart FOREIGN KEY (cart_id) REFERENCES sales_carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_inventory_reservation_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO background_jobs (
    tenant_id,
    job_type,
    payload,
    status,
    attempts,
    available_at,
    created_at
)
SELECT NULL,
       'sales.inventory.release_expired',
       JSON_OBJECT('interval_seconds', 300),
       'queued',
       0,
       CURRENT_TIMESTAMP,
       CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1
    FROM background_jobs
    WHERE job_type = 'sales.inventory.release_expired'
      AND status IN ('queued', 'running')
);

# End of file.
