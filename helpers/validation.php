<?php
// helpers/validation.php
// Validasi Syntactic & Semantic data server-side

/**
 * Validasi integer positif > 0
 */
function validate_positive_int($value, $field_name) {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false || $filtered <= 0) {
        return "$field_name harus berupa angka bulat lebih dari 0.";
    }
    return null;
}

/**
 * Validasi float/desimal non-negatif >= 0
 */
function validate_non_negative_number($value, $field_name) {
    if (!is_numeric($value) || (float)$value < 0) {
        return "$field_name tidak boleh bernilai negatif.";
    }
    return null;
}

/**
 * Validasi string tidak boleh kosong
 */
function validate_required_string($value, $field_name, $min_len = 1, $max_len = 255) {
    $trimmed = trim((string)$value);
    if (strlen($trimmed) < $min_len) {
        return "$field_name tidak boleh kosong (minimal $min_len karakter).";
    }
    if (strlen($trimmed) > $max_len) {
        return "$field_name maksimal $max_len karakter.";
    }
    return null;
}

/**
 * Validasi sorting column dengan allowlist (Slide 16)
 */
function validate_sort_column($column, array $allowed_columns, $default = 'id') {
    if (in_array($column, $allowed_columns, true)) {
        return $column;
    }
    return $default;
}

/**
 * Validasi sorting order ASC / DESC
 */
function validate_sort_order($order, $default = 'ASC') {
    $order = strtoupper((string)$order);
    return in_array($order, ['ASC', 'DESC'], true) ? $order : $default;
}
