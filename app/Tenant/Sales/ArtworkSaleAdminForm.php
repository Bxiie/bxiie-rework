<?php
/**
 * Tenant-admin artwork sale configuration helper.
 *
 * This class keeps phase-two sales catalog form rendering and persistence in
 * one place so upload and edit screens write the same phase-one sale tables.
 */

declare(strict_types=1);

namespace App\Tenant\Sales;

use PDO;
use App\Tenant\Sales\ShippingProfileService;

final class ArtworkSaleAdminForm
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Render the sales catalog controls used by both artwork upload and edit.
     *
     * @param array<string,mixed> $artwork
     */
    public function render(int $tenantId, array $artwork = []): string
    {
        $artworkId = (int) ($artwork['id'] ?? 0);
        $config = $this->saleConfig($tenantId, $artworkId, $artwork);
        $variants = $artworkId > 0 ? $this->saleVariants($tenantId, $artworkId) : [];
        if (!$variants) {
            $variants = [$this->defaultVariant($artwork)];
        }

        $saleKind = (string) $config['sale_kind'];
        $optionSchema = (string) $config['option_schema'];
        $genderSchema = (string) $config['gender_schema'];
        $shippingMode = (string) $config['shipping_mode'];
        $shippingProfileId = (int) ($config['shipping_profile_id'] ?? 0);
        $shippingProfileOptions = $this->shippingProfileOptions($tenantId, $shippingProfileId);
        $checkoutChecked = (int) $config['checkout_enabled'] === 1 ? ' checked' : '';
        $shippingRequiredChecked = (int) $config['require_shipping_address'] === 1 ? ' checked' : '';
        $price = htmlspecialchars($this->moneyFromCents($config['base_price_cents'], (string) ($artwork['price'] ?? '')), ENT_QUOTES, 'UTF-8');
        $shippingPrice = htmlspecialchars($this->moneyFromCents($config['shipping_price_cents']), ENT_QUOTES, 'UTF-8');
        $shippingAdditional = htmlspecialchars($this->moneyFromCents($config['shipping_additional_item_cents']), ENT_QUOTES, 'UTF-8');
        $simpleInventory = max(1, (int) ($variants[0]['inventory_quantity'] ?? $artwork['inventory_quantity'] ?? 1));
        $sku = htmlspecialchars((string) ($variants[0]['sku'] ?? ''), ENT_QUOTES, 'UTF-8');
        $saleModeMultipleChecked = $saleKind === 'one_off' ? '' : ' checked';
        $saleModeOneOffChecked = $saleKind === 'one_off' ? ' checked' : '';

        $kindOptions = $this->radio('sale_kind', 'one_off', 'One-off artwork', $saleKind)
            . $this->radio('sale_kind', 'limited_quantity', 'Multiple identical items', $saleKind)
            . $this->radio('sale_kind', 'variant_inventory', 'Sized / variant items', $saleKind);
        $optionOptions = $this->selectOptions([
            'none' => 'No sizing/options',
            'size_alpha' => 'Alpha sizes: XS/S/M/L/XL/XXL',
            'size_numeric' => 'Numeric sizes',
            'custom' => 'Custom labels',
        ], $optionSchema);
        $genderOptions = $this->selectOptions([
            'none' => 'No gender/fit',
            'mens' => 'Mens',
            'womens' => 'Womens',
            'unisex' => 'Unisex',
            'selectable' => 'Selectable per variant',
        ], $genderSchema);
        $shippingOptions = $this->selectOptions([
            'none' => 'No shipping charge',
            'flat_per_item' => 'Flat first item + additional item charge',
            'flat_per_order' => 'Flat per order',
            'variant' => 'Variant-specific shipping',
        ], $shippingMode);
        $variantRows = $this->variantRows($variants);

        return <<<HTML
        <fieldset class="admin-sale-config" style="margin:1rem 0;padding:1rem;border:1px solid #ccc;">
            <legend>Sales &amp; checkout</legend>
            <p>Configure how this artwork appears in the cart. These controls write the new sale catalog tables while keeping the legacy artwork price and inventory fields synchronized for the current checkout runtime.</p>
            <p><label>Price<br><input type="text" name="price" value="{$price}" placeholder="1200 or 1200.00"></label></p>
            <p><label><input type="checkbox" name="checkout_enabled" value="1"{$checkoutChecked}> Enable checkout for this artwork when sale status is For sale</label></p>
            <div class="admin-sale-grid">
                <div>
                    <strong>Product type</strong>
                    {$kindOptions}
                    <input type="hidden" name="sales_inventory_mode" value="multiple">
                    <label style="display:none"><input type="radio" name="sales_inventory_mode" value="one_off"{$saleModeOneOffChecked}> Legacy one-off</label>
                    <label style="display:none"><input type="radio" name="sales_inventory_mode" value="multiple"{$saleModeMultipleChecked}> Legacy multiple</label>
                </div>
                <label>Sizing/options<br><select name="option_schema">{$optionOptions}</select></label>
                <label>Gender / fit<br><select name="gender_schema">{$genderOptions}</select></label>
                <label>Shipping mode<br><select name="shipping_mode">{$shippingOptions}</select></label>
                <label>Shipping profile<br><select name="shipping_profile_id">{$shippingProfileOptions}</select><small>Profiles group similar items, so several sticker products can share one flat shipping charge.</small></label>
                <label>Shipping charge<br><input type="text" name="shipping_price" value="{$shippingPrice}" placeholder="0.00"></label>
                <label>Additional item shipping<br><input type="text" name="shipping_additional_item" value="{$shippingAdditional}" placeholder="0.00"></label>
                <label>Default inventory quantity<br><input type="number" name="inventory_quantity" min="1" step="1" value="{$simpleInventory}"></label>
                <label>SKU / internal code<br><input type="text" name="default_sku" value="{$sku}" maxlength="120"></label>
            </div>
            <p><label><input type="checkbox" name="require_shipping_address" value="1"{$shippingRequiredChecked}> Require a shipping address at checkout</label></p>
            <details class="admin-sale-variants" open>
                <summary>Variant rows for sizes, fits, editions, and inventory</summary>
                <p>Use one row per sellable option. For one-off or multiple identical item listings, keep one active row named Default. For shirts, shoes, prints with sizes, or other options, add one active row per size or fit.</p>
                <div style="overflow-x:auto;">
                    <table class="admin-sale-variant-table">
                        <thead>
                            <tr>
                                <th>Active</th>
                                <th>Label</th>
                                <th>Size</th>
                                <th>Gender / fit</th>
                                <th>Price override</th>
                                <th>Qty</th>
                                <th>Shipping override</th>
                                <th>Additional shipping</th>
                                <th>SKU</th>
                            </tr>
                        </thead>
                        <tbody>{$variantRows}</tbody>
                    </table>
                </div>
            </details>
        </fieldset>
HTML;
    }

    /**
     * Persist phase-two sale catalog data from the tenant-admin artwork form.
     *
     * @param array<string,mixed> $post
     */
    public function saveFromPost(int $tenantId, int $artworkId, array $post, string $saleStatus): void
    {
        if ($artworkId <= 0) {
            return;
        }

        $saleKind = $this->allowed((string) ($post['sale_kind'] ?? 'one_off'), ['one_off', 'limited_quantity', 'variant_inventory'], 'one_off');
        $optionSchema = $this->allowed((string) ($post['option_schema'] ?? 'none'), ['none', 'size_alpha', 'size_numeric', 'custom'], 'none');
        $genderSchema = $this->allowed((string) ($post['gender_schema'] ?? 'none'), ['none', 'mens', 'womens', 'unisex', 'selectable'], 'none');
        $shippingMode = $this->allowed((string) ($post['shipping_mode'] ?? 'none'), ['none', 'flat_per_item', 'flat_per_order', 'variant'], 'none');
        $shippingProfileId = max(0, (int) ($post['shipping_profile_id'] ?? 0));
        $basePriceCents = $this->parseMoneyToCents((string) ($post['price'] ?? ''));
        $shippingPriceCents = $this->parseMoneyToCents((string) ($post['shipping_price'] ?? '')) ?? 0;
        $shippingAdditionalCents = $this->parseMoneyToCents((string) ($post['shipping_additional_item'] ?? '')) ?? 0;
        $checkoutEnabled = $saleStatus === 'for_sale' && isset($post['checkout_enabled']) ? 1 : 0;
        $requireShipping = isset($post['require_shipping_address']) ? 1 : 0;

        $this->pdo->beginTransaction();
        try {
            $config = $this->pdo->prepare(
                "INSERT INTO artwork_sale_config (
                    tenant_id, artwork_id, sale_kind, option_schema, gender_schema, base_price_cents,
                    currency, shipping_mode, shipping_profile_id, shipping_price_cents, shipping_additional_item_cents,
                    checkout_enabled, require_shipping_address, created_at, updated_at
                 ) VALUES (
                    :tenant_id, :artwork_id, :sale_kind, :option_schema, :gender_schema, :base_price_cents,
                    'usd', :shipping_mode, :shipping_profile_id, :shipping_price_cents, :shipping_additional_item_cents,
                    :checkout_enabled, :require_shipping_address, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                 )
                 ON DUPLICATE KEY UPDATE
                    sale_kind = VALUES(sale_kind),
                    option_schema = VALUES(option_schema),
                    gender_schema = VALUES(gender_schema),
                    base_price_cents = VALUES(base_price_cents),
                    shipping_mode = VALUES(shipping_mode),
                    shipping_profile_id = VALUES(shipping_profile_id),
                    shipping_price_cents = VALUES(shipping_price_cents),
                    shipping_additional_item_cents = VALUES(shipping_additional_item_cents),
                    checkout_enabled = VALUES(checkout_enabled),
                    require_shipping_address = VALUES(require_shipping_address),
                    updated_at = CURRENT_TIMESTAMP"
            );
            $config->execute([
                'tenant_id' => $tenantId,
                'artwork_id' => $artworkId,
                'sale_kind' => $saleKind,
                'option_schema' => $optionSchema,
                'gender_schema' => $genderSchema,
                'base_price_cents' => $basePriceCents,
                'shipping_mode' => $shippingMode,
                'shipping_profile_id' => $shippingProfileId > 0 ? $shippingProfileId : null,
                'shipping_price_cents' => $shippingPriceCents,
                'shipping_additional_item_cents' => $shippingAdditionalCents,
                'checkout_enabled' => $checkoutEnabled,
                'require_shipping_address' => $requireShipping,
            ]);

            if ($saleKind === 'variant_inventory') {
                $this->saveVariantRows($tenantId, $artworkId, $post);
            } else {
                $this->saveDefaultVariant($tenantId, $artworkId, $post, $saleKind, $basePriceCents, $shippingPriceCents, $shippingAdditionalCents, $checkoutEnabled, $shippingProfileId);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Return legacy artwork inventory values derived from the phase-two form.
     *
     * @param array<string,mixed> $post
     * @return array{is_one_off:int,inventory_quantity:int}
     */
    public function legacyInventoryFromPost(array $post): array
    {
        $saleKind = $this->allowed((string) ($post['sale_kind'] ?? $post['sales_inventory_mode'] ?? 'one_off'), ['one_off', 'limited_quantity', 'variant_inventory', 'multiple'], 'one_off');
        if ($saleKind === 'one_off') {
            return ['is_one_off' => 1, 'inventory_quantity' => 1];
        }

        if ($saleKind === 'variant_inventory') {
            $quantity = 0;
            foreach ($this->postedVariants($post) as $row) {
                if ($this->truthy($row['is_active'] ?? null) && trim((string) ($row['variant_label'] ?? '')) !== '') {
                    $quantity += max(0, (int) ($row['inventory_quantity'] ?? 0));
                }
            }
            return ['is_one_off' => 0, 'inventory_quantity' => max(1, $quantity)];
        }

        return ['is_one_off' => 0, 'inventory_quantity' => max(1, (int) ($post['inventory_quantity'] ?? 1))];
    }

    /**
     * @param array<string,mixed> $artwork
     * @return array<string,mixed>
     */
    private function saleConfig(int $tenantId, int $artworkId, array $artwork): array
    {
        if ($artworkId > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM artwork_sale_config WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $isOneOff = (int) ($artwork['is_one_off'] ?? 1) === 1;
        return [
            'sale_kind' => $isOneOff ? 'one_off' : 'limited_quantity',
            'option_schema' => 'none',
            'gender_schema' => 'none',
            'base_price_cents' => $this->parseMoneyToCents((string) ($artwork['price'] ?? '')),
            'shipping_mode' => 'none',
            'shipping_profile_id' => null,
            'shipping_price_cents' => 0,
            'shipping_additional_item_cents' => 0,
            'checkout_enabled' => (string) ($artwork['sale_status'] ?? 'nfs') === 'for_sale' ? 1 : 0,
            'require_shipping_address' => 1,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function saleVariants(int $tenantId, int $artworkId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM artwork_sale_variants WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $artwork
     * @return array<string,mixed>
     */
    private function defaultVariant(array $artwork): array
    {
        return [
            'id' => 0,
            'variant_label' => 'Default',
            'size_value' => '',
            'gender_value' => 'not_applicable',
            'price_cents' => $this->parseMoneyToCents((string) ($artwork['price'] ?? '')),
            'shipping_price_cents' => null,
            'shipping_additional_item_cents' => null,
            'inventory_quantity' => max(1, (int) ($artwork['inventory_quantity'] ?? 1)),
            'sku' => '',
            'is_active' => 1,
        ];
    }

    /**
     * @param list<array<string,mixed>> $variants
     */
    private function variantRows(array $variants): string
    {
        $rows = '';
        $maxRows = max(8, count($variants) + 3);
        for ($i = 0; $i < $maxRows; $i++) {
            $variant = $variants[$i] ?? [
                'id' => 0,
                'variant_label' => '',
                'size_value' => '',
                'gender_value' => 'not_applicable',
                'price_cents' => null,
                'shipping_price_cents' => null,
                'shipping_additional_item_cents' => null,
                'inventory_quantity' => 0,
                'sku' => '',
                'is_active' => 0,
            ];
            $id = (int) ($variant['id'] ?? 0);
            $checked = (int) ($variant['is_active'] ?? 0) === 1 ? ' checked' : '';
            $label = htmlspecialchars((string) ($variant['variant_label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $size = htmlspecialchars((string) ($variant['size_value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $gender = (string) ($variant['gender_value'] ?? 'not_applicable');
            $price = htmlspecialchars($this->moneyFromCents($variant['price_cents']), ENT_QUOTES, 'UTF-8');
            $shipping = htmlspecialchars($this->moneyFromCents($variant['shipping_price_cents']), ENT_QUOTES, 'UTF-8');
            $shippingAdditional = htmlspecialchars($this->moneyFromCents($variant['shipping_additional_item_cents']), ENT_QUOTES, 'UTF-8');
            $quantity = max(0, (int) ($variant['inventory_quantity'] ?? 0));
            $sku = htmlspecialchars((string) ($variant['sku'] ?? ''), ENT_QUOTES, 'UTF-8');
            $genderOptions = $this->selectOptions([
                'not_applicable' => 'N/A',
                'unisex' => 'Unisex',
                'mens' => 'Mens',
                'womens' => 'Womens',
            ], $gender);

            $rows .= <<<HTML
                            <tr>
                                <td><input type="hidden" name="sale_variants[{$i}][id]" value="{$id}"><input type="checkbox" name="sale_variants[{$i}][is_active]" value="1"{$checked}></td>
                                <td><input type="text" name="sale_variants[{$i}][variant_label]" value="{$label}" placeholder="Default, Unisex XL, Mens 10.5"></td>
                                <td><input type="text" name="sale_variants[{$i}][size_value]" value="{$size}" placeholder="XL or 10.5"></td>
                                <td><select name="sale_variants[{$i}][gender_value]">{$genderOptions}</select></td>
                                <td><input type="text" name="sale_variants[{$i}][price]" value="{$price}" placeholder="blank = base"></td>
                                <td><input type="number" name="sale_variants[{$i}][inventory_quantity]" min="0" step="1" value="{$quantity}"></td>
                                <td><input type="text" name="sale_variants[{$i}][shipping_price]" value="{$shipping}" placeholder="blank = default"></td>
                                <td><input type="text" name="sale_variants[{$i}][shipping_additional_item]" value="{$shippingAdditional}" placeholder="blank = default"></td>
                                <td><input type="text" name="sale_variants[{$i}][sku]" value="{$sku}" maxlength="120"></td>
                            </tr>
HTML;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $post
     */
    private function saveDefaultVariant(int $tenantId, int $artworkId, array $post, string $saleKind, ?int $basePriceCents, int $shippingPriceCents, int $shippingAdditionalCents, int $checkoutEnabled, int $shippingProfileId = 0): void
    {
        $this->pdo->prepare('UPDATE artwork_sale_variants SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id')
            ->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId]);

        $stmt = $this->pdo->prepare(
            "SELECT id FROM artwork_sale_variants WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id ORDER BY CASE WHEN variant_label = 'Default' THEN 0 ELSE 1 END, id ASC LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);
        $quantity = $saleKind === 'one_off' ? 1 : max(1, (int) ($post['inventory_quantity'] ?? 1));
        $sku = trim((string) ($post['default_sku'] ?? '')) ?: null;

        if ($existingId > 0) {
            $update = $this->pdo->prepare(
                "UPDATE artwork_sale_variants
                 SET sku = :sku,
                     variant_label = 'Default',
                     size_value = NULL,
                     gender_value = 'not_applicable',
                     shipping_profile_id = :shipping_profile_id,
                     price_cents = :price_cents,
                     shipping_price_cents = :shipping_price_cents,
                     shipping_additional_item_cents = :shipping_additional_item_cents,
                     inventory_quantity = :inventory_quantity,
                     sort_order = 100,
                     is_active = :is_active,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND tenant_id = :tenant_id AND artwork_id = :artwork_id"
            );
            $update->execute([
                'sku' => $sku,
                'shipping_profile_id' => $shippingProfileId > 0 ? $shippingProfileId : null,
                'price_cents' => $basePriceCents,
                'shipping_price_cents' => $shippingPriceCents,
                'shipping_additional_item_cents' => $shippingAdditionalCents,
                'inventory_quantity' => $quantity,
                'is_active' => $checkoutEnabled,
                'id' => $existingId,
                'tenant_id' => $tenantId,
                'artwork_id' => $artworkId,
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO artwork_sale_variants (
                tenant_id, artwork_id, sku, variant_label, size_value, gender_value, shipping_profile_id, price_cents,
                shipping_price_cents, shipping_additional_item_cents, inventory_quantity, sort_order, is_active,
                created_at, updated_at
             ) VALUES (
                :tenant_id, :artwork_id, :sku, 'Default', NULL, 'not_applicable', :shipping_profile_id, :price_cents,
                :shipping_price_cents, :shipping_additional_item_cents, :inventory_quantity, 100, :is_active,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             )"
        );
        $insert->execute([
            'tenant_id' => $tenantId,
            'artwork_id' => $artworkId,
            'sku' => $sku,
            'price_cents' => $basePriceCents,
            'shipping_price_cents' => $shippingPriceCents,
            'shipping_additional_item_cents' => $shippingAdditionalCents,
            'inventory_quantity' => $quantity,
            'is_active' => $checkoutEnabled,
        ]);
    }

    /**
     * @param array<string,mixed> $post
     */
    private function saveVariantRows(int $tenantId, int $artworkId, array $post): void
    {
        $this->pdo->prepare('UPDATE artwork_sale_variants SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id')
            ->execute(['tenant_id' => $tenantId, 'artwork_id' => $artworkId]);

        $sort = 100;
        foreach ($this->postedVariants($post) as $row) {
            $label = trim((string) ($row['variant_label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $variantId = max(0, (int) ($row['id'] ?? 0));
            $size = trim((string) ($row['size_value'] ?? '')) ?: null;
            $gender = $this->allowed((string) ($row['gender_value'] ?? 'not_applicable'), ['mens', 'womens', 'unisex', 'not_applicable'], 'not_applicable');
            $priceCents = $this->parseMoneyToCents((string) ($row['price'] ?? ''));
            $shippingPriceCents = $this->parseMoneyToCents((string) ($row['shipping_price'] ?? ''));
            $shippingAdditionalCents = $this->parseMoneyToCents((string) ($row['shipping_additional_item'] ?? ''));
            $quantity = max(0, (int) ($row['inventory_quantity'] ?? 0));
            $sku = trim((string) ($row['sku'] ?? '')) ?: null;
            $isActive = $this->truthy($row['is_active'] ?? null) ? 1 : 0;

            if ($variantId > 0) {
                $update = $this->pdo->prepare(
                    "UPDATE artwork_sale_variants
                     SET sku = :sku,
                         variant_label = :variant_label,
                         size_value = :size_value,
                         gender_value = :gender_value,
                         price_cents = :price_cents,
                         shipping_price_cents = :shipping_price_cents,
                         shipping_additional_item_cents = :shipping_additional_item_cents,
                         inventory_quantity = :inventory_quantity,
                         sort_order = :sort_order,
                         is_active = :is_active,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND tenant_id = :tenant_id AND artwork_id = :artwork_id"
                );
                $update->execute([
                    'sku' => $sku,
                    'variant_label' => $label,
                    'size_value' => $size,
                    'gender_value' => $gender,
                    'price_cents' => $priceCents,
                    'shipping_price_cents' => $shippingPriceCents,
                    'shipping_additional_item_cents' => $shippingAdditionalCents,
                    'inventory_quantity' => $quantity,
                    'sort_order' => $sort,
                    'is_active' => $isActive,
                    'id' => $variantId,
                    'tenant_id' => $tenantId,
                    'artwork_id' => $artworkId,
                ]);
            } else {
                $insert = $this->pdo->prepare(
                    "INSERT INTO artwork_sale_variants (
                        tenant_id, artwork_id, sku, variant_label, size_value, gender_value, price_cents,
                        shipping_price_cents, shipping_additional_item_cents, inventory_quantity, sort_order, is_active,
                        created_at, updated_at
                     ) VALUES (
                        :tenant_id, :artwork_id, :sku, :variant_label, :size_value, :gender_value, :price_cents,
                        :shipping_price_cents, :shipping_additional_item_cents, :inventory_quantity, :sort_order, :is_active,
                        CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                     )"
                );
                $insert->execute([
                    'tenant_id' => $tenantId,
                    'artwork_id' => $artworkId,
                    'sku' => $sku,
                    'variant_label' => $label,
                    'size_value' => $size,
                    'gender_value' => $gender,
                    'price_cents' => $priceCents,
                    'shipping_price_cents' => $shippingPriceCents,
                    'shipping_additional_item_cents' => $shippingAdditionalCents,
                    'inventory_quantity' => $quantity,
                    'sort_order' => $sort,
                    'is_active' => $isActive,
                ]);
            }

            $sort += 10;
        }
    }

    /**
     * @param array<string,mixed> $post
     * @return list<array<string,mixed>>
     */
    private function postedVariants(array $post): array
    {
        $rows = $post['sale_variants'] ?? [];
        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param array<string,string> $options
     */
    private function selectOptions(array $options, string $current): string
    {
        $html = '';
        foreach ($options as $value => $label) {
            $selected = $value === $current ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html;
    }

    private function radio(string $name, string $value, string $label, string $current): string
    {
        $checked = $value === $current ? ' checked' : '';
        return '<label style="display:block;margin:.25rem 0;"><input type="radio" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $checked . '> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    }

    /**
     * @param list<string> $allowed
     */
    private function allowed(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true);
    }

    private function parseMoneyToCents(string $value): ?int
    {
        $value = trim(str_replace([',', '$'], '', $value));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        return (int) round(((float) $value) * 100);
    }

    private function moneyFromCents(mixed $cents, string $fallback = ''): string
    {
        if ($cents === null || $cents === '') {
            return $fallback;
        }
        return number_format(((int) $cents) / 100, 2, '.', '');
    }
}

// End of file.
