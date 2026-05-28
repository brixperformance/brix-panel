<?php

declare(strict_types=1);

function product_pricing_table_exists(PDO $pdo): bool
{
    static $checked = false;
    static $exists  = false;

    if ($checked) {
        return $exists;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = 'ms_product_pricing_rules'
    ");
    $stmt->execute();

    $checked = true;
    $exists  = (int) $stmt->fetchColumn() === 1;

    return $exists;
}

function product_pricing_parse_datetime(?string $value): ?int
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $timestamp = strtotime($text);
    if ($timestamp === false) {
        return null;
    }

    return $timestamp;
}

function product_pricing_rule_is_live(array $rule, ?int $now = null): bool
{
    $status = strtolower(trim((string) ($rule['mprr_status'] ?? '')));
    if (!in_array($status, ['active', 'scheduled'], true)) {
        return false;
    }

    $now     = $now ?? time();
    $startsAt = product_pricing_parse_datetime($rule['mprr_starts_at'] ?? null);
    $endsAt   = product_pricing_parse_datetime($rule['mprr_ends_at'] ?? null);

    if ($startsAt !== null && $startsAt > $now) {
        return false;
    }

    if ($endsAt !== null && $endsAt < $now) {
        return false;
    }

    return true;
}

function product_pricing_build_badge(array $rule, int $discountAmount): string
{
    $badge = trim((string) ($rule['mprr_badge_text'] ?? ''));
    if ($badge !== '') {
        return $badge;
    }

    $type  = (string) ($rule['mprr_discount_type'] ?? '');
    $value = (float) ($rule['mprr_discount_value'] ?? 0);

    if ($type === 'percent' && $value > 0) {
        $formatted = fmod($value, 1.0) === 0.0 ? number_format($value, 0) : number_format($value, 2);
        return $formatted . '% OFF';
    }

    if ($discountAmount > 0) {
        return 'IDR ' . number_format($discountAmount, 0, ',', '.') . ' OFF';
    }

    return 'SALE';
}

function product_pricing_calculate(array $product, ?array $rule = null): array
{
    $basePrice        = max(0, (int) ($product['price'] ?? 0));
    $compareAtBase    = $basePrice;
    $finalBasePrice   = $basePrice;

    if ($rule !== null && isset($rule['mprr_compare_at_price']) && $rule['mprr_compare_at_price'] !== null && $rule['mprr_compare_at_price'] !== '') {
        $compareAtBase = max($basePrice, (int) $rule['mprr_compare_at_price']);
    }

    if ($rule !== null) {
        $discountType  = (string) ($rule['mprr_discount_type'] ?? '');
        $discountValue = (float) ($rule['mprr_discount_value'] ?? 0);

        if ($discountType === 'percent') {
            $discountAmt    = (int) floor(($basePrice * max(0.0, min(100.0, $discountValue))) / 100);
            $finalBasePrice = max(0, $basePrice - $discountAmt);
        } elseif ($discountType === 'fixed') {
            $finalBasePrice = max(0, $basePrice - (int) round(max(0.0, $discountValue)));
        } elseif ($discountType === 'price_override') {
            $finalBasePrice = max(0, (int) round(max(0.0, $discountValue)));
        }
    }

    $discountAmount = max(0, $basePrice - $finalBasePrice);
    $hasDiscount    = $rule !== null && $discountAmount > 0 && $compareAtBase > $finalBasePrice;

    return [
        'rule_id'        => $rule !== null ? (int) ($rule['mprr_id'] ?? 0) : null,
        'rule'           => $rule,
        'base_price'     => $basePrice,
        'original_price' => $basePrice,
        'compare_at_price' => $hasDiscount ? $compareAtBase : null,
        'final_price'    => $finalBasePrice,
        'discount_amount' => $discountAmount,
        'has_discount'   => $hasDiscount,
        'badge_text'     => $hasDiscount ? product_pricing_build_badge($rule ?? [], $discountAmount) : '',
    ];
}

function product_pricing_fetch_active_rules_map(PDO $pdo, array $productIds): array
{
    if (empty($productIds) || !product_pricing_table_exists($pdo)) {
        return [];
    }

    $productIds   = array_values(array_unique(array_map('intval', $productIds)));
    $placeholders = [];
    $params       = [];

    foreach ($productIds as $index => $productId) {
        $key              = ':product_' . $index;
        $placeholders[]   = $key;
        $params[$key]     = $productId;
    }

    $stmt = $pdo->prepare('
        SELECT *
        FROM ms_product_pricing_rules
        WHERE mprr_mpr_id IN (' . implode(', ', $placeholders) . ")
          AND mprr_status IN ('active', 'scheduled')
        ORDER BY
            mprr_mpr_id ASC,
            CASE WHEN mprr_status = 'active' THEN 0 ELSE 1 END ASC,
            mprr_priority ASC,
            mprr_id DESC
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $now   = time();
    $map   = [];

    foreach ($rules as $rule) {
        $productId = (int) ($rule['mprr_mpr_id'] ?? 0);
        if ($productId <= 0 || isset($map[$productId])) {
            continue;
        }
        if (!product_pricing_rule_is_live($rule, $now)) {
            continue;
        }
        $map[$productId] = $rule;
    }

    return $map;
}

function product_pricing_enrich_product_row(array $product, ?array $rule = null): array
{
    $pricing = product_pricing_calculate($product, $rule);

    $product['pricing_rule']              = $rule;
    $product['pricing_rule_id']           = $pricing['rule_id'];
    $product['display_price']             = $pricing['final_price'];
    $product['original_display_price']    = $pricing['original_price'];
    $product['compare_at_display_price']  = $pricing['compare_at_price'];
    $product['discount_display_amount']   = $pricing['discount_amount'];
    $product['has_discount']              = $pricing['has_discount'];
    $product['pricing_badge_text']        = $pricing['badge_text'];

    return $product;
}

function product_pricing_enrich_product_rows(PDO $pdo, array $products): array
{
    if (empty($products)) {
        return $products;
    }

    $productIds = array_values(array_filter(array_map(static function (array $p): int {
        return (int) ($p['id'] ?? 0);
    }, $products), static fn (int $v): bool => $v > 0));

    $ruleMap = product_pricing_fetch_active_rules_map($pdo, $productIds);

    foreach ($products as &$product) {
        $pid     = (int) ($product['id'] ?? 0);
        $product = product_pricing_enrich_product_row($product, $ruleMap[$pid] ?? null);
    }
    unset($product);

    return $products;
}
