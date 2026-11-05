<?php
/**
 * Plugin Name: Deindicizzazione Automatica Articoli
 * Plugin URI: https://www.i-creativi.com/plugin/deindicizzazione-articoli
 * Description: Plugin per deindicizzare e archiviare automaticamente articoli di categorie specifiche dopo X giorni, con supporto multi-categoria, email personalizzate, log e dashboard.
 * Version: 2.3.1
 * Author: I-Creativi
 * Author URI: https://www.i-creativi.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deindicizzazione-automatica-articoli
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Definizione costanti
define('ICREA_DEINDEX_VERSION', '2.3.1');
define('ICREA_DEINDEX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ICREA_DEINDEX_PLUGIN_URL', plugin_dir_url(__FILE__));

// Caricamento file
require_once ICREA_DEINDEX_PLUGIN_DIR . 'includes/functions.php';
require_once ICREA_DEINDEX_PLUGIN_DIR . 'admin/admin-page.php';

// Registrazione hook di attivazione e disattivazione
register_activation_hook(__FILE__, 'icrea_deindex_activation');
register_deactivation_hook(__FILE__, 'icrea_deindex_deactivation');

/**
 * Funzione eseguita all'attivazione del plugin
 */
function icrea_deindex_activation() {
    // Imposta le opzioni predefinite se non esistono
    if (!get_option('icrea_deindex_days')) {
        update_option('icrea_deindex_days', 7);
    }

    if (!get_option('icrea_deindex_cron_frequency')) {
        update_option('icrea_deindex_cron_frequency', 'hourly');
    }

    // Imposta la frequenza corrente
    update_option('icrea_deindex_cron_frequency_current', get_option('icrea_deindex_cron_frequency', 'hourly'));

    // Pianifica l'evento cron
    icrea_deindex_schedule_event();
}

/**
 * Funzione eseguita alla disattivazione del plugin
 */
function icrea_deindex_deactivation() {
    // Rimuovi l'evento cron
    wp_clear_scheduled_hook('icrea_deindex_cron_hook');
}

/**
 * Pianifica l'evento cron con la frequenza configurata
 */
function icrea_deindex_schedule_event() {
    // Rimuovi eventuali eventi già pianificati
    wp_clear_scheduled_hook('icrea_deindex_cron_hook');

    // Ottieni la frequenza configurata
    $frequency = get_option('icrea_deindex_cron_frequency', 'hourly');

    // Pianifica il nuovo evento
    if (!wp_next_scheduled('icrea_deindex_cron_hook')) {
        wp_schedule_event(time(), $frequency, 'icrea_deindex_cron_hook');
    }
}

// Collega la funzione principale all'hook cron
add_action('icrea_deindex_cron_hook', 'icrea_deindex_old_articles');

// Aggiorna la pianificazione quando viene cambiata la frequenza
add_action('update_option_icrea_deindex_cron_frequency', 'icrea_deindex_update_cron_schedule', 10, 2);

/**
 * Aggiorna la pianificazione cron quando viene cambiata la frequenza
 */
function icrea_deindex_update_cron_schedule($old_value, $new_value) {
    if ($old_value !== $new_value) {
        icrea_deindex_schedule_event();
        update_option('icrea_deindex_cron_frequency_current', $new_value);
    }
}
