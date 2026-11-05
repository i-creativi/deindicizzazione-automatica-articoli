<?php
/**
 * Disinstallazione: rimuove opzioni, transient e marcatori del plugin.
 *
 * I meta SEO scritti sugli articoli (rank_math_robots, _yoast_wpseo_meta-robots-*) NON vengono toccati:
 * fanno parte della configurazione SEO del sito e rimuoverli renderebbe di nuovo indicizzabili gli articoli.
 *
 * @package DeindicizzazioneAutomaticaArticoli
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$icrea_deindex_options = array(
	'icrea_deindex_category_ids',
	'icrea_deindex_categories',
	'icrea_deindex_category',
	'icrea_deindex_days',
	'icrea_deindex_archive_days',
	'icrea_deindex_archive_status',
	'icrea_deindex_emails',
	'icrea_deindex_email_subject',
	'icrea_deindex_email_template',
	'icrea_deindex_nofollow',
	'icrea_deindex_regenerate_sitemap',
	'icrea_deindex_dry_run',
	'icrea_deindex_authors',
	'icrea_deindex_authors_mode',
	'icrea_deindex_cron_frequency',
	'icrea_deindex_cron_frequency_current',
	'icrea_deindex_add_expired_tag',
	'icrea_deindex_processed_count',
	'icrea_deindex_log',
	'icrea_deindex_last_run',
	'icrea_deindex_db_version',
);

foreach ( $icrea_deindex_options as $icrea_deindex_option ) {
	delete_option( $icrea_deindex_option );
}

delete_transient( 'icrea_deindex_running' );
wp_clear_scheduled_hook( 'icrea_deindex_cron_hook' );
delete_post_meta_by_key( '_icrea_deindex_noindexed' );
delete_post_meta_by_key( '_icrea_deindex_archived' );

// Log della versione 2.3 (file in uploads), se ancora presente.
$icrea_deindex_upload = wp_upload_dir( null, false );
$icrea_deindex_logdir = trailingslashit( $icrea_deindex_upload['basedir'] ) . 'deindicizzazione-automatica-articoli';
if ( file_exists( $icrea_deindex_logdir . '/log.txt' ) ) {
	wp_delete_file( $icrea_deindex_logdir . '/log.txt' );
}
if ( is_dir( $icrea_deindex_logdir ) ) {
	@rmdir( $icrea_deindex_logdir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
