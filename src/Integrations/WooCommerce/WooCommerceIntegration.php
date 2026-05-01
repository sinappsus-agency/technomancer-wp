<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Integrations\WooCommerce;

final class WooCommerceIntegration
{
    public function register(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_product_options_general_product_data', [$this, 'addFields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveFields']);

        add_action('admin_menu', [$this, 'settingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);

        add_action('woocommerce_before_single_product_summary', [$this, 'overrideSingle'], 5);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'overrideLoop'], 5);

        add_action('admin_enqueue_scripts', [$this, 'adminAssets']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function addFields(): void
    {
        if (! function_exists('woocommerce_wp_text_input') || ! function_exists('woocommerce_wp_select')) {
            return;
        }

        echo '<div class="options_group">';

        woocommerce_wp_text_input([
            'id' => '_fv_video_mp4',
            'label' => 'Featured Video (MP4)',
            'placeholder' => 'https://...',
            'desc_tip' => true,
            'description' => 'Paste a direct MP4 URL, or use Select/Upload to choose from Media Library.',
        ]);
        echo '<p class="form-field _fv_video_mp4_field">';
        echo '<button type="button" class="button tmwp-video-select" data-target="_fv_video_mp4" data-mime="video/mp4">Select or Upload MP4</button> ';
        echo '<button type="button" class="button tmwp-video-clear" data-target="_fv_video_mp4">Clear</button>';
        echo '<span class="tmwp-video-feedback" data-target="_fv_video_mp4" style="margin-left:8px;"></span>';
        echo '</p>';

        woocommerce_wp_text_input([
            'id' => '_fv_video_webm',
            'label' => 'WebM (optional)',
            'placeholder' => 'https://...',
            'desc_tip' => true,
            'description' => 'Optional WebM URL, or choose a WebM file from Media Library.',
        ]);
        echo '<p class="form-field _fv_video_webm_field">';
        echo '<button type="button" class="button tmwp-video-select" data-target="_fv_video_webm" data-mime="video/webm">Select or Upload WebM</button> ';
        echo '<button type="button" class="button tmwp-video-clear" data-target="_fv_video_webm">Clear</button>';
        echo '<span class="tmwp-video-feedback" data-target="_fv_video_webm" style="margin-left:8px;"></span>';
        echo '</p>';

        woocommerce_wp_select([
            'id' => '_fv_mode',
            'label' => 'Playback Mode',
            'options' => [
                'autoplay' => 'Autoplay',
                'hover' => 'Play on Hover',
            ],
        ]);

        echo '</div>';
    }

    public function adminAssets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_media();
        wp_register_script('tmwp-featured-video-admin', '', ['jquery'], TECHNOMANCER_WP_VERSION, true);
        wp_enqueue_script('tmwp-featured-video-admin');
        wp_add_inline_script('tmwp-featured-video-admin', "
            (function($) {
                function setFeedback(targetId, message, kind) {
                    var el = $('.tmwp-video-feedback').filter(function() {
                        return $(this).data('target') === targetId;
                    });
                    if (!el.length) {
                        return;
                    }

                    var color = '#2271b1';
                    if (kind === 'error') {
                        color = '#b32d2e';
                    }
                    if (kind === 'success') {
                        color = '#008a20';
                    }

                    el.text(message || '').css('color', color);
                }

                function selectVideo(targetId, mimeType) {
                    var frame = wp.media({
                        title: 'Select Video',
                        button: { text: 'Use this video' },
                        multiple: false,
                        library: { type: 'video' }
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (!attachment || !attachment.url) {
                            return;
                        }

                        if (mimeType && attachment.mime && attachment.mime !== mimeType) {
                            if (mimeType === 'video/webm' && attachment.mime.indexOf('video/webm') !== 0) {
                                setFeedback(targetId, 'Selected file is not WebM. Please choose a WebM video.', 'error');
                                return;
                            }
                            if (mimeType === 'video/mp4' && attachment.mime.indexOf('video/mp4') !== 0) {
                                setFeedback(targetId, 'Selected file is not MP4. Please choose an MP4 video.', 'error');
                                return;
                            }
                        }

                        $('#' + targetId).val(attachment.url).trigger('change');
                        setFeedback(targetId, 'Video selected from Media Library.', 'success');
                    });

                    frame.open();
                }

                $(document).on('click', '.tmwp-video-select', function(e) {
                    e.preventDefault();
                    var targetId = $(this).data('target');
                    var mimeType = $(this).data('mime') || '';
                    if (!targetId) {
                        return;
                    }
                    setFeedback(targetId, '', 'info');
                    selectVideo(targetId, mimeType);
                });

                $(document).on('click', '.tmwp-video-clear', function(e) {
                    e.preventDefault();
                    var targetId = $(this).data('target');
                    if (!targetId) {
                        return;
                    }
                    $('#' + targetId).val('').trigger('change');
                    setFeedback(targetId, 'Video cleared.', 'info');
                });
            })(jQuery);
        ");
    }

    public function saveFields($product): void
    {
        if (! $product instanceof \WC_Product) {
            return;
        }

        $mp4 = esc_url_raw((string) ($_POST['_fv_video_mp4'] ?? ''));
        $webm = esc_url_raw((string) ($_POST['_fv_video_webm'] ?? ''));
        $mode = sanitize_text_field((string) ($_POST['_fv_mode'] ?? 'autoplay'));
        if (! in_array($mode, ['autoplay', 'hover'], true)) {
            $mode = 'autoplay';
        }

        $product->update_meta_data('_fv_video_mp4', $mp4);
        $product->update_meta_data('_fv_video_webm', $webm);
        $product->update_meta_data('_fv_mode', $mode);
    }

    public function settingsPage(): void
    {
        add_options_page(
            'Featured Video Settings',
            'Featured Video',
            'manage_options',
            'fv-settings',
            [$this, 'settingsHtml']
        );
    }

    public function registerSettings(): void
    {
        register_setting('fv_settings_group', 'fv_disable_mobile_autoplay', [
            'type' => 'boolean',
            'sanitize_callback' => static function ($value): int {
                return ! empty($value) ? 1 : 0;
            },
            'default' => 0,
        ]);
    }

    public function settingsHtml(): void
    {
        ?>
        <div class="wrap">
            <h1>Featured Video Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('fv_settings_group'); ?>

                <label>
                    <input type="checkbox" name="fv_disable_mobile_autoplay" value="1"
                    <?php checked(1, (int) get_option('fv_disable_mobile_autoplay', 0)); ?>>
                    Disable autoplay on mobile
                </label>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render(\WC_Product $product, string $size = 'large'): bool
    {
        $mp4 = (string) get_post_meta($product->get_id(), '_fv_video_mp4', true);
        if ($mp4 === '') {
            return false;
        }

        $webm = (string) get_post_meta($product->get_id(), '_fv_video_webm', true);
        $mode = (string) get_post_meta($product->get_id(), '_fv_mode', true);
        if (! in_array($mode, ['autoplay', 'hover'], true)) {
            $mode = 'autoplay';
        }

        $image = get_the_post_thumbnail_url($product->get_id(), $size);
        ?>
        <div class="fv-media" data-mode="<?php echo esc_attr($mode); ?>">
            <?php if (is_string($image) && $image !== '') : ?>
                <img src="<?php echo esc_url($image); ?>" class="fv-img" alt="">
            <?php endif; ?>

            <video class="fv-video" muted playsinline preload="none" aria-hidden="true">
                <?php if ($webm !== '') : ?>
                    <source src="<?php echo esc_url($webm); ?>" type="video/webm">
                <?php endif; ?>
                <source src="<?php echo esc_url($mp4); ?>" type="video/mp4">
            </video>
        </div>
        <?php

        return true;
    }

    public function overrideSingle(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        if (! $this->render($product)) {
            return;
        }

        remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
    }

    public function overrideLoop(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        if (! $this->render($product, 'woocommerce_thumbnail')) {
            return;
        }

        remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    }

    public function assets(): void
    {
        if (! function_exists('is_product') || ! function_exists('is_shop')) {
            return;
        }

        if (! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag()) {
            return;
        }

        wp_register_style('snc-featured-video-inline', false, [], TECHNOMANCER_WP_VERSION);
        wp_enqueue_style('snc-featured-video-inline');
        wp_add_inline_style('snc-featured-video-inline', '
            .fv-media { position: relative; overflow: hidden; }
            .fv-media img, .fv-media video { width: 100%; display: block; }
            .fv-media .fv-img { transition: opacity .35s ease; }
            .fv-video { position: absolute; top: 0; left: 0; opacity: 0; transition: opacity .35s ease; }
        ');

        wp_register_script('snc-featured-video-inline', '', [], TECHNOMANCER_WP_VERSION, true);
        wp_enqueue_script('snc-featured-video-inline');

        $disableMobile = get_option('fv_disable_mobile_autoplay') ? 'true' : 'false';
        wp_add_inline_script('snc-featured-video-inline', "
            document.addEventListener('DOMContentLoaded', function () {
                var containers = document.querySelectorAll('.fv-media');
                if (!containers.length || typeof IntersectionObserver === 'undefined') {
                    return;
                }

                var isMobile = /iPhone|iPad|Android/i.test(navigator.userAgent);
                var disableMobile = {$disableMobile};

                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        var container = entry.target;
                        var video = container.querySelector('video');
                        var img = container.querySelector('img');
                        var mode = container.dataset.mode;

                        if (!video) {
                            observer.unobserve(container);
                            return;
                        }

                        video.load();

                        video.addEventListener('canplaythrough', function onCanPlay() {
                            video.style.opacity = '1';

                            if (!(disableMobile && isMobile) && mode === 'autoplay') {
                                video.play().catch(function() {});
                            }

                            if (img) {
                                img.style.opacity = '0';
                            }

                            video.removeEventListener('canplaythrough', onCanPlay);
                        });

                        if (mode === 'hover') {
                            container.addEventListener('mouseenter', function () {
                                video.play().catch(function() {});
                            });
                            container.addEventListener('mouseleave', function () {
                                video.pause();
                                video.currentTime = 0;
                            });
                        }

                        observer.unobserve(container);
                    });
                });

                containers.forEach(function(el) {
                    observer.observe(el);
                });
            });
        ");
    }
}

