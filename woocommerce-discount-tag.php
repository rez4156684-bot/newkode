<?php
/**
 * Plugin Name: WooCommerce Discount Tag
 * Plugin URI: https://github.com/yourusername/woocommerce-discount-tag
 * Description: نمایش تگ "تخفیف لحظه آخری" روی محصولات تخفیف‌دار ووکامرس
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: wc-discount-tag
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های پلاگین
define('WC_DISCOUNT_TAG_VERSION', '1.0.0');
define('WC_DISCOUNT_TAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_DISCOUNT_TAG_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Discount_Tag {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * متن تگ تخفیف
     */
    private $discount_text = 'تخفیف لحظه آخری';

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // بررسی فعال بودن ووکامرس
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // اضافه کردن استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // اضافه کردن تگ تخفیف به محصولات
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'show_discount_tag'), 10);
        add_action('woocommerce_before_single_product_summary', array($this, 'show_discount_tag_single'), 20);

        // افزودن تنظیمات به پنل مدیریت
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * نمایش اعلان در صورت عدم نصب ووکامرس
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('افزونه WooCommerce Discount Tag نیاز به نصب و فعال‌سازی افزونه WooCommerce دارد.', 'wc-discount-tag'); ?></p>
        </div>
        <?php
    }

    /**
     * افزودن استایل‌ها
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'wc-discount-tag-styles',
            WC_DISCOUNT_TAG_PLUGIN_URL . 'assets/css/discount-tag.css',
            array(),
            WC_DISCOUNT_TAG_VERSION
        );
    }

    /**
     * نمایش تگ تخفیف در صفحه فروشگاه
     */
    public function show_discount_tag() {
        global $product;

        if (!$product) {
            return;
        }

        // بررسی اینکه محصول در حال تخفیف است یا خیر
        if ($product->is_on_sale()) {
            $this->render_discount_tag();
        }
    }

    /**
     * نمایش تگ تخفیف در صفحه محصول
     */
    public function show_discount_tag_single() {
        global $product;

        if (!$product) {
            return;
        }

        // بررسی اینکه محصول در حال تخفیف است یا خیر
        if ($product->is_on_sale()) {
            $this->render_discount_tag('single');
        }
    }

    /**
     * رندر کردن تگ تخفیف
     */
    private function render_discount_tag($type = 'loop') {
        global $product;

        // محاسبه درصد تخفیف
        $percentage = $this->get_discount_percentage($product);

        $class = 'wc-discount-tag wc-discount-tag-' . $type;

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <span class="discount-badge">
                <span class="discount-text"><?php echo esc_html($this->discount_text); ?></span>
                <?php if ($percentage): ?>
                    <span class="discount-percentage"><?php echo esc_html($percentage); ?>%</span>
                <?php endif; ?>
            </span>
        </div>
        <?php
    }

    /**
     * محاسبه درصد تخفیف
     */
    private function get_discount_percentage($product) {
        if (!$product->is_on_sale()) {
            return 0;
        }

        $regular_price = (float) $product->get_regular_price();
        $sale_price = (float) $product->get_sale_price();

        if ($regular_price <= 0 || !$sale_price) {
            return 0;
        }

        $percentage = round((($regular_price - $sale_price) / $regular_price) * 100);

        return $percentage;
    }

    /**
     * افزودن لینک تنظیمات
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=products">' . __('تنظیمات', 'wc-discount-tag') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * اجرای پلاگین
 */
function wc_discount_tag_init() {
    return WC_Discount_Tag::get_instance();
}

// شروع پلاگین
wc_discount_tag_init();
