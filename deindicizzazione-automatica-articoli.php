<?php
/**
 * Plugin Name: Deindicizzazione Automatica Articoli
 * Plugin URI: https://www.i-creativi.com/plugin/deindicizzazione-articoli
 * Description: Plugin per deindicizzare e archiviare automaticamente articoli di categorie specifiche dopo X giorni, con supporto multi-categoria, email personalizzate, log e dashboard.
 * Version: 2.5.0
 * Requires at least: 5.7
 * Requires PHP: 7.2
 * Author: I-Creativi
 * Author URI: https://www.i-creativi.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deindicizzazione-automatica-articoli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'ICREA_DEINDEX_VERSION', '2.5.0' );
define( 'ICREA_DEINDEX_PLUGIN_FILE', __FILE__ );
define( 'ICREA_DEINDEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICREA_DEINDEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ICREA_DEINDEX_PLUGIN_DIR . 'includes/functions.php';
require_once ICREA_DEINDEX_PLUGIN_DIR . 'admin/admin-page.php';

if ( is_admin() ) {
	require_once ICREA_DEINDEX_PLUGIN_DIR . 'admin/post-tools.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ICREA_DEINDEX_PLUGIN_DIR . 'includes/class-icrea-deindex-cli.php';
}

register_activation_hook( __FILE__, 'icrea_deindex_activation' );
register_deactivation_hook( __FILE__, 'icrea_deindex_deactivation' );

/**
 * Attivazione: opzioni predefinite, migrazione dalle versioni precedenti e pianificazione del cron.
 */
function icrea_deindex_activation() {
	add_option( 'icrea_deindex_days', 7 );
	add_option( 'icrea_deindex_cron_frequency', 'hourly' );
	add_option( 'icrea_deindex_nofollow', 0 );
	icrea_deindex_maybe_upgrade();
	icrea_deindex_schedule_event();
}

/**
 * Disattivazione: rimuove l'evento cron.
 */
function icrea_deindex_deactivation() {
	wp_clear_scheduled_hook( 'icrea_deindex_cron_hook' );
}

/**
 * Pianifica (o ripianifica) l'evento cron con la frequenza configurata.
 */
function icrea_deindex_schedule_event() {
	wp_clear_scheduled_hook( 'icrea_deindex_cron_hook' );
	$frequency = icrea_deindex_get_frequency();
	wp_schedule_event( time() + 60, $frequency, 'icrea_deindex_cron_hook' );
	update_option( 'icrea_deindex_cron_frequency_current', $frequency );
}

add_action( 'icrea_deindex_cron_hook', 'icrea_deindex_old_articles' );

/**
 * Esegue la migrazione quando cambia la versione (gli aggiornamenti non lanciano l'hook di attivazione)
 * e ripristina l'evento cron se è sparito.
 */
function icrea_deindex_bootstrap() {
	icrea_deindex_maybe_upgrade();

	if ( ! wp_next_scheduled( 'icrea_deindex_cron_hook' ) ) {
		icrea_deindex_schedule_event();
	}
}
add_action( 'init', 'icrea_deindex_bootstrap' );

/**
 * Link "Impostazioni" nella lista dei plugin.
 *
 * @param array $links Link esistenti.
 * @return array
 */
function icrea_deindex_action_links( $links ) {
	$url = admin_url( 'admin.php?page=icrea-deindex-articoli' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Impostazioni', 'deindicizzazione-automatica-articoli' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'icrea_deindex_action_links' );
