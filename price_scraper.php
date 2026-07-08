<?php
function getUsedDevicePrice($device_type, $brand, $model, $condition) {
    // Construct search query for Malaysian market
    $condition_map = [
        'Excellent' => 'mint',
        'Good' => 'good',
        'Fair' => 'fair',
        'Poor' => 'damaged'
    ];
    
    $condition_text = $condition_map[$condition] ?? 'good';
    
    // Build search query with Malaysian context
    $search_query = urlencode("$brand $model used price Malaysia $condition_text");
    
    error_log("Price Search: Searching for '$brand $model' in Malaysia, condition: $condition_text");
    
    // Use a user agent to avoid being blocked
    $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    // Try multiple sources for price data
    $prices = [];
    
    // Source 1: Bing Search (more scraping-friendly than Google)
    $bing_url = "https://www.bing.com/search?q=" . $search_query;
    $bing_prices = scrapeBing($bing_url, $user_agent);
    $prices = array_merge($prices, $bing_prices);
    
    // Source 2: DuckDuckGo as backup
    if (empty($prices)) {
        $ddg_url = "https://duckduckgo.com/html/?q=" . $search_query;
        $ddg_prices = scrapeDuckDuckGo($ddg_url, $user_agent);
        $prices = array_merge($prices, $ddg_prices);
    }
    
    error_log("Price Search: Found " . count($prices) . " prices from scraping: " . implode(', ', $prices));
    
    // If scraping fails, use fallback pricing
    if (empty($prices)) {
        error_log("Price Search: Scraping failed, using fallback pricing");
        return getFallbackPrice($device_type, $brand, $model, $condition);
    }
    
    // Filter outliers (remove prices that are too high or too low)
    if (count($prices) > 2) {
        sort($prices);
        // Remove the highest and lowest if we have enough data
        array_shift($prices); // Remove lowest
        array_pop($prices); // Remove highest
    }
    
    // Calculate average price
    $avg_price = array_sum($prices) / count($prices);
    
    // Round to nearest 10
    $final_price = round($avg_price / 10) * 10;
    
    error_log("Price Search: Average scraped price: RM$avg_price, Final: RM$final_price");
    
    return $final_price;
}

function scrapeBing($url, $user_agent) {
    $prices = [];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Connection: keep-alive'
    ]);
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        error_log("Bing scraper: Failed to fetch page");
        return $prices;
    }
    
    // Parse HTML to extract prices
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Look for price patterns in the search results
    $text = $dom->textContent;
    
    // Extract RM prices (Malaysian Ringgit)
    // Pattern: RM 1,234 or RM1234 or RM 1.234
    preg_match_all('/RM\s*(\d{1,3}(?:[,.]\d{3})*(?:\.\d{2})?)/i', $text, $rm_matches);
    
    if (!empty($rm_matches[1])) {
        foreach ($rm_matches[1] as $price_str) {
            // Clean the price string (remove commas and dots used as thousand separators)
            $price = preg_replace('/[,.]/', '', $price_str);
            $price = (int)$price;
            
            // Filter reasonable prices (between 50 and 15000 for MYR)
            if ($price >= 50 && $price <= 15000) {
                $prices[] = $price;
            }
        }
    }
    
    // Also try to extract USD prices as fallback
    preg_match_all('/\$\s*(\d{1,3}(?:[,.]\d{3})*(?:\.\d{2})?)/', $text, $usd_matches);
    
    if (!empty($usd_matches[1])) {
        foreach ($usd_matches[1] as $price_str) {
            $price = preg_replace('/[,.]/', '', $price_str);
            $price = (int)$price;
            
            if ($price >= 20 && $price <= 3000) {
                // Convert USD to MYR (approximate)
                $price_myr = $price * 4.7;
                $prices[] = round($price_myr);
            }
        }
    }
    
    // Limit to top 8 prices to avoid outliers
    $prices = array_slice($prices, 0, 8);
    
    error_log("Bing scraper: Found " . count($prices) . " prices");
    
    return $prices;
}

function scrapeDuckDuckGo($url, $user_agent) {
    $prices = [];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        return $prices;
    }
    
    // Parse HTML to extract prices
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Look for price patterns in the search results
    // Common price patterns: $XXX, RM XXX, RMXXX, RM 1,234
    $text = $dom->textContent;
    
    // Extract prices with RM or $ symbol
    preg_match_all('/(?:RM\s*[\$]?\s*|[\$]\s*)(\d{1,3}(?:[,.]\d{3})*(?:\.\d{2})?)/', $text, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $price_str) {
            // Clean the price string
            $price = preg_replace('/[,.]/', '', $price_str);
            $price = (int)$price;
            
            // Filter reasonable prices (between 50 and 10000)
            if ($price >= 50 && $price <= 10000) {
                $prices[] = $price;
            }
        }
    }
    
    // Limit to top 5 prices to avoid outliers
    $prices = array_slice($prices, 0, 5);
    
    return $prices;
}

function getFallbackPrice($device_type, $brand, $model, $condition) {
    // Fallback to improved hardcoded pricing
    $model_lower = strtolower($model);
    $brand_lower = strtolower($brand);
    
    $model_prices = [
        'smartphone' => [
            'iphone 15 pro max' => 2800, 'iphone 15 pro' => 2400, 'iphone 15 plus' => 1800, 'iphone 15' => 1600,
            'iphone 14 pro max' => 2200, 'iphone 14 pro' => 1900, 'iphone 14 plus' => 1400, 'iphone 14' => 1200,
            'iphone 13 pro max' => 1600, 'iphone 13 pro' => 1400, 'iphone 13' => 1000, 'iphone 13 mini' => 800,
            'galaxy s26 ultra' => 3200, 'galaxy s26 plus' => 2200, 'galaxy s26' => 1800,
            'galaxy s25 ultra' => 3000, 'galaxy s25 plus' => 2000, 'galaxy s25' => 1600,
            'galaxy s24 ultra' => 2600, 'galaxy s24 plus' => 1800, 'galaxy s24' => 1400,
            'galaxy s23 ultra' => 2000, 'galaxy s23 plus' => 1400, 'galaxy s23' => 1100,
            'galaxy s22 ultra' => 1600, 'galaxy s22 plus' => 1200, 'galaxy s22' => 900,
            'galaxy s21 ultra' => 1200, 'galaxy s21 plus' => 900, 'galaxy s21' => 700,
            'galaxy z fold 6' => 2800, 'galaxy z fold 5' => 2400, 'galaxy z fold 4' => 2000,
            'galaxy z flip 6' => 1800, 'galaxy z flip 5' => 1500, 'galaxy z flip 4' => 1200,
            'pixel 9 pro xl' => 1800, 'pixel 9 pro' => 1600, 'pixel 9' => 1200,
            'pixel 8 pro' => 1200, 'pixel 8' => 900, 'pixel 7 pro' => 900, 'pixel 7' => 700,
            'pixel 6 pro' => 600, 'pixel 6' => 500,
            'oneplus 13' => 1500, 'oneplus 12' => 1300, 'oneplus 11' => 1000, 'oneplus 10' => 800,
            'oneplus 9' => 600, 'oneplus 8' => 500,
            'xiaomi 14 ultra' => 2000, 'xiaomi 14' => 1500, 'xiaomi 13 ultra' => 1800, 'xiaomi 13' => 1200,
            'oppo find x7 ultra' => 1800, 'oppo find x6' => 1400,
            'vivo x100 pro' => 1600, 'vivo x100' => 1200,
            'honor magic 6 pro' => 1400, 'honor magic 5' => 1000
        ],
        'tablet' => [
            'ipad pro 12.9 m4' => 2000, 'ipad pro 12.9' => 1500, 'ipad pro 11 m4' => 1800, 'ipad pro 11' => 1200,
            'ipad air m2' => 900, 'ipad air' => 700, 'ipad 10' => 500, 'ipad 9' => 400, 'ipad mini 6' => 600,
            'galaxy tab s9 ultra' => 1200, 'galaxy tab s9 plus' => 900, 'galaxy tab s9' => 700,
            'galaxy tab s8 ultra' => 1000, 'galaxy tab s8 plus' => 800, 'galaxy tab s8' => 600,
            'galaxy tab a9' => 300, 'galaxy tab a8' => 250,
            'surface pro 11' => 2000, 'surface pro 9' => 1500, 'surface go 4' => 600
        ],
        'laptop' => [
            'macbook pro 16 m3' => 3000, 'macbook pro 16' => 2500, 'macbook pro 14 m3' => 2600, 'macbook pro 14' => 2000,
            'macbook air m3' => 1600, 'macbook air m2' => 1400, 'macbook air' => 1200,
            'dell xps 15' => 1800, 'dell xps 13' => 1500, 'dell alienware' => 2000, 'dell inspiron' => 800,
            'hp spectre' => 1400, 'hp envy' => 1200, 'hp pavilion' => 900, 'hp omen' => 1500,
            'lenovo thinkpad x1' => 1600, 'lenovo thinkpad t14' => 1200, 'lenovo yoga' => 1000, 'lenovo legion' => 1800,
            'asus rog zephyrus' => 2000, 'asus vivobook' => 800, 'asus zenbook' => 1200,
            'acer swift' => 700, 'acer nitro' => 1000, 'acer predator' => 1500,
            'razer blade 15' => 2200, 'razer blade 14' => 1800,
            'msi stealth' => 1800, 'msi katana' => 1200
        ],
        'smartwatch' => [
            'apple watch ultra 2' => 1000, 'apple watch ultra' => 800, 'apple watch series 10' => 600, 'apple watch series 9' => 500,
            'apple watch se 2' => 300, 'apple watch se' => 250,
            'galaxy watch 7' => 500, 'galaxy watch 6' => 400, 'galaxy watch 5' => 300, 'galaxy watch 4' => 200,
            'galaxy watch active 2' => 150,
            'garmin fenix 7' => 600, 'garmin forerunner 965' => 500, 'garmin instinct' => 300,
            'garmin venu 3' => 1000, 'garmin venu 3s' => 900, 'garmin venu 2' => 700, 'garmin venu 2s' => 600,
            'garmin venu sq 2' => 500, 'garmin venu sq' => 400, 'garmin venu' => 350,
            'fitbit sense 2' => 300, 'fitbit versa 4' => 250, 'fitbit charge 6' => 150
        ]
    ];
    
    $device_type_lower = strtolower($device_type);
    $estimate = 300;
    
    if (isset($model_prices[$device_type_lower])) {
        foreach ($model_prices[$device_type_lower] as $db_model => $price) {
            if ($model_lower === $db_model || strpos($model_lower, $db_model) !== false) {
                $estimate = $price;
                break;
            }
        }
    }
    
    // Adjust based on condition
    $condition_multiplier = [
        'Excellent' => 1.0,
        'Good' => 0.85,
        'Fair' => 0.65,
        'Poor' => 0.45
    ];
    
    $multiplier = $condition_multiplier[$condition] ?? 0.85;
    $estimate = $estimate * $multiplier;
    
    return round($estimate);
}
?>
