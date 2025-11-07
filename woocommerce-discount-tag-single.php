<?php
/**
 * Plugin Name: WooCommerce Discount Tag
 * Plugin URI: https://github.com/yourusername/woocommerce-discount-tag
 * Description: نمایش تگ "تخفیف لحظه آخری" روی محصولات تخفیف‌دار ووکامرس - نسخه تک فایله
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
        add_action('wp_head', array($this, 'add_inline_styles'));

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
     * افزودن استایل‌ها به صورت Inline
     */
    public function add_inline_styles() {
        ?>
        <style type="text/css">
            /**
             * WooCommerce Discount Tag Styles
             * استایل‌های تگ تخفیف ووکامرس
             */

            /* تنظیمات عمومی تگ تخفیف */
            .wc-discount-tag {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 10;
                pointer-events: none;
            }

            /* تگ تخفیف در صفحه فروشگاه (حالت لوپ) */
            .wc-discount-tag-loop {
                top: 10px;
                right: 10px;
            }

            /* تگ تخفیف در صفحه محصول */
            .wc-discount-tag-single {
                top: 15px;
                right: 15px;
            }

            /* بج تخفیف */
            .wc-discount-tag .discount-badge {
                display: inline-block;
                background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
                color: #ffffff;
                padding: 8px 15px;
                border-radius: 5px;
                font-weight: bold;
                text-align: center;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                animation: wc-discount-pulse 2s infinite;
                font-family: 'Tahoma', 'Arial', sans-serif;
                direction: rtl;
                text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
                position: relative;
                min-width: 80px;
            }

            /* متن تخفیف */
            .wc-discount-tag .discount-text {
                display: block;
                font-size: 12px;
                line-height: 1.3;
                margin-bottom: 2px;
                white-space: nowrap;
            }

            /* درصد تخفیف */
            .wc-discount-tag .discount-percentage {
                display: block;
                font-size: 16px;
                font-weight: 900;
                line-height: 1;
                margin-top: 2px;
            }

            /* انیمیشن ضربان برای جلب توجه */
            @keyframes wc-discount-pulse {
                0% {
                    transform: scale(1);
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                }
                50% {
                    transform: scale(1.05);
                    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
                }
                100% {
                    transform: scale(1);
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                }
            }

            /* انیمیشن درخشش */
            @keyframes wc-discount-shine {
                0% {
                    left: -100%;
                }
                20%, 100% {
                    left: 100%;
                }
            }

            /* تنظیمات برای محصولات در حالت لوپ */
            .products .product {
                position: relative;
            }

            .products .product .woocommerce-loop-product__link {
                position: relative;
                display: block;
            }

            /* تنظیمات برای صفحه محصول */
            .single-product .woocommerce-product-gallery {
                position: relative;
            }

            /* تگ بزرگتر برای صفحه محصول */
            .wc-discount-tag-single .discount-badge {
                padding: 12px 20px;
            }

            .wc-discount-tag-single .discount-text {
                font-size: 14px;
            }

            .wc-discount-tag-single .discount-percentage {
                font-size: 20px;
            }

            /* اضافه کردن افکت‌های بصری */
            .wc-discount-tag .discount-badge::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 100%);
                border-radius: 5px;
                z-index: -1;
                opacity: 0.5;
            }

            /* افکت درخشش */
            .wc-discount-tag .discount-badge::after {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 50%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                animation: wc-discount-shine 3s infinite;
            }

            /* پشتیبانی از حالت تاریک */
            @media (prefers-color-scheme: dark) {
                .wc-discount-tag .discount-badge {
                    background: linear-gradient(135deg, #ff1a1a 0%, #d90000 100%);
                    box-shadow: 0 4px 10px rgba(255, 255, 255, 0.1);
                }
            }

            /* تنظیمات ریسپانسیو */
            @media (max-width: 768px) {
                .wc-discount-tag {
                    top: 5px;
                    right: 5px;
                }

                .wc-discount-tag .discount-badge {
                    padding: 6px 12px;
                }

                .wc-discount-tag .discount-text {
                    font-size: 10px;
                }

                .wc-discount-tag .discount-percentage {
                    font-size: 14px;
                }

                .wc-discount-tag-single .discount-badge {
                    padding: 8px 15px;
                }

                .wc-discount-tag-single .discount-text {
                    font-size: 12px;
                }

                .wc-discount-tag-single .discount-percentage {
                    font-size: 16px;
                }
            }
        </style>
        <?php
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
