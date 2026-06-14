<?php
/**
 * Plugin Name:       Plugin Fichaje
 * Plugin URI:        https://github.com/
 * Description:       Sistema de registro de jornada laboral conforme al RDL 8/2019 (Art. 34.9 ET). Registra entradas y salidas con trazabilidad completa para cumplir la obligación legal de conservación durante 4 años.
 * Version:           2.0.0
 * Author:            Ramiro
 * License:           GPL v2 or later
 * Text Domain:       plugin-fichaje
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PF_VERSION',    '2.0.0' );
define( 'PF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PF_TABLE_REG',  'fichaje_registros' );
define( 'PF_TABLE_AUD',  'fichaje_auditoria' );

require_once PF_PLUGIN_DIR . 'includes/database.php';
require_once PF_PLUGIN_DIR . 'includes/ajax.php';
require_once PF_PLUGIN_DIR . 'includes/admin-page.php';
require_once PF_PLUGIN_DIR . 'includes/shortcode.php';
require_once PF_PLUGIN_DIR . 'includes/export.php';
require_once PF_PLUGIN_DIR . 'includes/rest-api.php';

register_activation_hook( __FILE__, 'pf_activate' );
function pf_activate() {
    pf_create_tables();
    if ( ! get_option( 'pf_activated_date' ) ) {
        update_option( 'pf_activated_date', current_time( 'Y-m-d' ) );
    }
}

register_deactivation_hook( __FILE__, 'pf_deactivate' );
function pf_deactivate() {
    // Data preserved on deactivation (legal 4-year retention)
}

add_action( 'wp_enqueue_scripts', 'pf_enqueue_frontend' );
function pf_enqueue_frontend() {
    if ( ! is_user_logged_in() ) return;
    wp_enqueue_style( 'pf-styles', PF_PLUGIN_URL . 'assets/css/fichaje.css', [], PF_VERSION );
    wp_enqueue_script( 'pf-script', PF_PLUGIN_URL . 'assets/js/fichaje.js', [ 'jquery' ], PF_VERSION, true );
    wp_localize_script( 'pf-script', 'pfAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'pf_nonce' ),
        'i18n'    => [
            'clock_in'   => __( 'Fichar Entrada', 'plugin-fichaje' ),
            'clock_out'  => __( 'Fichar Salida', 'plugin-fichaje' ),
            'confirming' => __( 'Procesando…', 'plugin-fichaje' ),
            'error'      => __( 'Error. Inténtalo de nuevo.', 'plugin-fichaje' ),
        ],
    ] );
}

add_action( 'admin_enqueue_scripts', 'pf_enqueue_admin' );
function pf_enqueue_admin( $hook ) {
    if ( strpos( $hook, 'fichaje' ) === false ) return;
    wp_enqueue_style( 'pf-admin-styles', PF_PLUGIN_URL . 'assets/css/fichaje.css', [], PF_VERSION );
    wp_enqueue_script( 'pf-admin-script', PF_PLUGIN_URL . 'assets/js/fichaje.js', [ 'jquery' ], PF_VERSION, true );
    wp_localize_script( 'pf-admin-script', 'pfAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'pf_nonce' ),
        'i18n'    => [
            'clock_in'       => __( 'Fichar Entrada', 'plugin-fichaje' ),
            'clock_out'      => __( 'Fichar Salida', 'plugin-fichaje' ),
            'confirming'     => __( 'Procesando…', 'plugin-fichaje' ),
            'error'          => __( 'Error. Inténtalo de nuevo.', 'plugin-fichaje' ),
            'confirm_delete' => __( '¿Seguro que quieres eliminar este registro?', 'plugin-fichaje' ),
        ],
    ] );
}
