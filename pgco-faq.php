<?php
/**
 * Plugin Name: سوالات متداول
 * Plugin URI:  https://pgco.info
 * Description: مدیریت گروه‌های سوالات متداول با شورت‌کد، اسکیمای FAQPage و خروجی معنایی HTML5
 * Version:     1.0.3
 * Author: Moein Akbari
 * Author URI: https://moein-akbari.ir/
 * Text Domain: pgco-faq
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'PGCO_FAQ_VERSION', '1.0.3' );
define( 'PGCO_FAQ_FILE',    __FILE__ );
define( 'PGCO_FAQ_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PGCO_FAQ_URL',     plugin_dir_url( __FILE__ ) );

require_once PGCO_FAQ_PATH . 'includes/class-pgco-faq-cpt.php';
require_once PGCO_FAQ_PATH . 'includes/class-pgco-faq-metabox.php';
require_once PGCO_FAQ_PATH . 'includes/class-pgco-faq-shortcode.php';

function pgco_faq_boot() {
    PGCO_FAQ_CPT::init();
    PGCO_FAQ_Metabox::init();
    PGCO_FAQ_Shortcode::init();
}
add_action( 'plugins_loaded', 'pgco_faq_boot' );
