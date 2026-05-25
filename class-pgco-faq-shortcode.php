<?php
/**
 * PGCO FAQ — Shortcode + JSON-LD Schema
 *
 * Usage: [pgco_faq id="POST_ID"]
 *
 * Schema timing fix:
 *   wp_head fires BEFORE the_content, so we cannot rely on the shortcode
 *   render pass to populate $schema_pool in time.
 *   Solution: on the 'wp' action (query resolved, before any output) we
 *   scan the current post content for [pgco_faq id="X"] and pre-load
 *   FAQ items into $schema_pool so wp_head sees them.
 *
 * @package PGCO_FAQ
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

class PGCO_FAQ_Shortcode {

    /**
     * Accumulated FAQ items — pre-loaded before wp_head fires.
     *
     * @var array
     */
    private static $schema_pool = [];

    /**
     * Post IDs already loaded — prevents duplicates.
     *
     * @var int[]
     */
    private static $loaded_ids = [];

    /* ---------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------ */
    public static function init() {
        add_shortcode( 'pgco_faq', [ __CLASS__, 'render' ] );

        // Pre-scan must run AFTER the query is resolved but BEFORE any output.
        add_action( 'wp',      [ __CLASS__, 'prescan_content' ] );
        add_action( 'wp_head', [ __CLASS__, 'output_schema' ], 99 );
    }

    /* ---------------------------------------------------------------
     * Pre-scan post content for shortcodes and load FAQ items early.
     * Runs on 'wp' hook — no output has started yet.
     * ------------------------------------------------------------ */
    public static function prescan_content() {
        global $wp_query;

        if ( empty( $wp_query->posts ) ) {
            return;
        }

        foreach ( $wp_query->posts as $post ) {
            if ( ! is_object( $post ) || empty( $post->post_content ) ) {
                continue;
            }
            // Match [pgco_faq id="N"] or [pgco_faq id='N'] or [pgco_faq id=N]
            if ( preg_match_all( '/\[pgco_faq[^\]]*\bid=["\']?(\d+)["\']?/i', $post->post_content, $matches ) ) {
                foreach ( $matches[1] as $faq_id ) {
                    self::load_items_for_post( (int) $faq_id );
                }
            }
        }
    }

    /* ---------------------------------------------------------------
     * Load FAQ items for one pgco_faq post into the schema pool.
     * Idempotent: skips IDs already processed.
     * ------------------------------------------------------------ */
    private static function load_items_for_post( $post_id ) {
        if ( in_array( $post_id, self::$loaded_ids, true ) ) {
            return;
        }

        $faq_post = get_post( $post_id );
        if (
            ! $faq_post ||
            'pgco_faq' !== $faq_post->post_type ||
            'publish'  !== $faq_post->post_status
        ) {
            return;
        }

        $items = get_post_meta( $post_id, '_pgco_faq_items', true );
        if ( ! is_array( $items ) || empty( $items ) ) {
            return;
        }

        self::$loaded_ids[] = $post_id;

        foreach ( $items as $item ) {
            $q = isset( $item['question'] ) ? trim( $item['question'] ) : '';
            $a = isset( $item['answer'] )   ? trim( $item['answer'] )   : '';
            if ( '' !== $q || '' !== $a ) {
                self::$schema_pool[] = [ 'question' => $q, 'answer' => $a ];
            }
        }
    }

    /* ---------------------------------------------------------------
     * Shortcode handler — HTML output only.
     * Schema is already populated via prescan_content().
     * ------------------------------------------------------------ */
    public static function render( $atts ) {
        $atts = shortcode_atts(
            [ 'id' => 0 ],
            $atts,
            'pgco_faq'
        );

        $post_id = absint( $atts['id'] );
        if ( ! $post_id ) {
            return '<!-- pgco_faq: شناسه پست وارد نشده -->';
        }

        $faq_post = get_post( $post_id );
        if (
            ! $faq_post ||
            'pgco_faq' !== $faq_post->post_type ||
            'publish'  !== $faq_post->post_status
        ) {
            return '<!-- pgco_faq: گروه یافت نشد یا منتشر نشده -->';
        }

        $items = get_post_meta( $post_id, '_pgco_faq_items', true );
        if ( ! is_array( $items ) || empty( $items ) ) {
            return '<!-- pgco_faq: هیچ سوالی در این گروه وجود ندارد -->';
        }

        // Edge-case fallback: if prescan didn't catch it (e.g. REST/preview), load now.
        self::load_items_for_post( $post_id );

        $html = '<div class="pgco-faq-group" id="pgco-faq-' . esc_attr( $post_id ) . '" aria-label="' . esc_attr( get_the_title( $post_id ) ) . '">';

        foreach ( $items as $item ) {
            $question = isset( $item['question'] ) ? trim( $item['question'] ) : '';
            $answer   = isset( $item['answer'] )   ? trim( $item['answer'] )   : '';

            if ( '' === $question && '' === $answer ) {
                continue;
            }

            $html .= '<details open>';
            $html .= '<summary><h4>' . esc_html( $question ) . '</h4></summary>';
            $html .= '<p>' . nl2br( esc_html( $answer ) ) . '</p>';
            $html .= '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    /* ---------------------------------------------------------------
     * Output merged FAQPage JSON-LD schema into <head>.
     * By this point prescan_content() has already filled $schema_pool.
     * ------------------------------------------------------------ */
    public static function output_schema() {
        if ( empty( self::$schema_pool ) ) {
            return;
        }

        $entities = [];
        foreach ( self::$schema_pool as $item ) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags( $item['question'] ),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $item['answer'] ),
                ],
            ];
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];

        $json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

        echo "\n<!-- PGCO FAQ Schema — pgco.ir -->\n";
        echo '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
    }
}
