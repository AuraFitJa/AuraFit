<?php

declare(strict_types=1);

if (!function_exists('off_table_exists')) {
  function off_table_exists(string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) {
      return $cache[$table];
    }
    try {
      $row = Database::exec('SHOW TABLES LIKE ?', [$table])->fetch();
      $cache[$table] = (bool)$row;
      return $cache[$table];
    } catch (Throwable $e) {
      $cache[$table] = false;
      return false;
    }
  }
}

if (!function_exists('off_table_columns')) {
  function off_table_columns(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) {
      return $cache[$table];
    }
    try {
      $rows = Database::exec('SHOW COLUMNS FROM ' . $table)->fetchAll();
      $cols = [];
      foreach ((array)$rows as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
          $cols[$name] = true;
        }
      }
      $cache[$table] = $cols;
      return $cols;
    } catch (Throwable $e) {
      $cache[$table] = [];
      return [];
    }
  }
}

if (!function_exists('off_pick_column')) {
  function off_pick_column(array $available, array $candidates): ?string {
    foreach ($candidates as $candidate) {
      if (isset($available[$candidate])) {
        return $candidate;
      }
    }
    return null;
  }
}

if (!function_exists('off_normalize_query')) {
  function off_normalize_query(string $q): string {
    $q = trim(mb_strtolower($q));
    $q = preg_replace('/\s+/', ' ', $q) ?: '';
    return $q;
  }
}

if (!function_exists('off_http_get')) {
  function off_user_agent(): string {
    $email = trim((string)(getenv('OFF_CONTACT_EMAIL') ?: ''));
    if ($email === '' && defined('OPENFOODFACTS_CONTACT_EMAIL')) {
      $email = trim((string)OPENFOODFACTS_CONTACT_EMAIL);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $email = 'aurafit.team@gmail.com';
    }
    return 'AuraFit/1.1 (OpenFoodFacts integration; contact: ' . $email . ')';
  }

  function off_http_get_single(string $url): array {
    $body = false;
    $err = '';
    $status = 0;

    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
          'Accept: application/json',
          'User-Agent: ' . off_user_agent(),
        ],
      ]);

      $body = curl_exec($ch);
      $err = curl_error($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      curl_close($ch);
    }

    if ($body === false && ini_get('allow_url_fopen')) {
      $ctx = stream_context_create([
        'http' => [
          'method' => 'GET',
          'timeout' => 12,
          'header' => "Accept: application/json\r\nUser-Agent: " . off_user_agent() . "\r\n",
        ],
      ]);
      $body = @file_get_contents($url, false, $ctx);
      $status = 0;
      if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\\s(\\d{3})\\s/', (string)$http_response_header[0], $m)) {
          $status = (int)$m[1];
        }
      }
    }

    if ($body === false || $status >= 400) {
      throw new RuntimeException('Errore Open Food Facts: ' . ($err ?: ('HTTP ' . $status)));
    }

    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
      throw new RuntimeException('Risposta Open Food Facts non valida.');
    }

    return $json;
  }

  function off_http_get(string $url): array {
    try {
      return off_http_get_single($url);
    } catch (Throwable $e) {
      $message = (string)$e->getMessage();
      $proxyBlocked = stripos($message, 'CONNECT tunnel failed') !== false
        || stripos($message, 'HTTP 403') !== false;
      $retryUrls = [];

      if (strpos($url, 'openfoodfacts.org') !== false) {
        $retryUrls[] = str_replace('://world.openfoodfacts.org/', '://it.openfoodfacts.org/', $url);
        $retryUrls[] = str_replace('://it.openfoodfacts.org/', '://world.openfoodfacts.org/', $url);
      }

      if ($proxyBlocked && strpos($url, 'https://') === 0) {
        $retryUrls[] = preg_replace('/^https:\\/\\//', 'http://', $url);
        foreach ($retryUrls as $candidateUrl) {
          $retryUrls[] = preg_replace('/^https:\\/\\//', 'http://', (string)$candidateUrl);
        }
      }

      $retryUrls = array_values(array_unique(array_filter($retryUrls, static function ($candidate) use ($url) {
        return is_string($candidate) && $candidate !== '' && $candidate !== $url;
      })));

      foreach ($retryUrls as $retryUrl) {
        try {
          return off_http_get_single((string)$retryUrl);
        } catch (Throwable $retryError) {
          if (!$proxyBlocked) {
            break;
          }
        }
      }

      throw $e;
    }
  }
}

if (!function_exists('off_to_float')) {
  function off_to_float($value): ?float {
    if ($value === null || $value === '') {
      return null;
    }
    if (!is_numeric($value)) {
      return null;
    }
    return (float)$value;
  }
}

if (!function_exists('off_normalize_product')) {
  function off_normalize_product(array $product): array {
    $nutriments = (array)($product['nutriments'] ?? []);
    $barcode = preg_replace('/\D+/', '', (string)($product['code'] ?? $product['id'] ?? '')) ?: '';

    $kcal100g = off_to_float($nutriments['energy-kcal_100g'] ?? $nutriments['energy-kcal'] ?? null);
    $protein100g = off_to_float($nutriments['proteins_100g'] ?? $nutriments['proteins'] ?? null);
    $carbs100g = off_to_float($nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrates'] ?? null);
    $fat100g = off_to_float($nutriments['fat_100g'] ?? $nutriments['fat'] ?? null);

    $servingLabel = trim((string)($product['serving_size'] ?? ''));
    $servingQuantityG = off_to_float($nutriments['serving_quantity'] ?? null);
    if ($servingQuantityG === null) {
      $servingQuantityG = off_to_float($product['serving_quantity'] ?? null);
    }

    $kcalServing = off_to_float($nutriments['energy-kcal_serving'] ?? null);
    $proteinServing = off_to_float($nutriments['proteins_serving'] ?? null);
    $carbsServing = off_to_float($nutriments['carbohydrates_serving'] ?? null);
    $fatServing = off_to_float($nutriments['fat_serving'] ?? null);

    return [
      'barcode' => $barcode,
      'name' => trim((string)($product['product_name'] ?? $product['product_name_it'] ?? '')),
      'brand' => trim((string)($product['brands'] ?? '')),
      'image_url' => trim((string)($product['image_front_small_url'] ?? $product['image_front_url'] ?? $product['image_url'] ?? '')),
      'serving_size_label' => $servingLabel,
      'serving_quantity_g' => $servingQuantityG,
      'kcal_100g' => $kcal100g,
      'protein_100g' => $protein100g,
      'carbs_100g' => $carbs100g,
      'fat_100g' => $fat100g,
      'kcal_serving' => $kcalServing,
      'protein_serving' => $proteinServing,
      'carbs_serving' => $carbsServing,
      'fat_serving' => $fatServing,
      'nutrition_data_per' => trim((string)($product['nutrition_data_per'] ?? '100g')),
      'raw_product' => $product,
    ];
  }
}

if (!function_exists('off_upsert_product_cache')) {
  function off_upsert_product_cache(array $normalized): void {
    if (!off_table_exists('OpenFoodFactsProdottiCache') || empty($normalized['barcode'])) {
      return;
    }
    Database::exec(
      'INSERT INTO OpenFoodFactsProdottiCache (
        barcode, productName, productNameNormalized, brand, quantityLabel, servingSizeLabel, servingQuantityG,
        imageUrl, productUrl, nutritionDataPer, energiaKcal100g, proteine100g, carboidrati100g, grassi100g,
        energiaKcalServing, proteineServing, carboidratiServing, grassiServing, rawJson, creatoIl, aggiornatoIl
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        productName = VALUES(productName),
        productNameNormalized = VALUES(productNameNormalized),
        brand = VALUES(brand),
        quantityLabel = VALUES(quantityLabel),
        servingSizeLabel = VALUES(servingSizeLabel),
        servingQuantityG = VALUES(servingQuantityG),
        imageUrl = VALUES(imageUrl),
        productUrl = VALUES(productUrl),
        nutritionDataPer = VALUES(nutritionDataPer),
        energiaKcal100g = VALUES(energiaKcal100g),
        proteine100g = VALUES(proteine100g),
        carboidrati100g = VALUES(carboidrati100g),
        grassi100g = VALUES(grassi100g),
        energiaKcalServing = VALUES(energiaKcalServing),
        proteineServing = VALUES(proteineServing),
        carboidratiServing = VALUES(carboidratiServing),
        grassiServing = VALUES(grassiServing),
        rawJson = VALUES(rawJson),
        aggiornatoIl = NOW()',
      [
        $normalized['barcode'],
        $normalized['name'] ?: null,
        off_normalize_query((string)$normalized['name']),
        $normalized['brand'] ?: null,
        (string)($normalized['raw_product']['quantity'] ?? ''),
        $normalized['serving_size_label'] ?: null,
        $normalized['serving_quantity_g'],
        $normalized['image_url'] ?: null,
        (string)($normalized['raw_product']['url'] ?? ''),
        $normalized['nutrition_data_per'] ?: null,
        $normalized['kcal_100g'],
        $normalized['protein_100g'],
        $normalized['carbs_100g'],
        $normalized['fat_100g'],
        $normalized['kcal_serving'],
        $normalized['protein_serving'],
        $normalized['carbs_serving'],
        $normalized['fat_serving'],
        json_encode($normalized['raw_product'], JSON_UNESCAPED_UNICODE),
      ]
    );
  }
}

if (!function_exists('off_get_cached_product')) {
  function off_get_cached_product(string $barcode): ?array {
    if (!off_table_exists('OpenFoodFactsProdottiCache')) {
      return null;
    }
    $row = Database::exec('SELECT * FROM OpenFoodFactsProdottiCache WHERE barcode = ? LIMIT 1', [$barcode])->fetch();
    if (!$row) {
      return null;
    }
    return [
      'barcode' => (string)$row['barcode'],
      'name' => (string)($row['productName'] ?? ''),
      'brand' => (string)($row['brand'] ?? ''),
      'image_url' => (string)($row['imageUrl'] ?? ''),
      'serving_size_label' => (string)($row['servingSizeLabel'] ?? ''),
      'serving_quantity_g' => off_to_float($row['servingQuantityG'] ?? null),
      'kcal_100g' => off_to_float($row['energiaKcal100g'] ?? null),
      'protein_100g' => off_to_float($row['proteine100g'] ?? null),
      'carbs_100g' => off_to_float($row['carboidrati100g'] ?? null),
      'fat_100g' => off_to_float($row['grassi100g'] ?? null),
      'kcal_serving' => off_to_float($row['energiaKcalServing'] ?? null),
      'protein_serving' => off_to_float($row['proteineServing'] ?? null),
      'carbs_serving' => off_to_float($row['carboidratiServing'] ?? null),
      'fat_serving' => off_to_float($row['grassiServing'] ?? null),
      'nutrition_data_per' => (string)($row['nutritionDataPer'] ?? '100g'),
      'raw_product' => json_decode((string)($row['rawJson'] ?? '{}'), true) ?: [],
    ];
  }
}

if (!function_exists('off_lookup_barcode')) {
  function off_lookup_barcode(string $barcode): ?array {
    $barcode = preg_replace('/\D+/', '', $barcode) ?: '';
    if ($barcode === '' || strlen($barcode) < 8) {
      throw new InvalidArgumentException('Barcode non valido.');
    }

    $cached = off_get_cached_product($barcode);
    if ($cached) {
      return $cached;
    }

    $response = off_http_get('https://world.openfoodfacts.org/api/v2/product/' . rawurlencode($barcode) . '.json');
    if ((int)($response['status'] ?? 0) !== 1 || empty($response['product'])) {
      return null;
    }

    $normalized = off_normalize_product((array)$response['product']);
    if (empty($normalized['barcode'])) {
      $normalized['barcode'] = $barcode;
    }
    off_upsert_product_cache($normalized);
    return $normalized;
  }
}

if (!function_exists('off_search_products')) {
  function off_search_products(string $query, int $page = 1, int $pageSize = 12): array {
    $normalizedQuery = off_normalize_query($query);
    if ($normalizedQuery === '' || mb_strlen($normalizedQuery) < 2) {
      throw new InvalidArgumentException('Inserisci almeno 2 caratteri.');
    }

    $page = max(1, $page);
    $pageSize = max(1, min(30, $pageSize));
    $queryHash = sha1($normalizedQuery . '|' . $page . '|' . $pageSize);

    if (off_table_exists('OpenFoodFactsSearchCache')) {
      $cached = Database::exec(
        'SELECT responseJson, totalResults, scadeIl FROM OpenFoodFactsSearchCache
         WHERE queryHash = ? AND pageNumber = ? AND pageSize = ?
         LIMIT 1',
        [$queryHash, $page, $pageSize]
      )->fetch();

      if ($cached && !empty($cached['responseJson']) && strtotime((string)$cached['scadeIl']) > time()) {
        $decoded = json_decode((string)$cached['responseJson'], true);
        if (is_array($decoded)) {
          return $decoded;
        }
      }
    }

    $url = 'https://world.openfoodfacts.org/cgi/search.pl?search_simple=1&json=1'
      . '&search_terms=' . rawurlencode($normalizedQuery)
      . '&page=' . $page
      . '&page_size=' . $pageSize
      . '&fields=code,product_name,product_name_it,brands,image_front_small_url,image_front_url,image_url,serving_size,serving_quantity,nutrition_data_per,nutriments,quantity,url';

    $response = off_http_get($url);
    $products = [];
    foreach ((array)($response['products'] ?? []) as $product) {
      $normalized = off_normalize_product((array)$product);
      if (empty($normalized['barcode']) || empty($normalized['name'])) {
        continue;
      }
      off_upsert_product_cache($normalized);
      $products[] = $normalized;
    }

    $payload = [
      'products' => $products,
      'total_results' => (int)($response['count'] ?? count($products)),
      'page' => $page,
      'page_size' => $pageSize,
    ];

    if (off_table_exists('OpenFoodFactsSearchCache')) {
      $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
      Database::exec(
        'INSERT INTO OpenFoodFactsSearchCache
         (queryHash, queryNormalized, pageNumber, pageSize, responseJson, totalResults, scadeIl, creatoIl, aggiornatoIl)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 6 HOUR), NOW(), NOW())
         ON DUPLICATE KEY UPDATE responseJson = VALUES(responseJson), totalResults = VALUES(totalResults), scadeIl = VALUES(scadeIl), aggiornatoIl = NOW()',
        [$queryHash, $normalizedQuery, $page, $pageSize, $jsonPayload, (int)$payload['total_results']]
      );
    }

    return $payload;
  }
}

if (!function_exists('off_calculate_macros')) {
  function off_calculate_macros(array $normalizedProduct, string $mode, float $amount): array {
    $amount = max(0.0, $amount);
    if ($amount <= 0) {
      throw new InvalidArgumentException('Quantità non valida.');
    }

    $kcal100 = (float)($normalizedProduct['kcal_100g'] ?? 0);
    $p100 = (float)($normalizedProduct['protein_100g'] ?? 0);
    $c100 = (float)($normalizedProduct['carbs_100g'] ?? 0);
    $f100 = (float)($normalizedProduct['fat_100g'] ?? 0);

    $servingQ = off_to_float($normalizedProduct['serving_quantity_g'] ?? null);
    $kcalServing = off_to_float($normalizedProduct['kcal_serving'] ?? null);
    $pServing = off_to_float($normalizedProduct['protein_serving'] ?? null);
    $cServing = off_to_float($normalizedProduct['carbs_serving'] ?? null);
    $fServing = off_to_float($normalizedProduct['fat_serving'] ?? null);

    if ($mode === 'grams') {
      $grams = $amount;
      $factor = $grams / 100;
      return [
        'calorie' => round($kcal100 * $factor, 2),
        'proteine' => round($p100 * $factor, 2),
        'carboidrati' => round($c100 * $factor, 2),
        'grassi' => round($f100 * $factor, 2),
        'grammiTotali' => round($grams, 2),
        'numeroPorzioni' => $servingQ && $servingQ > 0 ? round($grams / $servingQ, 3) : null,
      ];
    }

    if ($mode !== 'servings') {
      throw new InvalidArgumentException('Modalità quantità non supportata.');
    }

    $servings = $amount;
    if ($kcalServing !== null && $pServing !== null && $cServing !== null && $fServing !== null) {
      $grams = $servingQ && $servingQ > 0 ? $servings * $servingQ : null;
      return [
        'calorie' => round($kcalServing * $servings, 2),
        'proteine' => round($pServing * $servings, 2),
        'carboidrati' => round($cServing * $servings, 2),
        'grassi' => round($fServing * $servings, 2),
        'grammiTotali' => $grams !== null ? round($grams, 2) : null,
        'numeroPorzioni' => round($servings, 3),
      ];
    }

    if ($servingQ !== null && $servingQ > 0) {
      $grams = $servings * $servingQ;
      $factor = $grams / 100;
      return [
        'calorie' => round($kcal100 * $factor, 2),
        'proteine' => round($p100 * $factor, 2),
        'carboidrati' => round($c100 * $factor, 2),
        'grassi' => round($f100 * $factor, 2),
        'grammiTotali' => round($grams, 2),
        'numeroPorzioni' => round($servings, 3),
      ];
    }

    throw new RuntimeException('Dati porzione non disponibili per questo prodotto.');
  }
}
