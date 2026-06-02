#!/bin/bash
# Widen platform pricing numeric fields that hold currency, percentages, and fixed fees.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CSS_FILE="$ROOT/public/assets/tenant-admin.css"
TEST_FILE="$ROOT/scripts/test/platform_pricing_input_width_static.php"

if [[ ! -f "$CSS_FILE" ]]; then
  echo "Missing expected CSS file: $CSS_FILE" >&2
  exit 1
fi

MARKER='/* ArtsFolio platform pricing economics input width fix. */'
if ! grep -Fq "$MARKER" "$CSS_FILE"; then
  cat >> "$CSS_FILE" <<'CSS'

/* ArtsFolio platform pricing economics input width fix. */
.platform-admin-page input[name*="monthly_price_dollars"],
.platform-admin-page input[name*="platform_sales_commission_percent"],
.platform-admin-page input[name*="credit_card_percentage"],
.platform-admin-page input[name*="credit_card_fixed_fee"],
.platform-admin-page input[name*="payment_processor_percent"],
.platform-admin-page input[name*="payment_processor_fixed_fee"],
.platform-admin-page input[name*="commission_percent"] {
  min-width: 9.5rem !important;
  max-width: 12rem !important;
  width: 100% !important;
}

.platform-admin-page .admin-table input[type="number"] {
  min-width: 7.5rem !important;
}
CSS
fi

cat > "$TEST_FILE" <<'PHP'
<?php
/**
 * Static regression check for platform pricing economics input sizing.
 */

$css = file_get_contents(__DIR__ . '/../../public/assets/tenant-admin.css');

$required = [
    'ArtsFolio platform pricing economics input width fix',
    'credit_card_percentage',
    'credit_card_fixed_fee',
    'platform_sales_commission_percent',
    'min-width: 9.5rem',
];

foreach ($required as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "Missing expected pricing input width marker: {$needle}\n");
        exit(1);
    }
}

echo "Platform pricing economics input width CSS is present.\n";

// End of file.
PHP

echo "Applied platform pricing economics input width fix."

# End of file.
