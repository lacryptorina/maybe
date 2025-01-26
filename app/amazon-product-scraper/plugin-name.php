<?php
/*
Plugin Name: Amazon Product Scraper
Description: A lightweight WordPress plugin to scrape and display Amazon products with SEO optimization.
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMAZON_SCRAPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMAZON_SCRAPER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Enqueue CSS and JavaScript
function amazon_scraper_enqueue_scripts() {
    // Frontend styles
    wp_enqueue_style('amazon-scraper-frontend', AMAZON_SCRAPER_PLUGIN_URL . 'assets/css/frontend.css');

    // Backend styles
    if (is_admin()) {
        wp_enqueue_style('amazon-scraper-backend', AMAZON_SCRAPER_PLUGIN_URL . 'assets/css/backend.css');
    }

    // Frontend scripts
    wp_enqueue_script('amazon-scraper-frontend', AMAZON_SCRAPER_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'amazon_scraper_enqueue_scripts');

// Include Guzzle HTTP client
require_once AMAZON_SCRAPER_PLUGIN_DIR . 'vendor/autoload.php';
use GuzzleHttp\Client;

// Web Scraping Logic
function amazon_scraper_fetch_products($keywords) {
    $client = new Client();
    $tracking_id = 'gfkosher05-20';
    $products = [];

    foreach ($keywords as $keyword) {
        $url = "https://www.amazon.com/s?k=" . urlencode($keyword);
        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $html = $response->getBody()->getContents();
                $dom = new DOMDocument();
                @$dom->loadHTML($html);

                $xpath = new DOMXPath($dom);
                $items = $xpath->query("//div[contains(@class, 's-result-item')]");

                foreach ($items as $item) {
                    $title = $xpath->query(".//h2", $item)->item(0)->nodeValue ?? '';
                    $price = $xpath->query(".//span[@class='a-price']", $item)->item(0)->nodeValue ?? '';
                    $image = $xpath->query(".//img/@src", $item)->item(0)->nodeValue ?? '';
                    $link = $xpath->query(".//a/@href", $item)->item(0)->nodeValue ?? '';

                    if ($title && $price && $image && $link) {
                        $link = "https://www.amazon.com" . $link . "&tag=" . $tracking_id;
                        $products[] = [
                            'title' => $title,
                            'price' => $price,
                            'image' => $image,
                            'link' => $link,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Amazon Scraper Error: ' . $e->getMessage());
        }
    }

    return $products;
}

// Backend Interface
function amazon_scraper_admin_menu() {
    add_menu_page(
        'Amazon Product Scraper',
        'Amazon Scraper',
        'manage_options',
        'amazon-scraper',
        'amazon_scraper_admin_page',
        'dashicons-products',
        6
    );
}
add_action('admin_menu', 'amazon_scraper_admin_menu');

function amazon_scraper_admin_page() {
    ?>
    <div class="wrap">
        <h1>Amazon Product Scraper</h1>
        <form method="post" action="">
            <?php wp_nonce_field('amazon_scraper_nonce', 'amazon_scraper_nonce'); ?>
            <label for="keywords">Enter Keywords:</label>
            <input type="text" name="keywords" id="keywords" required>
            <input type="submit" name="scrape" value="Scrape Products">
        </form>

        <?php
        if (isset($_POST['scrape']) && wp_verify_nonce($_POST['amazon_scraper_nonce'], 'amazon_scraper_nonce')) {
            $keywords = array_map('trim', explode(',', sanitize_text_field($_POST['keywords'])));
            $products = amazon_scraper_fetch_products($keywords);

            if (!empty($products)) {
                update_option('amazon_scraper_products', $products);
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Title</th><th>Price</th><th>Image</th><th>Link</th></tr></thead>';
                echo '<tbody>';
                foreach ($products as $product) {
                    echo '<tr>';
                    echo '<td>' . esc_html($product['title']) . '</td>';
                    echo '<td>' . esc_html($product['price']) . '</td>';
                    echo '<td><img src="' . esc_url($product['image']) . '" width="100"></td>';
                    echo '<td><a href="' . esc_url($product['link']) . '" target="_blank">View Product</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No products found.</p>';
            }
        }
        ?>
    </div>
    <?php
}

// Frontend Display
function amazon_scraper_display_products() {
    $products = get_option('amazon_scraper_products', []);

    if (!empty($products)) {
        echo '<div class="amazon-scraper-products">';
        foreach ($products as $product) {
            echo '<div class="product">';
            echo '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '">';
            echo '<h3>' . esc_html($product['title']) . '</h3>';
            echo '<p>' . esc_html($product['price']) . '</p>';
            echo '<a href="' . esc_url($product['link']) . '" target="_blank" rel="noopener noreferrer">Buy Now</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No products available.</p>';
    }
}
add_shortcode('amazon_products', 'amazon_scraper_display_products');

// SEO Optimization
function amazon_scraper_seo_metadata($product) {
    $title = $product['title'] . ' | Amazon Product';
    $description = 'Buy ' . $product['title'] . ' at the best price on Amazon.';
    $keywords = 'Amazon, ' . $product['title'];

    return [
        'title' => $title,
        'description' => $description,
        'keywords' => $keywords,
    ];
}
