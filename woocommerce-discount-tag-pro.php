<?php
/**
 * Plugin Name: WooCommerce Discount Tag Pro
 * Plugin URI: https://github.com/yourusername/woocommerce-discount-tag-pro
 * Description: نمایش تگ تخفیف حرفه‌ای با پنل تنظیمات کامل، اشکال متنوع و نمایش در کنار قیمت
 * Version: 2.1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: wc-discount-tag-pro
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
define('WC_DISCOUNT_TAG_PRO_VERSION', '2.1.0');

class WC_Discount_Tag_Pro {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * تنظیمات پیش‌فرض
     */
    private $default_settings = array(
        'enabled' => 'yes',
        'text' => 'تخفیف لحظه آخری',
        'shape' => 'badge',
        'position' => 'top-right',
        'bg_color' => '#ff0000',
        'text_color' => '#ffffff',
        'font_size' => '12',
        'show_percentage' => 'yes',
        'enable_animation' => 'yes',
        'animation_type' => 'pulse',
        'show_on_shop' => 'yes',
        'show_on_single' => 'yes',
        'show_on_shop_price' => 'yes',
        'show_on_single_price' => 'yes',
    );

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

        // اضافه کردن تگ تخفیف به محصولات (روی تصویر)
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'show_discount_tag'), 10);
        add_action('woocommerce_before_single_product_summary', array($this, 'show_discount_tag_single'), 20);

        // اضافه کردن تگ تخفیف در کنار قیمت
        add_filter('woocommerce_get_price_html', array($this, 'add_price_badge'), 10, 2);

        // افزودن تنظیمات به پنل مدیریت
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_discount_tag', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_discount_tag', array($this, 'update_settings'));

        // اضافه کردن متاباکس به محصولات
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));

        // افزودن لینک تنظیمات
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * نمایش اعلان در صورت عدم نصب ووکامرس
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('افزونه WooCommerce Discount Tag Pro نیاز به نصب و فعال‌سازی افزونه WooCommerce دارد.', 'wc-discount-tag-pro'); ?></p>
        </div>
        <?php
    }

    /**
     * گرفتن تنظیمات
     */
    private function get_settings() {
        $settings = array();
        foreach ($this->default_settings as $key => $default) {
            $settings[$key] = get_option('wc_discount_tag_' . $key, $default);
        }
        return $settings;
    }

    /**
     * افزودن تب تنظیمات
     */
    public function add_settings_tab($settings_tabs) {
        $settings_tabs['discount_tag'] = __('تگ تخفیف', 'wc-discount-tag-pro');
        return $settings_tabs;
    }

    /**
     * نمایش تنظیمات
     */
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings_fields());
    }

    /**
     * ذخیره تنظیمات
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings_fields());
    }

    /**
     * فیلدهای تنظیمات
     */
    private function get_settings_fields() {
        return array(
            array(
                'title' => __('تنظیمات تگ تخفیف', 'wc-discount-tag-pro'),
                'type' => 'title',
                'desc' => __('تنظیمات نمایش تگ تخفیف روی محصولات', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_title'
            ),
            array(
                'title' => __('فعال‌سازی', 'wc-discount-tag-pro'),
                'desc' => __('نمایش تگ تخفیف روی محصولات', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_enabled',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('متن تگ', 'wc-discount-tag-pro'),
                'desc' => __('متنی که روی تگ تخفیف نمایش داده می‌شود', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_text',
                'default' => 'تخفیف لحظه آخری',
                'type' => 'text',
                'css' => 'min-width:300px;'
            ),
            array(
                'title' => __('شکل تگ', 'wc-discount-tag-pro'),
                'desc' => __('شکل نمایش تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_shape',
                'default' => 'badge',
                'type' => 'select',
                'options' => array(
                    'badge' => __('مربع (Badge)', 'wc-discount-tag-pro'),
                    'circle' => __('دایره (Circle)', 'wc-discount-tag-pro'),
                    'ribbon' => __('روبان (Ribbon)', 'wc-discount-tag-pro'),
                    'corner' => __('گوشه (Corner)', 'wc-discount-tag-pro'),
                    'star' => __('ستاره (Star)', 'wc-discount-tag-pro'),
                    'hexagon' => __('شش‌ضلعی (Hexagon)', 'wc-discount-tag-pro'),
                ),
                'css' => 'min-width:300px;'
            ),
            array(
                'title' => __('موقعیت تگ', 'wc-discount-tag-pro'),
                'desc' => __('موقعیت نمایش تگ روی تصویر محصول', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_position',
                'default' => 'top-right',
                'type' => 'select',
                'options' => array(
                    'top-right' => __('بالا راست', 'wc-discount-tag-pro'),
                    'top-left' => __('بالا چپ', 'wc-discount-tag-pro'),
                    'bottom-right' => __('پایین راست', 'wc-discount-tag-pro'),
                    'bottom-left' => __('پایین چپ', 'wc-discount-tag-pro'),
                    'center' => __('وسط', 'wc-discount-tag-pro'),
                ),
                'css' => 'min-width:300px;'
            ),
            array(
                'title' => __('رنگ پس‌زمینه', 'wc-discount-tag-pro'),
                'desc' => __('رنگ پس‌زمینه تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_bg_color',
                'default' => '#ff0000',
                'type' => 'color'
            ),
            array(
                'title' => __('رنگ متن', 'wc-discount-tag-pro'),
                'desc' => __('رنگ متن تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_text_color',
                'default' => '#ffffff',
                'type' => 'color'
            ),
            array(
                'title' => __('اندازه فونت', 'wc-discount-tag-pro'),
                'desc' => __('اندازه فونت متن (پیکسل)', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_font_size',
                'default' => '12',
                'type' => 'number',
                'css' => 'width:80px;',
                'custom_attributes' => array(
                    'min' => '8',
                    'max' => '30',
                    'step' => '1'
                )
            ),
            array(
                'title' => __('نمایش درصد تخفیف', 'wc-discount-tag-pro'),
                'desc' => __('نمایش درصد تخفیف روی تگ', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_show_percentage',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('فعال‌سازی انیمیشن', 'wc-discount-tag-pro'),
                'desc' => __('نمایش انیمیشن روی تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_enable_animation',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('نوع انیمیشن', 'wc-discount-tag-pro'),
                'desc' => __('نوع انیمیشن تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_animation_type',
                'default' => 'pulse',
                'type' => 'select',
                'options' => array(
                    'pulse' => __('ضربان (Pulse)', 'wc-discount-tag-pro'),
                    'bounce' => __('پرش (Bounce)', 'wc-discount-tag-pro'),
                    'shake' => __('لرزش (Shake)', 'wc-discount-tag-pro'),
                    'rotate' => __('چرخش (Rotate)', 'wc-discount-tag-pro'),
                    'glow' => __('درخشش (Glow)', 'wc-discount-tag-pro'),
                ),
                'css' => 'min-width:300px;'
            ),
            array(
                'title' => __('نمایش در فروشگاه', 'wc-discount-tag-pro'),
                'desc' => __('نمایش تگ در صفحه فروشگاه', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_show_on_shop',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('نمایش در صفحه محصول', 'wc-discount-tag-pro'),
                'desc' => __('نمایش تگ روی تصویر در صفحه محصول', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_show_on_single',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('نمایش در کنار قیمت فروشگاه', 'wc-discount-tag-pro'),
                'desc' => __('نمایش تگ تخفیف در کنار قیمت در صفحه فروشگاه', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_show_on_shop_price',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('نمایش در کنار قیمت صفحه محصول', 'wc-discount-tag-pro'),
                'desc' => __('نمایش تگ تخفیف در کنار قیمت در صفحه محصول', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_show_on_single_price',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_discount_tag_end'
            ),
        );
    }

    /**
     * اضافه کردن متاباکس به محصولات
     */
    public function add_product_metabox() {
        add_meta_box(
            'wc_discount_tag_metabox',
            __('تنظیمات تگ تخفیف', 'wc-discount-tag-pro'),
            array($this, 'render_product_metabox'),
            'product',
            'side',
            'default'
        );
    }

    /**
     * رندر متاباکس محصول
     */
    public function render_product_metabox($post) {
        wp_nonce_field('wc_discount_tag_metabox', 'wc_discount_tag_metabox_nonce');

        $disable_tag = get_post_meta($post->ID, '_wc_discount_tag_disable', true);
        $custom_text = get_post_meta($post->ID, '_wc_discount_tag_custom_text', true);

        ?>
        <p>
            <label>
                <input type="checkbox" name="wc_discount_tag_disable" value="1" <?php checked($disable_tag, '1'); ?>>
                <?php _e('غیرفعال کردن تگ تخفیف برای این محصول', 'wc-discount-tag-pro'); ?>
            </label>
        </p>
        <p>
            <label for="wc_discount_tag_custom_text">
                <?php _e('متن سفارشی (اختیاری):', 'wc-discount-tag-pro'); ?>
            </label>
            <input type="text" id="wc_discount_tag_custom_text" name="wc_discount_tag_custom_text"
                   value="<?php echo esc_attr($custom_text); ?>"
                   class="widefat"
                   placeholder="<?php _e('متن سفارشی برای این محصول', 'wc-discount-tag-pro'); ?>">
        </p>
        <?php
    }

    /**
     * ذخیره متای محصول
     */
    public function save_product_meta($post_id) {
        if (!isset($_POST['wc_discount_tag_metabox_nonce']) ||
            !wp_verify_nonce($_POST['wc_discount_tag_metabox_nonce'], 'wc_discount_tag_metabox')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $disable = isset($_POST['wc_discount_tag_disable']) ? '1' : '0';
        update_post_meta($post_id, '_wc_discount_tag_disable', $disable);

        $custom_text = isset($_POST['wc_discount_tag_custom_text']) ? sanitize_text_field($_POST['wc_discount_tag_custom_text']) : '';
        update_post_meta($post_id, '_wc_discount_tag_custom_text', $custom_text);
    }

    /**
     * افزودن استایل‌ها به صورت Inline
     */
    public function add_inline_styles() {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes') {
            return;
        }

        $bg_color = $settings['bg_color'];
        $text_color = $settings['text_color'];
        $font_size = $settings['font_size'];
        $shape = $settings['shape'];
        $position = $settings['position'];
        $animation = $settings['enable_animation'] === 'yes' ? $settings['animation_type'] : 'none';

        ?>
        <style type="text/css">
            /* تنظیمات عمومی */
            .wc-discount-tag-pro {
                position: absolute;
                z-index: 10;
                pointer-events: none;
                <?php echo $this->get_position_css($position); ?>
            }

            .wc-discount-tag-pro .discount-badge {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                background: <?php echo esc_attr($bg_color); ?>;
                color: <?php echo esc_attr($text_color); ?>;
                font-family: 'Tahoma', 'Arial', sans-serif;
                font-weight: bold;
                text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
                direction: rtl;
                <?php echo $this->get_shape_css($shape); ?>
                <?php if ($animation !== 'none'): ?>
                animation: wc-discount-<?php echo esc_attr($animation); ?> 2s infinite;
                <?php endif; ?>
            }

            .wc-discount-tag-pro .discount-text {
                display: block;
                font-size: <?php echo esc_attr($font_size); ?>px;
                line-height: 1.3;
                white-space: nowrap;
            }

            .wc-discount-tag-pro .discount-percentage {
                display: block;
                font-size: <?php echo esc_attr($font_size + 4); ?>px;
                font-weight: 900;
                line-height: 1;
                margin-top: 2px;
            }

            /* تنظیمات برای محصولات */
            .products .product {
                position: relative;
            }

            .products .product .woocommerce-loop-product__link {
                position: relative;
                display: block;
            }

            .single-product .woocommerce-product-gallery {
                position: relative;
            }

            .wc-discount-tag-pro-single .discount-badge {
                padding: 15px 25px;
            }

            .wc-discount-tag-pro-single .discount-text {
                font-size: <?php echo esc_attr($font_size + 2); ?>px;
            }

            .wc-discount-tag-pro-single .discount-percentage {
                font-size: <?php echo esc_attr($font_size + 8); ?>px;
            }

            /* انیمیشن‌ها */
            @keyframes wc-discount-pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }

            @keyframes wc-discount-bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }

            @keyframes wc-discount-shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }

            @keyframes wc-discount-rotate {
                0% { transform: rotate(-5deg); }
                50% { transform: rotate(5deg); }
                100% { transform: rotate(-5deg); }
            }

            @keyframes wc-discount-glow {
                0%, 100% { box-shadow: 0 0 10px <?php echo esc_attr($bg_color); ?>; }
                50% { box-shadow: 0 0 20px <?php echo esc_attr($bg_color); ?>, 0 0 30px <?php echo esc_attr($bg_color); ?>; }
            }

            /* ریسپانسیو */
            @media (max-width: 768px) {
                .wc-discount-tag-pro .discount-text {
                    font-size: <?php echo esc_attr($font_size - 2); ?>px;
                }
                .wc-discount-tag-pro .discount-percentage {
                    font-size: <?php echo esc_attr($font_size + 2); ?>px;
                }
            }

            /* استایل تگ در کنار قیمت */
            .wc-discount-price-badge {
                display: inline-block;
                background: <?php echo esc_attr($bg_color); ?>;
                color: <?php echo esc_attr($text_color); ?>;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: <?php echo esc_attr($font_size - 2); ?>px;
                font-weight: bold;
                font-family: 'Tahoma', 'Arial', sans-serif;
                direction: rtl;
                margin-right: 8px;
                vertical-align: middle;
                white-space: nowrap;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                <?php if ($animation !== 'none'): ?>
                animation: wc-discount-<?php echo esc_attr($animation); ?> 2s infinite;
                <?php endif; ?>
            }

            .wc-discount-price-badge .price-badge-text {
                font-size: <?php echo esc_attr($font_size - 2); ?>px;
            }

            .wc-discount-price-badge .price-badge-percentage {
                font-size: <?php echo esc_attr($font_size); ?>px;
                font-weight: 900;
            }

            /* استایل خاص برای صفحه محصول */
            .single-product .wc-discount-price-badge {
                font-size: <?php echo esc_attr($font_size); ?>px;
                padding: 6px 14px;
                margin-right: 12px;
            }

            .single-product .wc-discount-price-badge .price-badge-text {
                font-size: <?php echo esc_attr($font_size); ?>px;
            }

            .single-product .wc-discount-price-badge .price-badge-percentage {
                font-size: <?php echo esc_attr($font_size + 2); ?>px;
            }

            /* ریسپانسیو برای تگ قیمت */
            @media (max-width: 768px) {
                .wc-discount-price-badge {
                    font-size: <?php echo esc_attr($font_size - 3); ?>px;
                    padding: 3px 8px;
                    margin-right: 5px;
                }

                .wc-discount-price-badge .price-badge-text {
                    font-size: <?php echo esc_attr($font_size - 3); ?>px;
                }

                .wc-discount-price-badge .price-badge-percentage {
                    font-size: <?php echo esc_attr($font_size - 1); ?>px;
                }
            }
        </style>
        <?php
    }

    /**
     * دریافت CSS موقعیت
     */
    private function get_position_css($position) {
        switch ($position) {
            case 'top-right':
                return 'top: 10px; right: 10px;';
            case 'top-left':
                return 'top: 10px; left: 10px;';
            case 'bottom-right':
                return 'bottom: 10px; right: 10px;';
            case 'bottom-left':
                return 'bottom: 10px; left: 10px;';
            case 'center':
                return 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
            default:
                return 'top: 10px; right: 10px;';
        }
    }

    /**
     * دریافت CSS شکل
     */
    private function get_shape_css($shape) {
        switch ($shape) {
            case 'badge':
                return 'padding: 8px 15px; border-radius: 5px; min-width: 80px;';

            case 'circle':
                return 'padding: 15px; border-radius: 50%; width: 80px; height: 80px; min-width: auto;';

            case 'ribbon':
                return 'padding: 8px 20px; border-radius: 0; position: relative; box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                       &:before { content: ""; position: absolute; left: -10px; top: 0; border-style: solid; border-width: 19px 10px 19px 0; border-color: transparent currentColor transparent transparent; }
                       &:after { content: ""; position: absolute; right: -10px; top: 0; border-style: solid; border-width: 19px 0 19px 10px; border-color: transparent transparent transparent currentColor; }';

            case 'corner':
                return 'padding: 8px 15px; border-radius: 0 0 5px 0; clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);';

            case 'star':
                return 'padding: 15px; clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%); width: 80px; height: 80px; min-width: auto;';

            case 'hexagon':
                return 'padding: 10px 15px; clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%); min-width: 80px;';

            default:
                return 'padding: 8px 15px; border-radius: 5px; min-width: 80px;';
        }
    }

    /**
     * بررسی نمایش تگ برای محصول
     */
    private function should_show_tag($product_id) {
        $disable = get_post_meta($product_id, '_wc_discount_tag_disable', true);
        return $disable !== '1';
    }

    /**
     * گرفتن متن سفارشی محصول
     */
    private function get_custom_text($product_id) {
        $custom_text = get_post_meta($product_id, '_wc_discount_tag_custom_text', true);
        return !empty($custom_text) ? $custom_text : null;
    }

    /**
     * نمایش تگ تخفیف در صفحه فروشگاه
     */
    public function show_discount_tag() {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes' || $settings['show_on_shop'] !== 'yes') {
            return;
        }

        global $product;

        if (!$product || !$product->is_on_sale()) {
            return;
        }

        if (!$this->should_show_tag($product->get_id())) {
            return;
        }

        $this->render_discount_tag($product, 'loop');
    }

    /**
     * نمایش تگ تخفیف در صفحه محصول
     */
    public function show_discount_tag_single() {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes' || $settings['show_on_single'] !== 'yes') {
            return;
        }

        global $product;

        if (!$product || !$product->is_on_sale()) {
            return;
        }

        if (!$this->should_show_tag($product->get_id())) {
            return;
        }

        $this->render_discount_tag($product, 'single');
    }

    /**
     * رندر کردن تگ تخفیف
     */
    private function render_discount_tag($product, $type = 'loop') {
        $settings = $this->get_settings();
        $percentage = $this->get_discount_percentage($product);

        $custom_text = $this->get_custom_text($product->get_id());
        $text = $custom_text ? $custom_text : $settings['text'];

        $class = 'wc-discount-tag-pro wc-discount-tag-pro-' . $type;

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <span class="discount-badge">
                <span class="discount-text"><?php echo esc_html($text); ?></span>
                <?php if ($settings['show_percentage'] === 'yes' && $percentage > 0): ?>
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
     * اضافه کردن تگ تخفیف در کنار قیمت
     */
    public function add_price_badge($price, $product) {
        // بررسی اینکه محصول در حال تخفیف است
        if (!$product->is_on_sale()) {
            return $price;
        }

        $settings = $this->get_settings();

        // بررسی فعال بودن افزونه
        if ($settings['enabled'] !== 'yes') {
            return $price;
        }

        // بررسی غیرفعال نبودن تگ برای این محصول
        if (!$this->should_show_tag($product->get_id())) {
            return $price;
        }

        // تشخیص صفحه
        $is_single = is_product();
        $show_on_price = false;

        if ($is_single && $settings['show_on_single_price'] === 'yes') {
            $show_on_price = true;
        } elseif (!$is_single && $settings['show_on_shop_price'] === 'yes') {
            $show_on_price = true;
        }

        if (!$show_on_price) {
            return $price;
        }

        // گرفتن متن و درصد
        $custom_text = $this->get_custom_text($product->get_id());
        $text = $custom_text ? $custom_text : $settings['text'];
        $percentage = $this->get_discount_percentage($product);

        // ساخت تگ
        $badge_html = '<span class="wc-discount-price-badge">';
        $badge_html .= '<span class="price-badge-text">' . esc_html($text) . '</span>';

        if ($settings['show_percentage'] === 'yes' && $percentage > 0) {
            $badge_html .= ' <span class="price-badge-percentage">' . esc_html($percentage) . '%</span>';
        }

        $badge_html .= '</span>';

        return $price . ' ' . $badge_html;
    }

    /**
     * افزودن لینک تنظیمات
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=discount_tag">' . __('تنظیمات', 'wc-discount-tag-pro') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * اجرای پلاگین
 */
function wc_discount_tag_pro_init() {
    return WC_Discount_Tag_Pro::get_instance();
}

// شروع پلاگین
wc_discount_tag_pro_init();
