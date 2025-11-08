<?php
/**
 * Plugin Name: WooCommerce Discount Tag Pro with Lucky Egg Game
 * Plugin URI: https://github.com/yourusername/woocommerce-discount-tag-pro
 * Description: نمایش تگ تخفیف حرفه‌ای با بازی تخم‌مرغ شانسی برای کد تخفیف
 * Version: 3.0.0
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
define('WC_DISCOUNT_TAG_PRO_VERSION', '3.0.0');

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
        'enable_lucky_egg' => 'yes',
        'lucky_egg_title' => 'تخم‌مرغ شانس خود را بشکنید!',
        'lucky_egg_description' => 'یک تخم‌مرغ انتخاب کنید و کد تخفیف خود را دریافت کنید',
        'lucky_egg_count' => '3',
        'discount_codes' => '',
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

        // اضافه کردن استایل‌ها و اسکریپت‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_inline_styles'));

        // اضافه کردن تگ تخفیف به محصولات (روی تصویر)
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'show_discount_tag'), 10);
        add_action('woocommerce_before_single_product_summary', array($this, 'show_discount_tag_single'), 20);

        // اضافه کردن تگ تخفیف در کنار قیمت
        add_filter('woocommerce_get_price_html', array($this, 'add_price_badge'), 10, 2);

        // اضافه کردن مودال بازی
        add_action('wp_footer', array($this, 'add_lucky_egg_modal'));

        // AJAX برای دریافت کد تخفیف
        add_action('wp_ajax_get_lucky_egg_coupon', array($this, 'ajax_get_lucky_egg_coupon'));
        add_action('wp_ajax_nopriv_get_lucky_egg_coupon', array($this, 'ajax_get_lucky_egg_coupon'));

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
     * بارگذاری اسکریپت‌ها و استایل‌ها
     */
    public function enqueue_scripts() {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes' || $settings['enable_lucky_egg'] !== 'yes') {
            return;
        }

        // اضافه کردن جاوااسکریپت
        wp_enqueue_script(
            'wc-discount-tag-lucky-egg',
            false,
            array('jquery'),
            WC_DISCOUNT_TAG_PRO_VERSION,
            true
        );

        // اضافه کردن کد JavaScript inline
        $this->add_inline_javascript();

        // localize script برای AJAX
        wp_localize_script('wc-discount-tag-lucky-egg', 'wcDiscountTag', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_discount_tag_nonce')
        ));
    }

    /**
     * افزودن JavaScript به صورت Inline
     */
    private function add_inline_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // باز کردن مودال با کلیک روی تگ تخفیف
            $(document).on('click', '.wc-discount-tag-pro.clickable, .wc-discount-price-badge.clickable', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var productId = $(this).data('product-id');
                $('#wc-lucky-egg-modal').data('product-id', productId).fadeIn(300);
                $('body').addClass('wc-modal-open');
            });

            // بستن مودال
            $(document).on('click', '.wc-modal-close, .wc-modal-overlay', function() {
                $('#wc-lucky-egg-modal').fadeOut(300);
                $('body').removeClass('wc-modal-open');

                // ریست کردن بازی
                setTimeout(function() {
                    $('.wc-egg-item').removeClass('broken');
                    $('.wc-egg-item').find('img').attr('src', $('.wc-egg-item').first().data('egg-image'));
                    $('.wc-coupon-result').hide().find('.coupon-code-text').text('');
                }, 300);
            });

            // شکستن تخم‌مرغ
            $(document).on('click', '.wc-egg-item:not(.broken)', function() {
                var $egg = $(this);
                var productId = $('#wc-lucky-egg-modal').data('product-id');

                // غیرفعال کردن تخم‌مرغ‌های دیگر
                $('.wc-egg-item').addClass('disabled');

                // انیمیشن شکستن
                $egg.addClass('breaking');

                setTimeout(function() {
                    $egg.removeClass('breaking').addClass('broken');

                    // دریافت کد تخفیف از سرور
                    $.ajax({
                        url: wcDiscountTag.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'get_lucky_egg_coupon',
                            nonce: wcDiscountTag.nonce,
                            product_id: productId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.coupon-code-text').text(response.data.code);
                                $('.wc-coupon-result').fadeIn(300);
                            } else {
                                alert(response.data.message || 'خطا در دریافت کد تخفیف');
                            }
                        },
                        error: function() {
                            alert('خطا در ارتباط با سرور');
                        }
                    });
                }, 500);
            });

            // کپی کد تخفیف
            $(document).on('click', '.copy-coupon-btn', function() {
                var couponCode = $('.coupon-code-text').text();

                // کپی به کلیپ‌برد
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(couponCode).then(function() {
                        $('.copy-coupon-btn').text('✓ کپی شد!');
                        setTimeout(function() {
                            $('.copy-coupon-btn').text('کپی کد');
                        }, 2000);
                    });
                } else {
                    // fallback برای مرورگرهای قدیمی
                    var $temp = $('<input>');
                    $('body').append($temp);
                    $temp.val(couponCode).select();
                    document.execCommand('copy');
                    $temp.remove();

                    $('.copy-coupon-btn').text('✓ کپی شد!');
                    setTimeout(function() {
                        $('.copy-coupon-btn').text('کپی کد');
                    }, 2000);
                }
            });

            // جلوگیری از بسته شدن مودال با کلیک داخل آن
            $(document).on('click', '.wc-modal-content', function(e) {
                e.stopPropagation();
            });
        });
        </script>
        <?php
        wp_add_inline_script('wc-discount-tag-lucky-egg', ob_get_clean());
    }

    /**
     * AJAX handler برای دریافت کد تخفیف
     */
    public function ajax_get_lucky_egg_coupon() {
        check_ajax_referer('wc_discount_tag_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $settings = $this->get_settings();

        // گرفتن کدهای تخفیف از تنظیمات
        $discount_codes = $settings['discount_codes'];

        if (empty($discount_codes)) {
            // اگر کدی تعریف نشده، کدهای فعال ووکامرس را بگیر
            $coupons = $this->get_active_coupons();

            if (empty($coupons)) {
                wp_send_json_error(array('message' => 'هیچ کد تخفیفی موجود نیست'));
                return;
            }

            $random_coupon = $coupons[array_rand($coupons)];
            $coupon_code = $random_coupon->post_title;
        } else {
            // تقسیم کدها با کاما یا خط جدید
            $codes = preg_split('/[\r\n,]+/', $discount_codes);
            $codes = array_map('trim', $codes);
            $codes = array_filter($codes);

            if (empty($codes)) {
                wp_send_json_error(array('message' => 'هیچ کد تخفیفی موجود نیست'));
                return;
            }

            $coupon_code = $codes[array_rand($codes)];
        }

        wp_send_json_success(array(
            'code' => $coupon_code,
            'message' => 'کد تخفیف شما آماده است!'
        ));
    }

    /**
     * گرفتن کوپن‌های فعال ووکامرس
     */
    private function get_active_coupons() {
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'date_expires',
                    'value' => current_time('timestamp'),
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => 'date_expires',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $coupons = get_posts($args);
        return $coupons;
    }

    /**
     * افزودن مودال بازی تخم‌مرغ
     */
    public function add_lucky_egg_modal() {
        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes' || $settings['enable_lucky_egg'] !== 'yes') {
            return;
        }

        $egg_count = intval($settings['lucky_egg_count']);
        if ($egg_count < 1) $egg_count = 3;
        if ($egg_count > 6) $egg_count = 6;

        ?>
        <div id="wc-lucky-egg-modal" class="wc-modal-overlay" style="display: none;">
            <div class="wc-modal-content">
                <button class="wc-modal-close">&times;</button>

                <div class="wc-modal-header">
                    <h2><?php echo esc_html($settings['lucky_egg_title']); ?></h2>
                    <p><?php echo esc_html($settings['lucky_egg_description']); ?></p>
                </div>

                <div class="wc-eggs-container">
                    <?php for ($i = 0; $i < $egg_count; $i++): ?>
                        <div class="wc-egg-item" data-egg-image="<?php echo $this->get_egg_svg(); ?>">
                            <div class="egg-wrapper">
                                <img src="<?php echo $this->get_egg_svg(); ?>" alt="تخم‌مرغ شانس">
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="wc-coupon-result" style="display: none;">
                    <div class="coupon-success-icon">🎉</div>
                    <h3>تبریک! کد تخفیف شما:</h3>
                    <div class="coupon-code">
                        <span class="coupon-code-text"></span>
                    </div>
                    <button class="copy-coupon-btn">کپی کد</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * دریافت SVG تخم‌مرغ
     */
    private function get_egg_svg() {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 240" width="200" height="240">
            <defs>
                <linearGradient id="eggGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#fff5e6;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#ffd4a3;stop-opacity:1" />
                </linearGradient>
                <filter id="shadow">
                    <feDropShadow dx="0" dy="4" stdDeviation="8" flood-opacity="0.3"/>
                </filter>
            </defs>
            <ellipse cx="100" cy="140" rx="70" ry="90" fill="url(#eggGradient)" filter="url(#shadow)" stroke="#e6b88a" stroke-width="3"/>
            <ellipse cx="70" cy="100" rx="20" ry="30" fill="white" opacity="0.5"/>
        </svg>');
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
            // تنظیمات عمومی
            array(
                'title' => __('تنظیمات عمومی', 'wc-discount-tag-pro'),
                'type' => 'title',
                'desc' => __('تنظیمات پایه تگ تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_general'
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
                'id' => 'wc_discount_tag_bg_color',
                'default' => '#ff0000',
                'type' => 'color'
            ),
            array(
                'title' => __('رنگ متن', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_text_color',
                'default' => '#ffffff',
                'type' => 'color'
            ),
            array(
                'title' => __('اندازه فونت', 'wc-discount-tag-pro'),
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
                'id' => 'wc_discount_tag_general_end'
            ),

            // تنظیمات بازی تخم‌مرغ شانسی
            array(
                'title' => __('تنظیمات بازی تخم‌مرغ شانسی', 'wc-discount-tag-pro'),
                'type' => 'title',
                'desc' => __('تنظیمات بازی برای دریافت کد تخفیف', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_lucky_egg'
            ),
            array(
                'title' => __('فعال‌سازی بازی', 'wc-discount-tag-pro'),
                'desc' => __('فعال کردن بازی تخم‌مرغ شانسی', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_enable_lucky_egg',
                'default' => 'yes',
                'type' => 'checkbox'
            ),
            array(
                'title' => __('عنوان مودال', 'wc-discount-tag-pro'),
                'desc' => __('عنوان نمایش داده شده در پاپ‌آپ بازی', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_lucky_egg_title',
                'default' => 'تخم‌مرغ شانس خود را بشکنید!',
                'type' => 'text',
                'css' => 'min-width:400px;'
            ),
            array(
                'title' => __('توضیحات مودال', 'wc-discount-tag-pro'),
                'desc' => __('متن توضیحات زیر عنوان', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_lucky_egg_description',
                'default' => 'یک تخم‌مرغ انتخاب کنید و کد تخفیف خود را دریافت کنید',
                'type' => 'text',
                'css' => 'min-width:400px;'
            ),
            array(
                'title' => __('تعداد تخم‌مرغ', 'wc-discount-tag-pro'),
                'desc' => __('تعداد تخم‌مرغ‌ها در بازی (1 تا 6)', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_lucky_egg_count',
                'default' => '3',
                'type' => 'number',
                'css' => 'width:80px;',
                'custom_attributes' => array(
                    'min' => '1',
                    'max' => '6',
                    'step' => '1'
                )
            ),
            array(
                'title' => __('کدهای تخفیف', 'wc-discount-tag-pro'),
                'desc' => __('کدهای تخفیف را وارد کنید (هر کد در یک خط یا با کاما جدا شود). اگر خالی بگذارید، از کدهای فعال ووکامرس استفاده می‌شود.', 'wc-discount-tag-pro'),
                'id' => 'wc_discount_tag_discount_codes',
                'default' => '',
                'type' => 'textarea',
                'css' => 'min-width:400px; min-height:100px;',
                'placeholder' => 'SUMMER2024\nWINTER20\nSPECIAL10'
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_discount_tag_lucky_egg_end'
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
        $disable_game = get_post_meta($post->ID, '_wc_discount_tag_disable_game', true);

        ?>
        <p>
            <label>
                <input type="checkbox" name="wc_discount_tag_disable" value="1" <?php checked($disable_tag, '1'); ?>>
                <?php _e('غیرفعال کردن تگ تخفیف', 'wc-discount-tag-pro'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="wc_discount_tag_disable_game" value="1" <?php checked($disable_game, '1'); ?>>
                <?php _e('غیرفعال کردن بازی تخم‌مرغ', 'wc-discount-tag-pro'); ?>
            </label>
        </p>
        <p>
            <label for="wc_discount_tag_custom_text">
                <?php _e('متن سفارشی (اختیاری):', 'wc-discount-tag-pro'); ?>
            </label>
            <input type="text" id="wc_discount_tag_custom_text" name="wc_discount_tag_custom_text"
                   value="<?php echo esc_attr($custom_text); ?>"
                   class="widefat"
                   placeholder="<?php _e('متن سفارشی', 'wc-discount-tag-pro'); ?>">
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

        $disable_game = isset($_POST['wc_discount_tag_disable_game']) ? '1' : '0';
        update_post_meta($post_id, '_wc_discount_tag_disable_game', $disable_game);

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
        $enable_game = $settings['enable_lucky_egg'] === 'yes';

        ?>
        <style type="text/css">
            /* تنظیمات عمومی تگ */
            .wc-discount-tag-pro {
                position: absolute;
                z-index: 10;
                <?php echo $enable_game ? 'pointer-events: auto; cursor: pointer;' : 'pointer-events: none;'; ?>
                <?php echo $this->get_position_css($position); ?>
            }

            .wc-discount-tag-pro.clickable:hover .discount-badge {
                transform: scale(1.05);
                box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
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
                transition: all 0.3s ease;
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

            /* تگ قیمت */
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
                transition: all 0.3s ease;
                <?php echo $enable_game ? 'cursor: pointer;' : ''; ?>
            }

            .wc-discount-price-badge.clickable:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            }

            /* مودال بازی */
            .wc-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .wc-modal-content {
                background: #fff;
                border-radius: 20px;
                max-width: 600px;
                width: 100%;
                padding: 40px;
                position: relative;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                animation: modalSlideIn 0.3s ease;
            }

            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .wc-modal-close {
                position: absolute;
                top: 15px;
                right: 15px;
                width: 35px;
                height: 35px;
                border: none;
                background: #f0f0f0;
                border-radius: 50%;
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
                color: #666;
                transition: all 0.3s ease;
            }

            .wc-modal-close:hover {
                background: #e0e0e0;
                transform: rotate(90deg);
            }

            .wc-modal-header {
                text-align: center;
                margin-bottom: 30px;
            }

            .wc-modal-header h2 {
                font-size: 28px;
                margin: 0 0 10px;
                color: #333;
            }

            .wc-modal-header p {
                font-size: 16px;
                color: #666;
                margin: 0;
            }

            .wc-eggs-container {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                justify-content: center;
                margin: 30px 0;
            }

            .wc-egg-item {
                flex: 0 0 calc(33.333% - 14px);
                max-width: 120px;
                cursor: pointer;
                transition: transform 0.3s ease;
            }

            .wc-egg-item:hover:not(.broken):not(.disabled) {
                transform: translateY(-10px);
            }

            .wc-egg-item.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .egg-wrapper {
                position: relative;
            }

            .egg-wrapper img {
                width: 100%;
                height: auto;
                display: block;
                filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            }

            .wc-egg-item.breaking {
                animation: eggShake 0.5s ease;
            }

            @keyframes eggShake {
                0%, 100% { transform: rotate(0deg); }
                25% { transform: rotate(-10deg); }
                75% { transform: rotate(10deg); }
            }

            .wc-egg-item.broken .egg-wrapper img {
                opacity: 0;
                transform: scale(0);
                transition: all 0.3s ease;
            }

            .wc-coupon-result {
                text-align: center;
                padding: 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 15px;
                color: white;
                animation: resultFadeIn 0.5s ease;
            }

            @keyframes resultFadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .coupon-success-icon {
                font-size: 60px;
                margin-bottom: 15px;
                animation: iconBounce 0.6s ease;
            }

            @keyframes iconBounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.2); }
            }

            .wc-coupon-result h3 {
                font-size: 24px;
                margin: 0 0 20px;
                color: white;
            }

            .coupon-code {
                background: white;
                color: #333;
                padding: 15px 25px;
                border-radius: 10px;
                font-size: 24px;
                font-weight: bold;
                letter-spacing: 2px;
                margin-bottom: 20px;
                border: 3px dashed #667eea;
            }

            .copy-coupon-btn {
                background: white;
                color: #667eea;
                border: none;
                padding: 12px 30px;
                border-radius: 25px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .copy-coupon-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            }

            body.wc-modal-open {
                overflow: hidden;
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
                .wc-modal-content {
                    padding: 30px 20px;
                }

                .wc-modal-header h2 {
                    font-size: 22px;
                }

                .wc-eggs-container {
                    gap: 15px;
                }

                .wc-egg-item {
                    flex: 0 0 calc(50% - 8px);
                    max-width: 100px;
                }

                .coupon-code {
                    font-size: 18px;
                    padding: 12px 15px;
                }
            }

            @media (max-width: 480px) {
                .wc-egg-item {
                    flex: 0 0 calc(50% - 8px);
                    max-width: 80px;
                }
            }

            /* تنظیمات محصولات */
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
                return 'padding: 8px 20px; border-radius: 0;';
            case 'corner':
                return 'padding: 8px 15px; border-radius: 0 0 5px 0;';
            case 'star':
                return 'padding: 15px; clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%); width: 80px; height: 80px; min-width: auto;';
            case 'hexagon':
                return 'padding: 10px 15px; clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%); min-width: 80px;';
            default:
                return 'padding: 8px 15px; border-radius: 5px; min-width: 80px;';
        }
    }

    /**
     * بررسی فعال بودن بازی برای محصول
     */
    private function is_game_enabled_for_product($product_id) {
        $settings = $this->get_settings();

        // بررسی فعال بودن کلی بازی
        if ($settings['enable_lucky_egg'] !== 'yes') {
            return false;
        }

        // بررسی غیرفعال نبودن بازی برای این محصول
        $disable_game = get_post_meta($product_id, '_wc_discount_tag_disable_game', true);
        return $disable_game !== '1';
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

        $is_game_enabled = $this->is_game_enabled_for_product($product->get_id());
        $clickable_class = $is_game_enabled ? 'clickable' : '';

        $class = 'wc-discount-tag-pro wc-discount-tag-pro-' . $type . ' ' . $clickable_class;

        ?>
        <div class="<?php echo esc_attr($class); ?>" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
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
        if (!$product->is_on_sale()) {
            return $price;
        }

        $settings = $this->get_settings();

        if ($settings['enabled'] !== 'yes') {
            return $price;
        }

        if (!$this->should_show_tag($product->get_id())) {
            return $price;
        }

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

        $custom_text = $this->get_custom_text($product->get_id());
        $text = $custom_text ? $custom_text : $settings['text'];
        $percentage = $this->get_discount_percentage($product);

        $is_game_enabled = $this->is_game_enabled_for_product($product->get_id());
        $clickable_class = $is_game_enabled ? 'clickable' : '';

        $badge_html = '<span class="wc-discount-price-badge ' . $clickable_class . '" data-product-id="' . esc_attr($product->get_id()) . '">';
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
