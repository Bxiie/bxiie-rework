CREATE TABLE IF NOT EXISTS artwork_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS artwork_type_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artwork_id BIGINT UNSIGNED NOT NULL,
    type_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_artwork_type_assignment (artwork_id, type_id),
    INDEX idx_artwork_type_assignment_type (type_id),
    CONSTRAINT fk_artwork_type_assignment_artwork FOREIGN KEY (artwork_id) REFERENCES artworks(id) ON DELETE CASCADE,
    CONSTRAINT fk_artwork_type_assignment_type FOREIGN KEY (type_id) REFERENCES artwork_types(id) ON DELETE CASCADE
);

INSERT INTO artwork_types (code, name, description)
VALUES
    ('portfolio_images', 'Portfolio Images', 'Images eligible for public portfolio, portfolio section, home page, and artwork detail display.'),
    ('site_images', 'Site Images', 'Published images eligible for contact, about, and background image pickers.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT IGNORE INTO artwork_type_assignments (artwork_id, type_id)
SELECT a.id, t.id
FROM artworks a
JOIN artwork_types t ON t.code = 'portfolio_images'
WHERE a.status <> 'archived';

-- End of file.
