<?php
/**
 * PGCO FAQ — Custom Post Type + Admin Columns
 *
 * @package PGCO_FAQ
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class PGCO_FAQ_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );

        // Admin list columns
        add_filter( 'manage_pgco_faq_posts_columns',       [ __CLASS__, 'add_columns' ] );
        add_action( 'manage_pgco_faq_posts_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
        add_action( 'admin_head',                          [ __CLASS__, 'column_styles' ] );
        add_action( 'admin_footer',                        [ __CLASS__, 'column_scripts' ] );
    }

    /* ---------------------------------------------------------------
     * Register post type
     * ------------------------------------------------------------ */
    public static function register_post_type() {
        $labels = [
            'name'               => 'سوالات متداول',
            'singular_name'      => 'گروه سوالات متداول',
            'add_new'            => 'افزودن گروه جدید',
            'add_new_item'       => 'افزودن گروه سوالات متداول',
            'edit_item'          => 'ویرایش گروه',
            'new_item'           => 'گروه جدید',
            'view_item'          => 'مشاهده گروه',
            'search_items'       => 'جستجو در سوالات متداول',
            'not_found'          => 'موردی یافت نشد',
            'not_found_in_trash' => 'زباله‌دان خالی است',
            'all_items'          => 'همه گروه‌ها',
            'menu_name'          => 'سوالات متداول',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-editor-help',
            'supports'           => [ 'title' ],
        ];

        register_post_type( 'pgco_faq', $args );
    }

    /* ---------------------------------------------------------------
     * Add custom columns
     * ------------------------------------------------------------ */
    public static function add_columns( $columns ) {
        // Keep cb + title, inject our columns, then date
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $new['pgco_faq_count']     = 'تعداد سوالات';
                $new['pgco_faq_shortcode'] = 'شورت‌کد';
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    /* ---------------------------------------------------------------
     * Render column content
     * ------------------------------------------------------------ */
    public static function render_column( $column, $post_id ) {
        if ( 'pgco_faq_count' === $column ) {
            $items = get_post_meta( $post_id, '_pgco_faq_items', true );
            $count = is_array( $items ) ? count( $items ) : 0;
            echo '<span style="font-weight:600;color:#2271b1;">' . esc_html( $count ) . '</span> سوال';
        }

        if ( 'pgco_faq_shortcode' === $column ) {
            $sc = '[pgco_faq id="' . $post_id . '"]';
            ?>
            <div class="pgco-col-sc-wrap">
                <code class="pgco-col-sc" data-sc="<?php echo esc_attr( $sc ); ?>">
                    <?php echo esc_html( $sc ); ?>
                </code>
                <button
                    type="button"
                    class="pgco-col-copy button button-small"
                    data-sc="<?php echo esc_attr( $sc ); ?>"
                    title="کپی شورت‌کد"
                >📋</button>
                <span class="pgco-col-copied" style="display:none;color:green;font-size:11px;margin-right:4px;">✔ کپی شد</span>
            </div>
            <?php
        }
    }

    /* ---------------------------------------------------------------
     * Column styles (only on pgco_faq list screen)
     * ------------------------------------------------------------ */
    public static function column_styles() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-pgco_faq' !== $screen->id ) {
            return;
        }
        ?>
        <style>
        .column-pgco_faq_shortcode { width: 260px; }
        .column-pgco_faq_count     { width: 90px; text-align: center; }

        .pgco-col-sc-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            direction: ltr;
        }
        .pgco-col-sc {
            background: #f0f0f1;
            border-radius: 3px;
            padding: 3px 7px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
            display: inline-block;
            vertical-align: middle;
            transition: background .15s;
        }
        .pgco-col-sc:hover { background: #dde0e5; }
        .pgco-col-copy {
            flex-shrink: 0;
            cursor: pointer;
            padding: 2px 6px !important;
            font-size: 13px !important;
            line-height: 1.6 !important;
        }
        </style>
        <?php
    }

    /* ---------------------------------------------------------------
     * Column scripts
     * ------------------------------------------------------------ */
    public static function column_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || 'edit-pgco_faq' !== $screen->id ) {
            return;
        }
        ?>
        <script>
        (function () {
            function copyShortcode(sc, wrap) {
                navigator.clipboard.writeText(sc).then(function () {
                    var copied = wrap.querySelector('.pgco-col-copied');
                    copied.style.display = 'inline';
                    setTimeout(function () { copied.style.display = 'none'; }, 2000);
                });
            }

            document.addEventListener('click', function (e) {
                // Click on copy button
                var btn = e.target.closest('.pgco-col-copy');
                if (btn) {
                    copyShortcode(btn.dataset.sc, btn.closest('.pgco-col-sc-wrap'));
                    return;
                }
                // Click on code element
                var code = e.target.closest('.pgco-col-sc');
                if (code) {
                    copyShortcode(code.dataset.sc, code.closest('.pgco-col-sc-wrap'));
                }
            });
        }());
        </script>
        <?php
    }
}
