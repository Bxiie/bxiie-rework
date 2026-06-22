-- Support large tenant artwork catalogs and page-local placement queries.
CREATE INDEX idx_artworks_tenant_status_id
    ON artworks (tenant_id, status, id);

CREATE INDEX idx_artworks_tenant_sale_status_id
    ON artworks (tenant_id, sale_status, id);

CREATE INDEX idx_artworks_tenant_created_id
    ON artworks (tenant_id, created_at, id);

CREATE INDEX idx_artwork_section_assignments_section_sort_artwork
    ON artwork_section_assignments (section_id, sort_order, artwork_id);

-- End of file.
