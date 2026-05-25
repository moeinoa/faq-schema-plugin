<?php
/**
 * PGCO FAQ — Meta Box (Repeater Q&A)
 *
 * @package PGCO_FAQ
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class PGCO_FAQ_Metabox {

    public static function init() {
        add_action( 'add_meta_boxes',       [ __CLASS__, 'register' ] );
        add_action( 'save_post_pgco_faq',   [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'admin_head',           [ __CLASS__, 'inline_styles' ] );
        add_action( 'admin_footer',         [ __CLASS__, 'inline_scripts' ] );
    }

    /* ---------------------------------------------------------------
     * Register meta boxes
     * ------------------------------------------------------------ */
    public static function register() {
        add_meta_box(
            'pgco_faq_items',
            'سوال و جواب‌ها',
            [ __CLASS__, 'render_items' ],
            'pgco_faq',
            'normal',
            'high'
        );
        add_meta_box(
            'pgco_faq_shortcode_box',
            'شورت‌کد این گروه',
            [ __CLASS__, 'render_shortcode' ],
            'pgco_faq',
            'side',
            'high'
        );
    }

    /* ---------------------------------------------------------------
     * Render: repeater items
     * ------------------------------------------------------------ */
    public static function render_items( $post ) {
        wp_nonce_field( 'pgco_faq_save_' . $post->ID, 'pgco_faq_nonce' );

        $items = get_post_meta( $post->ID, '_pgco_faq_items', true );
        if ( ! is_array( $items ) ) {
            $items = [];
        }
        ?>
        <div id="pgco-faq-wrapper">
            <div id="pgco-faq-items">
                <?php foreach ( $items as $i => $item ) :
                    $q = $item['question'] ?? '';
                    $a = $item['answer']   ?? '';
                ?>
                    <div class="pgco-faq-item" data-index="<?php echo esc_attr( $i ); ?>">
                        <div class="pgco-faq-item-header">
                            <span class="pgco-faq-drag dashicons dashicons-move" title="جابه‌جایی"></span>
                            <span class="pgco-faq-num"><?php echo esc_html( $i + 1 ); ?></span>
                            <button type="button" class="pgco-faq-toggle button button-small">▾ جمع کردن</button>
                            <button type="button" class="pgco-faq-remove button-link-delete">✕ حذف</button>
                        </div>
                        <div class="pgco-faq-item-body">
                            <label>سوال <span class="required">*</span></label>
                            <input
                                type="text"
                                name="pgco_faq_items[<?php echo $i; ?>][question]"
                                value="<?php echo esc_attr( $q ); ?>"
                                placeholder="سوال را وارد کنید…"
                                required
                            />
                            <label>جواب <span class="required">*</span></label>
                            <textarea
                                name="pgco_faq_items[<?php echo $i; ?>][answer]"
                                rows="3"
                                placeholder="جواب را وارد کنید…"
                                required
                            ><?php echo esc_textarea( $a ); ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="pgco-faq-add" class="button button-primary">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span>
                افزودن سوال جدید
            </button>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------
     * Render: shortcode sidebar
     * ------------------------------------------------------------ */
    public static function render_shortcode( $post ) {
        if ( in_array( $post->post_status, [ 'publish', 'draft', 'pending' ], true ) && $post->ID ) {
            $sc = '[pgco_faq id="' . $post->ID . '"]';
            ?>
            <div style="direction:ltr;text-align:center;">
                <code id="pgco-sc-code" style="display:block;background:#f0f0f1;padding:10px 6px;border-radius:4px;font-size:13px;cursor:pointer;" title="کلیک برای کپی">
                    <?php echo esc_html( $sc ); ?>
                </code>
                <button type="button" id="pgco-sc-copy" class="button button-small" style="margin-top:8px;">
                    📋 کپی شورت‌کد
                </button>
                <p id="pgco-sc-copied" style="color:green;font-size:11px;display:none;">✔ کپی شد!</p>
            </div>
            <p style="font-size:11px;color:#888;direction:rtl;margin-top:10px;">
                این شورت‌کد را داخل مقاله یا برگه مورد نظر قرار دهید.
            </p>
            <script>
            document.getElementById('pgco-sc-copy').addEventListener('click', function(){
                var text = document.getElementById('pgco-sc-code').textContent.trim();
                navigator.clipboard.writeText(text).then(function(){
                    document.getElementById('pgco-sc-copied').style.display='block';
                    setTimeout(function(){ document.getElementById('pgco-sc-copied').style.display='none'; }, 2000);
                });
            });
            document.getElementById('pgco-sc-code').addEventListener('click', function(){
                document.getElementById('pgco-sc-copy').click();
            });
            </script>
            <?php
        } else {
            echo '<p style="color:#888;font-size:12px;direction:rtl;">پس از ذخیره، شورت‌کد نمایش داده می‌شود.</p>';
        }
    }

    /* ---------------------------------------------------------------
     * Save meta data (security hardened)
     * ------------------------------------------------------------ */
    public static function save( $post_id, $post ) {
        // 1. Verify nonce
        if (
            ! isset( $_POST['pgco_faq_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pgco_faq_nonce'] ) ), 'pgco_faq_save_' . $post_id )
        ) {
            return;
        }

        // 2. Skip autosave / revisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // 3. Capability check
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // 4. Verify post type
        if ( 'pgco_faq' !== $post->post_type ) {
            return;
        }

        // 5. Sanitize & save
        $raw   = isset( $_POST['pgco_faq_items'] ) ? $_POST['pgco_faq_items'] : [];
        $clean = [];

        if ( is_array( $raw ) ) {
            foreach ( $raw as $item ) {
                $q = sanitize_text_field( wp_unslash( $item['question'] ?? '' ) );
                $a = sanitize_textarea_field( wp_unslash( $item['answer']   ?? '' ) );
                if ( '' !== $q || '' !== $a ) {
                    $clean[] = [
                        'question' => $q,
                        'answer'   => $a,
                    ];
                }
            }
        }

        update_post_meta( $post_id, '_pgco_faq_items', $clean );
    }

    /* ---------------------------------------------------------------
     * Inline admin styles (only on pgco_faq screen)
     * ------------------------------------------------------------ */
    public static function inline_styles() {
        $screen = get_current_screen();
        if ( ! $screen || 'pgco_faq' !== $screen->post_type ) {
            return;
        }
        ?>
        <style id="pgco-faq-admin-css">
        #pgco-faq-wrapper { direction: rtl; font-family: inherit; }

        .pgco-faq-item {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-right: 4px solid #2271b1;
            border-radius: 4px;
            margin-bottom: 10px;
            transition: border-color .2s;
        }
        .pgco-faq-item:hover { border-color: #2271b1; }

        .pgco-faq-item-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #f6f7f7;
            border-bottom: 1px solid #e2e4e7;
            border-radius: 3px 3px 0 0;
            cursor: grab;
        }
        .pgco-faq-drag { color: #8c8f94; cursor: grab; }
        .pgco-faq-num {
            font-weight: 700;
            color: #2271b1;
            font-size: 13px;
            min-width: 22px;
        }
        .pgco-faq-remove { color: #b32d2e !important; margin-right: auto !important; }
        .pgco-faq-toggle { font-size: 11px !important; }

        .pgco-faq-item-body { padding: 14px; }
        .pgco-faq-item-body label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1d2327;
        }
        .required { color: #b32d2e; }
        .pgco-faq-item-body input[type="text"],
        .pgco-faq-item-body textarea {
            width: 100%;
            direction: rtl;
            margin-bottom: 10px;
            border-color: #c3c4c7;
        }
        .pgco-faq-item-body input[type="text"]:focus,
        .pgco-faq-item-body textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }

        #pgco-faq-add { margin-top: 6px; display: inline-flex; align-items: center; gap: 6px; }

        /* sortable placeholder */
        .pgco-faq-placeholder {
            border: 2px dashed #2271b1;
            background: #f0f6fc;
            border-radius: 4px;
            height: 60px;
            margin-bottom: 10px;
        }
        </style>
        <?php
    }

    /* ---------------------------------------------------------------
     * Inline admin scripts (jQuery UI Sortable + repeater logic)
     * ------------------------------------------------------------ */
    public static function inline_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || 'pgco_faq' !== $screen->post_type ) {
            return;
        }

        // Count existing items for JS index seed
        global $post;
        $existing = is_object( $post ) ? get_post_meta( $post->ID, '_pgco_faq_items', true ) : [];
        $seed     = is_array( $existing ) ? count( $existing ) : 0;
        ?>
        <script id="pgco-faq-admin-js">
        /* global jQuery */
        (function ($) {
            'use strict';

            var idx = <?php echo (int) $seed; ?>;

            /* ---- helpers ---- */
            function itemTpl(i) {
                return '<div class="pgco-faq-item" data-index="' + i + '">' +
                    '<div class="pgco-faq-item-header">' +
                    '<span class="pgco-faq-drag dashicons dashicons-move" title="جابه‌جایی"></span>' +
                    '<span class="pgco-faq-num">' + (i + 1) + '</span>' +
                    '<button type="button" class="pgco-faq-toggle button button-small">▾ جمع کردن</button>' +
                    '<button type="button" class="pgco-faq-remove button-link-delete">✕ حذف</button>' +
                    '</div>' +
                    '<div class="pgco-faq-item-body">' +
                    '<label>سوال <span class="required">*</span></label>' +
                    '<input type="text" name="pgco_faq_items[' + i + '][question]" placeholder="سوال را وارد کنید…" />' +
                    '<label>جواب <span class="required">*</span></label>' +
                    '<textarea name="pgco_faq_items[' + i + '][answer]" rows="3" placeholder="جواب را وارد کنید…"></textarea>' +
                    '</div></div>';
            }

            function reIndex() {
                $('#pgco-faq-items .pgco-faq-item').each(function (i) {
                    $(this).attr('data-index', i).find('.pgco-faq-num').text(i + 1);
                    $(this).find('input[type="text"]').attr('name', 'pgco_faq_items[' + i + '][question]');
                    $(this).find('textarea').attr('name', 'pgco_faq_items[' + i + '][answer]');
                });
                idx = $('#pgco-faq-items .pgco-faq-item').length;
            }

            /* ---- add ---- */
            $(document).on('click', '#pgco-faq-add', function () {
                $('#pgco-faq-items').append(itemTpl(idx));
                idx++;
                $('#pgco-faq-items .pgco-faq-item:last-child input').trigger('focus');
            });

            /* ---- remove ---- */
            $(document).on('click', '.pgco-faq-remove', function () {
                if (confirm('این سوال حذف شود؟')) {
                    $(this).closest('.pgco-faq-item').remove();
                    reIndex();
                }
            });

            /* ---- toggle collapse ---- */
            $(document).on('click', '.pgco-faq-toggle', function () {
                var $body = $(this).closest('.pgco-faq-item').find('.pgco-faq-item-body');
                var collapsed = $body.is(':hidden');
                $body.slideToggle(150);
                $(this).text(collapsed ? '▾ جمع کردن' : '▸ باز کردن');
            });

            /* ---- sortable ---- */
            $(function () {
                $('#pgco-faq-items').sortable({
                    handle:      '.pgco-faq-drag',
                    placeholder: 'pgco-faq-placeholder',
                    axis:        'y',
                    update:      reIndex,
                    tolerance:   'pointer',
                });
            });

        }(jQuery));
        </script>
        <?php
    }
}
