<?php
/**
 * Comandi WP-CLI: wp deindex <comando>.
 *
 * @package DeindicizzazioneAutomaticaArticoli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gestisce la deindicizzazione automatica degli articoli da riga di comando.
 */
class Icrea_Deindex_CLI {

	/**
	 * Mostra configurazione e stato del plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex status
	 */
	public function status() {
		$category_ids = icrea_deindex_get_category_ids();
		$next_run     = wp_next_scheduled( 'icrea_deindex_cron_hook' );
		$last_run     = (int) get_option( 'icrea_deindex_last_run', 0 );
		$excluded     = icrea_deindex_get_marked_posts( 'excluded', 1, 1 );
		$processed    = icrea_deindex_get_marked_posts( 'processed', 1, 1 );

		$rows = array(
			array( 'Setting' => 'version', 'Value' => ICREA_DEINDEX_VERSION ),
			array( 'Setting' => 'categories', 'Value' => $category_ids ? implode( ', ', icrea_deindex_get_category_names( $category_ids ) ) : '-' ),
			array( 'Setting' => 'noindex_days', 'Value' => absint( get_option( 'icrea_deindex_days', 7 ) ) ),
			array( 'Setting' => 'archive_days', 'Value' => absint( get_option( 'icrea_deindex_archive_days', 0 ) ) ),
			array( 'Setting' => 'archive_status', 'Value' => icrea_deindex_get_archive_status() ),
			array( 'Setting' => 'nofollow', 'Value' => get_option( 'icrea_deindex_nofollow', 0 ) ? 'yes' : 'no' ),
			array( 'Setting' => 'dry_run', 'Value' => get_option( 'icrea_deindex_dry_run', 0 ) ? 'yes' : 'no' ),
			array( 'Setting' => 'frequency', 'Value' => icrea_deindex_get_frequency() ),
			array( 'Setting' => 'next_run', 'Value' => $next_run ? wp_date( 'Y-m-d H:i:s', $next_run ) : '-' ),
			array( 'Setting' => 'last_run', 'Value' => $last_run ? wp_date( 'Y-m-d H:i:s', $last_run ) : '-' ),
			array( 'Setting' => 'processed_total', 'Value' => (int) get_option( 'icrea_deindex_processed_count', 0 ) ),
			array( 'Setting' => 'processed_now', 'Value' => $processed['total'] ),
			array( 'Setting' => 'excluded', 'Value' => $excluded['total'] ),
		);
		WP_CLI\Utils\format_items( 'table', $rows, array( 'Setting', 'Value' ) );
	}

	/**
	 * Elenca gli articoli che verrebbero elaborati alla prossima esecuzione.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Formato di output.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex preview
	 *     wp deindex preview --format=ids
	 *
	 * @param array $args       Argomenti.
	 * @param array $assoc_args Opzioni.
	 */
	public function preview( $args, $assoc_args ) {
		$preview = icrea_deindex_preview( 1000 );
		if ( null === $preview ) {
			WP_CLI::error( 'Nessuna categoria configurata.' );
		}

		$items = array();
		foreach ( array( 'deindex' => 'noindex', 'archive' => 'archive' ) as $key => $label ) {
			foreach ( $preview[ $key ]['ids'] as $post_id ) {
				$items[] = array(
					'ID'     => $post_id,
					'action' => $label,
					'date'   => get_the_date( 'Y-m-d', $post_id ),
					'title'  => icrea_deindex_plain_title( $post_id ),
				);
			}
		}
		self::output( $items, $assoc_args, array( 'ID', 'action', 'date', 'title' ) );
	}

	/**
	 * Esegue subito la deindicizzazione con le impostazioni salvate.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Simula senza modificare gli articoli, anche se la simulazione è disattivata nelle impostazioni.
	 *
	 * [--[no-]email]
	 * : Invia l'email di notifica. Predefinito: sì.
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex run
	 *     wp deindex run --dry-run --no-email
	 *
	 * @param array $args       Argomenti.
	 * @param array $assoc_args Opzioni.
	 */
	public function run( $args, $assoc_args ) {
		if ( ! icrea_deindex_get_category_ids() ) {
			WP_CLI::error( 'Nessuna categoria configurata.' );
		}
		if ( get_transient( 'icrea_deindex_running' ) ) {
			WP_CLI::error( 'Un\'esecuzione è già in corso.' );
		}

		$summary = icrea_deindex_old_articles(
			array(
				'dry_run' => WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ? true : null,
				'source'  => 'cli',
				'email'   => (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'email', true ),
			)
		);
		if ( ! is_array( $summary ) ) {
			WP_CLI::error( 'Esecuzione non avviata.' );
		}

		$message = sprintf( '%d articoli in noindex, %d archiviati.', count( $summary['deindexed'] ), count( $summary['archived'] ) );
		if ( $summary['dry_run'] ) {
			$message = '[SIMULAZIONE] ' . $message;
		}
		if ( $summary['more_queued'] ) {
			$message .= ' Altri articoli in coda per la prossima esecuzione.';
		}
		WP_CLI::success( $message );
	}

	/**
	 * Ripristina l'indicizzazione degli articoli e li esclude dalle esecuzioni future.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : ID degli articoli.
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex restore 123 456
	 *
	 * @param array $args Argomenti.
	 */
	public function restore( $args ) {
		$errors = 0;
		foreach ( array_map( 'absint', $args ) as $post_id ) {
			$result = icrea_deindex_restore_post( $post_id );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( '%d: %s', $post_id, $result->get_error_message() ) );
				++$errors;
			} else {
				WP_CLI::log( sprintf( '%d: ripristinato ed escluso.', $post_id ) );
			}
		}
		self::finish( count( $args ), $errors, 'ripristinati' );
	}

	/**
	 * Esclude gli articoli dalla deindicizzazione automatica.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : ID degli articoli.
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex exclude 123
	 *
	 * @param array $args Argomenti.
	 */
	public function exclude( $args ) {
		self::set_excluded( $args, true );
	}

	/**
	 * Toglie l'esclusione: gli articoli tornano a essere elaborati.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : ID degli articoli.
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex include 123
	 *
	 * @subcommand include
	 *
	 * @param array $args Argomenti.
	 */
	public function include_( $args ) {
		self::set_excluded( $args, false );
	}

	/**
	 * Elenca gli articoli elaborati dal plugin oppure quelli esclusi.
	 *
	 * ## OPTIONS
	 *
	 * [--excluded]
	 * : Elenca gli articoli esclusi invece di quelli elaborati.
	 *
	 * [--format=<format>]
	 * : Formato di output.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp deindex list
	 *     wp deindex list --excluded --format=ids
	 *
	 * @subcommand list
	 *
	 * @param array $args       Argomenti.
	 * @param array $assoc_args Opzioni.
	 */
	public function list_( $args, $assoc_args ) {
		$which = WP_CLI\Utils\get_flag_value( $assoc_args, 'excluded', false ) ? 'excluded' : 'processed';
		$items = array();
		$page  = 1;
		do {
			$result = icrea_deindex_get_marked_posts( $which, $page, 200 );
			foreach ( $result['ids'] as $post_id ) {
				$state   = icrea_deindex_get_post_state( $post_id );
				$items[] = array(
					'ID'        => $post_id,
					'status'    => get_post_status( $post_id ),
					'noindexed' => $state['noindexed'] ? wp_date( 'Y-m-d', $state['noindexed'] ) : '',
					'archived'  => $state['archived'] ? wp_date( 'Y-m-d', $state['archived'] ) : '',
					'excluded'  => $state['excluded'] ? 'yes' : 'no',
					'title'     => icrea_deindex_plain_title( $post_id ),
				);
			}
			++$page;
		} while ( $page <= $result['pages'] );

		self::output( $items, $assoc_args, array( 'ID', 'status', 'noindexed', 'archived', 'excluded', 'title' ) );
	}

	/**
	 * Imposta l'esclusione su più articoli.
	 *
	 * @param array $args     ID.
	 * @param bool  $excluded Escluso.
	 */
	private static function set_excluded( $args, $excluded ) {
		$errors = 0;
		foreach ( array_map( 'absint', $args ) as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, icrea_deindex_post_types(), true ) ) {
				WP_CLI::warning( sprintf( '%d: articolo non trovato.', $post_id ) );
				++$errors;
				continue;
			}
			icrea_deindex_set_excluded( $post_id, $excluded );
		}
		self::finish( count( $args ), $errors, $excluded ? 'esclusi' : 'reinclusi' );
	}

	/**
	 * Messaggio finale per i comandi su più articoli.
	 *
	 * @param int    $total  Articoli richiesti.
	 * @param int    $errors Errori.
	 * @param string $verb   Participio (es. "esclusi").
	 */
	private static function finish( $total, $errors, $verb ) {
		if ( $errors && $errors === $total ) {
			WP_CLI::error( 'Nessun articolo modificato.' );
		}
		WP_CLI::success( sprintf( '%d articoli %s.', $total - $errors, $verb ) );
	}

	/**
	 * Stampa una lista nel formato richiesto.
	 *
	 * @param array    $items      Righe.
	 * @param array    $assoc_args Opzioni.
	 * @param string[] $fields     Colonne.
	 */
	private static function output( $items, $assoc_args, $fields ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', array_unique( wp_list_pluck( $items, 'ID' ) ) ) );
		} elseif ( 'count' === $format ) {
			WP_CLI::line( (string) count( $items ) );
		} else {
			WP_CLI\Utils\format_items( $format, $items, $fields );
		}
	}
}

WP_CLI::add_command( 'deindex', 'Icrea_Deindex_CLI' );
