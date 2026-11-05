<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * -------------------------------------------------------------------------
 * Opzioni
 * -------------------------------------------------------------------------
 */

/**
 * Meta usati dal plugin per marcare gli articoli già trattati.
 */
define( 'ICREA_DEINDEX_META_NOINDEXED', '_icrea_deindex_noindexed' );
define( 'ICREA_DEINDEX_META_ARCHIVED', '_icrea_deindex_archived' );
define( 'ICREA_DEINDEX_META_EXCLUDED', '_icrea_deindex_exclude' );
define( 'ICREA_DEINDEX_META_BACKUP', '_icrea_deindex_backup' );
define( 'ICREA_DEINDEX_META_TAGGED', '_icrea_deindex_tagged' );

/**
 * Capability richiesta per gestire il plugin (impostazioni, esecuzione, ripristino).
 *
 * @return string
 */
function icrea_deindex_capability() {
	return (string) apply_filters( 'icrea_deindex_capability', 'manage_options' );
}

/**
 * L'utente corrente può gestire la deindicizzazione di questo articolo?
 *
 * @param int $post_id ID articolo.
 * @return bool
 */
function icrea_deindex_user_can_manage_post( $post_id ) {
	return current_user_can( icrea_deindex_capability() ) && current_user_can( 'edit_post', $post_id );
}

/**
 * Tipi di contenuto elaborati (quelli configurati nelle impostazioni).
 *
 * @return string[]
 */
function icrea_deindex_post_types() {
	return array_keys( icrea_deindex_get_targets() );
}

/**
 * Tipi di contenuto pubblici che si possono configurare (allegati esclusi).
 *
 * @return WP_Post_Type[] slug => oggetto.
 */
function icrea_deindex_available_post_types() {
	$types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $types['attachment'] );
	return $types;
}

/**
 * Tassonomie che si possono usare come filtro per un tipo di contenuto.
 *
 * @param string $post_type Tipo di contenuto.
 * @return WP_Taxonomy[] slug => oggetto.
 */
function icrea_deindex_available_taxonomies( $post_type ) {
	$taxonomies = array();
	foreach ( get_object_taxonomies( $post_type, 'objects' ) as $slug => $taxonomy ) {
		if ( $taxonomy->show_ui && 'post_format' !== $slug ) {
			$taxonomies[ $slug ] = $taxonomy;
		}
	}
	return $taxonomies;
}

/**
 * Configurazione salvata: per ogni tipo di contenuto la tassonomia ('' = tutti i contenuti) e i termini.
 *
 * @return array tipo => array( 'taxonomy' => string, 'terms' => int[] ).
 */
function icrea_deindex_get_saved_targets() {
	$saved   = get_option( 'icrea_deindex_targets', array() );
	$targets = array();
	foreach ( is_array( $saved ) ? $saved : array() as $post_type => $target ) {
		if ( ! is_array( $target ) ) {
			continue;
		}
		$targets[ sanitize_key( $post_type ) ] = array(
			'taxonomy' => isset( $target['taxonomy'] ) ? sanitize_key( $target['taxonomy'] ) : '',
			'terms'    => isset( $target['terms'] ) ? array_values( array_filter( array_map( 'absint', (array) $target['terms'] ) ) ) : array(),
		);
	}
	return $targets;
}

/**
 * Obiettivi attivi: tipi esistenti con "tutti i contenuti" oppure con almeno un termine selezionato.
 *
 * Il filtro icrea_deindex_post_types resta compatibile con la 2.5: un tipo aggiunto dal filtro senza
 * configurazione propria usa le stesse categorie degli articoli.
 *
 * @return array tipo => array( 'taxonomy' => string, 'terms' => int[] ).
 */
function icrea_deindex_get_targets() {
	$targets = array();
	foreach ( icrea_deindex_get_saved_targets() as $post_type => $target ) {
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}
		if ( '' === $target['taxonomy'] ) {
			$targets[ $post_type ] = $target;
		} elseif ( $target['terms'] && taxonomy_exists( $target['taxonomy'] ) && is_object_in_taxonomy( $post_type, $target['taxonomy'] ) ) {
			$targets[ $post_type ] = $target;
		}
	}

	$types = (array) apply_filters( 'icrea_deindex_post_types', array_keys( $targets ) );
	foreach ( $types as $post_type ) {
		if ( ! isset( $targets[ $post_type ] ) && isset( $targets['post'] ) && 'category' === $targets['post']['taxonomy'] && post_type_exists( $post_type ) ) {
			$targets[ $post_type ] = $targets['post'];
		}
	}
	return array_intersect_key( $targets, array_flip( $types ) );
}

/**
 * Descrizione leggibile degli obiettivi (per email, log, widget): "Articoli: News, Eventi; Offerte: tutti".
 *
 * @param array|null $targets Obiettivi (null = quelli attivi).
 * @return string
 */
function icrea_deindex_targets_label( $targets = null ) {
	$targets = null === $targets ? icrea_deindex_get_targets() : $targets;
	$parts   = array();
	foreach ( $targets as $post_type => $target ) {
		$type  = get_post_type_object( $post_type );
		$label = $type ? $type->labels->name : $post_type;
		if ( '' === $target['taxonomy'] ) {
			$parts[] = $label . ': ' . __( 'tutti i contenuti', 'deindicizzazione-automatica-articoli' );
			continue;
		}
		$names = array();
		foreach ( $target['terms'] as $term_id ) {
			$term = get_term( $term_id, $target['taxonomy'] );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		$parts[] = $label . ': ' . implode( ', ', $names );
	}
	return implode( '; ', $parts );
}

/**
 * Meta robots dei plugin SEO che il plugin modifica (e salva nel backup per il ripristino).
 *
 * @return string[]
 */
function icrea_deindex_robots_meta_keys() {
	return array( 'rank_math_robots', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow' );
}

/**
 * Frequenze cron ammesse.
 *
 * @return array slug => etichetta.
 */
function icrea_deindex_frequencies() {
	return array(
		'hourly' => __( 'Ogni ora', 'deindicizzazione-automatica-articoli' ),
		'daily'  => __( 'Ogni giorno', 'deindicizzazione-automatica-articoli' ),
		'weekly' => __( 'Ogni settimana', 'deindicizzazione-automatica-articoli' ),
	);
}

/**
 * Stati di archiviazione ammessi.
 *
 * @return array slug => etichetta.
 */
function icrea_deindex_archive_statuses() {
	return array(
		'draft'   => __( 'Bozza', 'deindicizzazione-automatica-articoli' ),
		'private' => __( 'Privato', 'deindicizzazione-automatica-articoli' ),
	);
}

/**
 * Frequenza cron valida.
 *
 * @return string
 */
function icrea_deindex_get_frequency() {
	$frequency = get_option( 'icrea_deindex_cron_frequency', 'hourly' );
	return array_key_exists( $frequency, icrea_deindex_frequencies() ) ? $frequency : 'hourly';
}

/**
 * Stato di archiviazione valido.
 *
 * @return string
 */
function icrea_deindex_get_archive_status() {
	$status = get_option( 'icrea_deindex_archive_status', 'draft' );
	return array_key_exists( $status, icrea_deindex_archive_statuses() ) ? $status : 'draft';
}

/*
 * -------------------------------------------------------------------------
 * Migrazione dalle versioni precedenti
 * -------------------------------------------------------------------------
 */

/**
 * Migra dati e opzioni quando cambia la versione del plugin.
 *
 * - 2.2/2.3 salvavano le categorie per slug, 2.4/2.5 per ID: dalla 2.6 la configurazione è per tipo di contenuto
 *   (tassonomia + ID dei termini) nell'opzione icrea_deindex_targets.
 * - 2.3 scriveva il log in un file pubblico in uploads: ora sta in un'opzione non autocaricata.
 * - 2.2/2.3 salvavano rank_math_robots serializzato due volte: Rank Math lo ignorava e l'articolo restava
 *   indicizzato. Quei meta vengono rimossi, così la prossima esecuzione applica il noindex correttamente.
 */
function icrea_deindex_maybe_upgrade() {
	$installed = get_option( 'icrea_deindex_db_version', '' );
	if ( ICREA_DEINDEX_VERSION === $installed ) {
		return;
	}

	global $wpdb;

	// Obiettivi: dalle categorie (ID dalla 2.4, slug fino alla 2.3) alla configurazione per tipo di contenuto.
	if ( false === get_option( 'icrea_deindex_targets', false ) ) {
		$ids = get_option( 'icrea_deindex_category_ids', false );
		if ( false === $ids ) {
			$slugs = get_option( 'icrea_deindex_categories', array() );
			if ( empty( $slugs ) ) {
				$old   = get_option( 'icrea_deindex_category', '' );
				$slugs = $old ? array( $old ) : array();
			}
			$ids = array();
			foreach ( (array) $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, 'category' );
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				}
			}
		}
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		update_option(
			'icrea_deindex_targets',
			$ids ? array(
				'post' => array(
					'taxonomy' => 'category',
					'terms'    => $ids,
				),
			) : array()
		);
	}

	// Nuove opzioni.
	add_option( 'icrea_deindex_nofollow', 0 );

	// Log: dal file pubblico in uploads all'opzione.
	$upload_dir = wp_upload_dir( null, false );
	$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'deindicizzazione-automatica-articoli';
	$log_file   = $log_dir . '/log.txt';
	if ( is_readable( $log_file ) ) {
		$lines   = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$entries = array();
		foreach ( (array) $lines as $line ) {
			if ( preg_match( '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.*)$/', $line, $m ) ) {
				$entries[] = array(
					'time' => $m[1],
					'msg'  => sanitize_text_field( $m[2] ),
				);
			}
		}
		$entries = array_merge( $entries, (array) get_option( 'icrea_deindex_log', array() ) );
		update_option( 'icrea_deindex_log', array_slice( $entries, -icrea_deindex_log_limit() ), false );
		wp_delete_file( $log_file );
		@rmdir( $log_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- rimuove la cartella solo se vuota.
	}

	// Meta rank_math_robots rotti (stringa serializzata dentro un valore serializzato).
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s AND meta_value LIKE %s",
			'rank_math_robots',
			's:%',
			'%noindex%'
		)
	);

	// Opzioni non più usate.
	delete_option( 'icrea_deindex_category_ids' );
	delete_option( 'icrea_deindex_categories' );
	delete_option( 'icrea_deindex_category' );

	update_option( 'icrea_deindex_db_version', ICREA_DEINDEX_VERSION );
}

/*
 * -------------------------------------------------------------------------
 * Log (opzione non autocaricata, ultime N voci)
 * -------------------------------------------------------------------------
 */

/**
 * Numero massimo di voci di log conservate.
 *
 * @return int
 */
function icrea_deindex_log_limit() {
	return max( 10, (int) apply_filters( 'icrea_deindex_log_limit', 200 ) );
}

/**
 * Aggiunge una voce al log.
 *
 * @param string $message Messaggio.
 */
function icrea_deindex_log_add( $message ) {
	$entries   = (array) get_option( 'icrea_deindex_log', array() );
	$entries[] = array(
		'time' => current_time( 'mysql' ),
		'msg'  => $message,
	);
	update_option( 'icrea_deindex_log', array_slice( $entries, -icrea_deindex_log_limit() ), false );
}

/**
 * Voci di log, dalla più vecchia alla più recente.
 *
 * @return array
 */
function icrea_deindex_log_get() {
	$entries = get_option( 'icrea_deindex_log', array() );
	return is_array( $entries ) ? $entries : array();
}

/**
 * Log in formato testo.
 *
 * @return string
 */
function icrea_deindex_log_as_text() {
	$text = '';
	foreach ( icrea_deindex_log_get() as $entry ) {
		$text .= '[' . $entry['time'] . '] ' . $entry['msg'] . "\n";
	}
	return $text;
}

/*
 * -------------------------------------------------------------------------
 * Noindex: Rank Math, Yoast SEO e robots di WordPress
 * -------------------------------------------------------------------------
 */

/**
 * Imposta il noindex su un articolo e lo marca come trattato.
 *
 * Scrive sia il meta di Rank Math sia quelli di Yoast SEO (così il noindex resta anche se il sito cambia
 * plugin SEO). Senza plugin SEO interviene il filtro wp_robots di WordPress.
 *
 * @param int  $post_id  ID articolo.
 * @param bool $nofollow Aggiungere anche nofollow.
 */
function icrea_deindex_apply_noindex( $post_id, $nofollow ) {
	// Backup dei meta originali, per poter ripristinare l'articolo esattamente com'era.
	if ( ! metadata_exists( 'post', $post_id, ICREA_DEINDEX_META_BACKUP ) ) {
		$backup = array();
		foreach ( icrea_deindex_robots_meta_keys() as $key ) {
			$backup[ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		update_post_meta( $post_id, ICREA_DEINDEX_META_BACKUP, $backup );
	}

	// Rank Math: array di direttive.
	$robots = get_post_meta( $post_id, 'rank_math_robots', true );
	$robots = is_array( $robots ) ? $robots : array();
	$robots = array_values( array_diff( $robots, array( 'index', 'noindex', 'nofollow' ) ) );
	$robots[] = 'noindex';
	if ( $nofollow ) {
		$robots[] = 'nofollow';
	}
	update_post_meta( $post_id, 'rank_math_robots', $robots );

	// Yoast SEO.
	update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
	if ( $nofollow ) {
		update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );
	}
	icrea_deindex_refresh_yoast_indexable( $post_id );

	update_post_meta( $post_id, ICREA_DEINDEX_META_NOINDEXED, time() );
}

/**
 * Yoast legge i robots dalla sua tabella indexable, aggiornata al salvataggio del post:
 * dopo aver cambiato i meta va ricostruito l'indexable dell'articolo.
 *
 * @param int $post_id ID articolo.
 */
function icrea_deindex_refresh_yoast_indexable( $post_id ) {
	if ( ! function_exists( 'YoastSEO' ) || ! class_exists( '\Yoast\WP\SEO\Builders\Indexable_Builder' ) ) {
		return;
	}
	try {
		$repository = YoastSEO()->classes->get( '\Yoast\WP\SEO\Repositories\Indexable_Repository' );
		$builder    = YoastSEO()->classes->get( '\Yoast\WP\SEO\Builders\Indexable_Builder' );
		$indexable  = $repository->find_by_id_and_type( $post_id, 'post', false );
		$builder->build_for_id_and_type( $post_id, 'post', $indexable ? $indexable : false );
	} catch ( \Throwable $e ) {
		// Yoast cambia spesso le API interne: se fallisce, l'indexable si aggiorna al prossimo salvataggio.
		unset( $e );
	}
}

/**
 * Noindex tramite i robots di WordPress (usato quando nessun plugin SEO gestisce il meta robots).
 *
 * @param array $robots Direttive robots.
 * @return array
 */
function icrea_deindex_wp_robots( $robots ) {
	if ( is_singular() && get_post_meta( get_queried_object_id(), ICREA_DEINDEX_META_NOINDEXED, true ) ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		if ( get_option( 'icrea_deindex_nofollow', 0 ) ) {
			unset( $robots['follow'] );
			$robots['nofollow'] = true;
		}
	}
	return $robots;
}
add_filter( 'wp_robots', 'icrea_deindex_wp_robots', 20 );

/**
 * Svuota la cache delle sitemap di Rank Math e Yoast SEO.
 */
function icrea_deindex_flush_sitemaps() {
	try {
		if ( class_exists( '\RankMath\Sitemap\Cache' ) && method_exists( '\RankMath\Sitemap\Cache', 'invalidate_storage' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}
		if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) {
			WPSEO_Sitemaps_Cache::clear();
		}
	} catch ( \Throwable $e ) {
		unset( $e );
	}
	do_action( 'icrea_deindex_sitemaps_flushed' );
}

/*
 * -------------------------------------------------------------------------
 * Esclusione e ripristino del singolo articolo
 * -------------------------------------------------------------------------
 */

/**
 * Stato dell'articolo rispetto al plugin.
 *
 * @param int $post_id ID articolo.
 * @return array{noindexed:int,archived:int,excluded:bool}
 */
function icrea_deindex_get_post_state( $post_id ) {
	return array(
		'noindexed' => (int) get_post_meta( $post_id, ICREA_DEINDEX_META_NOINDEXED, true ),
		'archived'  => (int) get_post_meta( $post_id, ICREA_DEINDEX_META_ARCHIVED, true ),
		'excluded'  => (bool) get_post_meta( $post_id, ICREA_DEINDEX_META_EXCLUDED, true ),
	);
}

/**
 * Esclude (o reinclude) un articolo dall'elaborazione automatica.
 *
 * L'esclusione blocca solo le azioni future: un articolo già in noindex resta tale finché non viene ripristinato.
 *
 * @param int  $post_id  ID articolo.
 * @param bool $excluded Escluso.
 */
function icrea_deindex_set_excluded( $post_id, $excluded ) {
	if ( $excluded ) {
		update_post_meta( $post_id, ICREA_DEINDEX_META_EXCLUDED, 1 );
	} else {
		delete_post_meta( $post_id, ICREA_DEINDEX_META_EXCLUDED );
	}
}

/**
 * Annulla quanto fatto dal plugin su un articolo: rimette i meta robots originali, ripubblica l'articolo se
 * era stato archiviato, toglie il tag "scaduto" aggiunto dal plugin. L'articolo viene poi escluso, altrimenti
 * la prossima esecuzione lo rimetterebbe subito in noindex.
 *
 * @param int $post_id ID articolo.
 * @return true|WP_Error
 */
function icrea_deindex_restore_post( $post_id ) {
	$post  = get_post( $post_id );
	$state = $post ? icrea_deindex_get_post_state( $post_id ) : null;
	if ( ! $post || ( ! $state['noindexed'] && ! $state['archived'] ) ) {
		return new WP_Error( 'icrea_deindex_not_processed', __( 'Questo articolo non è stato elaborato dal plugin.', 'deindicizzazione-automatica-articoli' ) );
	}

	if ( $state['noindexed'] ) {
		$backup = get_post_meta( $post_id, ICREA_DEINDEX_META_BACKUP, true );
		if ( is_array( $backup ) ) {
			foreach ( icrea_deindex_robots_meta_keys() as $key ) {
				if ( ! array_key_exists( $key, $backup ) || null === $backup[ $key ] ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $backup[ $key ] );
				}
			}
		} else {
			// Articoli elaborati dalla 2.4.0, che non salvava il backup: si toglie solo ciò che aggiunge il plugin.
			$nofollow = (bool) get_option( 'icrea_deindex_nofollow', 0 );
			$robots   = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $robots ) ) {
				$robots = array_values( array_diff( $robots, $nofollow ? array( 'noindex', 'nofollow' ) : array( 'noindex' ) ) );
				if ( $robots ) {
					update_post_meta( $post_id, 'rank_math_robots', $robots );
				} else {
					delete_post_meta( $post_id, 'rank_math_robots' );
				}
			}
			delete_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex' );
			if ( $nofollow ) {
				delete_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow' );
			}
		}
		icrea_deindex_refresh_yoast_indexable( $post_id );
	}

	if ( $state['archived'] && array_key_exists( $post->post_status, icrea_deindex_archive_statuses() ) ) {
		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	if ( get_post_meta( $post_id, ICREA_DEINDEX_META_TAGGED, true ) ) {
		wp_remove_object_terms( $post_id, 'scaduto', 'post_tag' );
	}

	foreach ( array( ICREA_DEINDEX_META_NOINDEXED, ICREA_DEINDEX_META_ARCHIVED, ICREA_DEINDEX_META_BACKUP, ICREA_DEINDEX_META_TAGGED ) as $key ) {
		delete_post_meta( $post_id, $key );
	}
	icrea_deindex_set_excluded( $post_id, true );

	if ( get_option( 'icrea_deindex_regenerate_sitemap', 0 ) ) {
		icrea_deindex_flush_sitemaps();
	}

	/* translators: 1: titolo articolo, 2: ID */
	icrea_deindex_log_add( sprintf( __( 'Ripristinato ed escluso: "%1$s" (ID %2$d).', 'deindicizzazione-automatica-articoli' ), icrea_deindex_plain_title( $post_id ), $post_id ) );

	do_action( 'icrea_deindex_post_restored', $post_id );

	return true;
}

/*
 * -------------------------------------------------------------------------
 * Elaborazione
 * -------------------------------------------------------------------------
 */

/**
 * ID dei contenuti pubblicati, più vecchi di $days giorni, che rientrano negli obiettivi configurati.
 *
 * @param int    $days         Giorni.
 * @param string $exclude_meta Escludi i contenuti che hanno già questo meta ('' = nessuna esclusione).
 * @param int    $limit        Numero massimo di contenuti.
 * @return int[]
 */
function icrea_deindex_find_posts( $days, $exclude_meta, $limit ) {
	$result = icrea_deindex_query_posts( $days, $exclude_meta, $limit, false );
	return $result['ids'];
}

/**
 * Come icrea_deindex_find_posts(), con il totale (per l'anteprima).
 *
 * Si fa una query per tipo di contenuto, perché ognuno ha la sua tassonomia; il limite è condiviso.
 * I contenuti esclusi manualmente non vengono mai restituiti.
 *
 * @param int    $days         Giorni.
 * @param string $exclude_meta Meta marcatore da escludere ('' = nessuno).
 * @param int    $limit        Numero massimo di contenuti.
 * @param bool   $with_total   Calcolare il totale.
 * @return array{ids:int[],total:int}
 */
function icrea_deindex_query_posts( $days, $exclude_meta, $limit, $with_total ) {
	$meta_query = array(
		array(
			'key'     => ICREA_DEINDEX_META_EXCLUDED,
			'compare' => 'NOT EXISTS',
		),
	);
	if ( $exclude_meta ) {
		$meta_query[] = array(
			'key'     => $exclude_meta,
			'compare' => 'NOT EXISTS',
		);
	}

	$authors = array_filter( array_map( 'absint', (array) get_option( 'icrea_deindex_authors', array() ) ) );
	$ids     = array();
	$total   = 0;

	foreach ( icrea_deindex_get_targets() as $post_type => $target ) {
		$remaining = $limit - count( $ids );
		if ( $remaining <= 0 && ! $with_total ) {
			break;
		}

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => max( 1, $remaining ),
			'orderby'             => 'date',
			'order'               => 'ASC',
			'no_found_rows'       => ! $with_total,
			'ignore_sticky_posts' => true,
			'meta_query'          => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'date_query'          => array(
				array(
					'column' => 'post_date',
					'before' => absint( $days ) . ' days ago',
				),
			),
		);
		if ( '' !== $target['taxonomy'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => $target['taxonomy'],
					'field'            => 'term_id',
					'terms'            => $target['terms'],
					'include_children' => is_taxonomy_hierarchical( $target['taxonomy'] ),
				),
			);
		}
		if ( $authors ) {
			if ( 'include' === get_option( 'icrea_deindex_authors_mode', 'exclude' ) ) {
				$args['author__in'] = $authors;
			} else {
				$args['author__not_in'] = $authors;
			}
		}

		$query = new WP_Query( apply_filters( 'icrea_deindex_query_args', $args, $days, $exclude_meta, $post_type ) );
		$found = array_map( 'intval', $query->posts );
		if ( $remaining > 0 ) {
			$ids = array_merge( $ids, array_slice( $found, 0, $remaining ) );
		}
		$total += $with_total ? (int) $query->found_posts : count( $found );
	}

	return array(
		'ids'   => $ids,
		'total' => $total,
	);
}

/**
 * Anteprima della prossima esecuzione: contenuti che verrebbero messi in noindex e archiviati.
 *
 * @param int $limit Numero massimo di contenuti elencati per azione.
 * @return array|null null se non c'è nulla di configurato.
 */
function icrea_deindex_preview( $limit = 50 ) {
	if ( ! icrea_deindex_get_targets() ) {
		return null;
	}
	$days         = absint( get_option( 'icrea_deindex_days', 7 ) );
	$archive_days = absint( get_option( 'icrea_deindex_archive_days', 0 ) );
	$empty        = array(
		'ids'   => array(),
		'total' => 0,
	);

	return array(
		'deindex' => $days > 0 ? icrea_deindex_query_posts( $days, ICREA_DEINDEX_META_NOINDEXED, $limit, true ) : $empty,
		'archive' => $archive_days > 0 ? icrea_deindex_query_posts( $archive_days, '', $limit, true ) : $empty,
	);
}

/**
 * Articoli con un certo marcatore (elaborati o esclusi), dal più recente, paginati.
 *
 * @param string $which    'processed' (noindex o archiviati) oppure 'excluded'.
 * @param int    $page     Pagina (da 1).
 * @param int    $per_page Articoli per pagina.
 * @return array{ids:int[],total:int,pages:int}
 */
function icrea_deindex_get_marked_posts( $which, $page = 1, $per_page = 50 ) {
	if ( 'excluded' === $which ) {
		$meta_query = array(
			array(
				'key'     => ICREA_DEINDEX_META_EXCLUDED,
				'compare' => 'EXISTS',
			),
		);
	} else {
		$meta_query = array(
			'relation' => 'OR',
			array(
				'key'     => ICREA_DEINDEX_META_NOINDEXED,
				'compare' => 'EXISTS',
			),
			array(
				'key'     => ICREA_DEINDEX_META_ARCHIVED,
				'compare' => 'EXISTS',
			),
		);
	}

	$query = new WP_Query(
		array(
			'post_type'      => array_keys( icrea_deindex_available_post_types() ),
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'fields'         => 'ids',
			'posts_per_page' => $per_page,
			'paged'          => max( 1, (int) $page ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);

	return array(
		'ids'   => array_map( 'intval', $query->posts ),
		'total' => (int) $query->found_posts,
		'pages' => (int) $query->max_num_pages,
	);
}

/**
 * Titolo dell'articolo in testo semplice (per email e log).
 *
 * @param int $post_id ID articolo.
 * @return string
 */
function icrea_deindex_plain_title( $post_id ) {
	return wp_specialchars_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES );
}

/**
 * Esecuzione principale (agganciata al cron).
 *
 * Ogni articolo viene trattato una sola volta: quelli già messi in noindex sono marcati e non vengono
 * più elaborati, così email, contatore e sitemap riguardano solo le novità.
 *
 * @param array $options {
 *     Opzioni (il cron non ne passa: WordPress chiama la callback con una stringa vuota).
 *
 *     @type bool|null $dry_run Forza (o disattiva) la simulazione; null = impostazione salvata.
 *     @type string    $source  'cron', 'manual' o 'cli' (compare nel log).
 *     @type bool      $email   Inviare l'email di notifica.
 * }
 * @return array|null Riepilogo dell'esecuzione, null se non ci sono categorie o un'esecuzione è già in corso.
 */
function icrea_deindex_old_articles( $options = array() ) {
	$options = wp_parse_args(
		is_array( $options ) ? $options : array(),
		array(
			'dry_run' => null,
			'source'  => 'cron',
			'email'   => true,
		)
	);

	$targets = icrea_deindex_get_targets();
	if ( ! $targets ) {
		return null;
	}

	if ( get_transient( 'icrea_deindex_running' ) ) {
		return null;
	}
	set_transient( 'icrea_deindex_running', 1, 10 * MINUTE_IN_SECONDS );

	$days           = absint( get_option( 'icrea_deindex_days', 7 ) );
	$archive_days   = absint( get_option( 'icrea_deindex_archive_days', 0 ) );
	$archive_status = icrea_deindex_get_archive_status();
	$dry_run        = null === $options['dry_run'] ? (bool) get_option( 'icrea_deindex_dry_run', 0 ) : (bool) $options['dry_run'];
	$nofollow       = (bool) get_option( 'icrea_deindex_nofollow', 0 );
	$add_tag        = (bool) get_option( 'icrea_deindex_add_expired_tag', 0 );
	$limit          = max( 1, (int) apply_filters( 'icrea_deindex_batch_size', 200 ) );

	$summary = array(
		'dry_run'     => $dry_run,
		'source'      => sanitize_key( $options['source'] ),
		'deindexed'   => array(),
		'archived'    => array(),
		'tagged'      => array(),
		'more_queued' => false,
	);

	// Noindex.
	if ( $days > 0 ) {
		$ids = icrea_deindex_find_posts( $days, ICREA_DEINDEX_META_NOINDEXED, $limit );
		$summary['more_queued'] = count( $ids ) >= $limit;
		foreach ( $ids as $post_id ) {
			if ( ! $dry_run ) {
				icrea_deindex_apply_noindex( $post_id, $nofollow );
				if ( $add_tag && is_object_in_taxonomy( get_post_type( $post_id ), 'post_tag' ) && ! has_tag( 'scaduto', $post_id ) && ! is_wp_error( wp_set_post_tags( $post_id, 'scaduto', true ) ) ) {
					update_post_meta( $post_id, ICREA_DEINDEX_META_TAGGED, 1 );
					$summary['tagged'][] = $post_id;
				}
			}
			$summary['deindexed'][] = $post_id;
		}
	}

	// Archiviazione (gli articoli archiviati non sono più "publish", quindi non vengono ripresi).
	if ( $archive_days > 0 ) {
		$ids = icrea_deindex_find_posts( $archive_days, '', $limit );
		$summary['more_queued'] = $summary['more_queued'] || count( $ids ) >= $limit;
		foreach ( $ids as $post_id ) {
			if ( ! $dry_run ) {
				$result = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $archive_status,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					continue;
				}
				update_post_meta( $post_id, ICREA_DEINDEX_META_ARCHIVED, time() );
			}
			$summary['archived'][] = $post_id;
		}
	}

	$touched = count( array_unique( array_merge( $summary['deindexed'], $summary['archived'] ) ) );

	if ( $touched ) {
		if ( ! $dry_run ) {
			update_option( 'icrea_deindex_processed_count', (int) get_option( 'icrea_deindex_processed_count', 0 ) + $touched );
		}
		if ( $options['email'] ) {
			icrea_deindex_send_email( $summary, $targets, $days, $archive_status );
		}
		if ( ! $dry_run && get_option( 'icrea_deindex_regenerate_sitemap', 0 ) ) {
			icrea_deindex_flush_sitemaps();
		}
	}

	// Nel log solo le esecuzioni con novità: con il cron orario quelle vuote riempirebbero lo storico.
	if ( $touched ) {
		icrea_deindex_log_add( icrea_deindex_summary_message( $summary, $targets, $days, $archive_days ) );
	}
	update_option( 'icrea_deindex_last_run', time(), false );

	delete_transient( 'icrea_deindex_running' );

	do_action( 'icrea_deindex_after_run', $summary );

	return $summary;
}

/**
 * Riga di log per un'esecuzione.
 *
 * @param array $summary      Riepilogo.
 * @param array $targets      Obiettivi.
 * @param int   $days         Giorni noindex.
 * @param int   $archive_days Giorni archiviazione.
 * @return string
 */
function icrea_deindex_summary_message( $summary, $targets, $days, $archive_days ) {
	$parts = array();
	if ( isset( $summary['source'] ) && 'manual' === $summary['source'] ) {
		$parts[] = __( '[MANUALE]', 'deindicizzazione-automatica-articoli' );
	} elseif ( isset( $summary['source'] ) && 'cli' === $summary['source'] ) {
		$parts[] = '[WP-CLI]';
	}
	if ( $summary['dry_run'] ) {
		$parts[] = __( '[SIMULAZIONE]', 'deindicizzazione-automatica-articoli' );
	}
	/* translators: %s: contenuti monitorati */
	$parts[] = sprintf( __( 'Contenuti: %s.', 'deindicizzazione-automatica-articoli' ), icrea_deindex_targets_label( $targets ) );
	/* translators: 1: numero articoli, 2: giorni */
	$parts[] = sprintf( __( 'Deindicizzati: %1$d (dopo %2$d giorni).', 'deindicizzazione-automatica-articoli' ), count( $summary['deindexed'] ), $days );
	if ( $archive_days > 0 ) {
		/* translators: 1: numero articoli, 2: giorni */
		$parts[] = sprintf( __( 'Archiviati: %1$d (dopo %2$d giorni).', 'deindicizzazione-automatica-articoli' ), count( $summary['archived'] ), $archive_days );
	}
	if ( $summary['tagged'] ) {
		/* translators: %d: numero articoli */
		$parts[] = sprintf( __( 'Taggati "scaduto": %d.', 'deindicizzazione-automatica-articoli' ), count( $summary['tagged'] ) );
	}
	if ( $summary['more_queued'] ) {
		$parts[] = __( 'Altri articoli in coda per la prossima esecuzione.', 'deindicizzazione-automatica-articoli' );
	}
	return implode( ' ', $parts );
}

/**
 * Email di notifica (solo se ci sono articoli nuovi e destinatari validi).
 *
 * @param array  $summary        Riepilogo.
 * @param array  $targets        Obiettivi.
 * @param int    $days           Giorni noindex.
 * @param string $archive_status Stato di archiviazione.
 */
function icrea_deindex_send_email( $summary, $targets, $days, $archive_status ) {
	$recipients = array();
	foreach ( explode( ',', (string) get_option( 'icrea_deindex_emails', '' ) ) as $email ) {
		$email = sanitize_email( trim( $email ) );
		if ( $email && is_email( $email ) ) {
			$recipients[] = $email;
		}
	}
	if ( ! $recipients ) {
		return;
	}

	$subject  = get_option( 'icrea_deindex_email_subject', '' );
	$subject  = $subject ? $subject : __( 'Articoli deindicizzati automaticamente', 'deindicizzazione-automatica-articoli' );
	$template = get_option( 'icrea_deindex_email_template', '' );
	$template = $template ? $template : icrea_deindex_default_email_template();

	$titles   = array_map( 'icrea_deindex_plain_title', $summary['deindexed'] );
	$message  = str_replace(
		array( '{post_titles}', '{category}', '{days}' ),
		array( implode( "\n", $titles ), icrea_deindex_targets_label( $targets ), (string) $days ),
		wp_strip_all_tags( $template )
	);

	if ( $summary['archived'] ) {
		$statuses = icrea_deindex_archive_statuses();
		/* translators: %s: stato (bozza/privato) */
		$message .= "\n\n" . sprintf( __( 'Articoli archiviati (stato: %s):', 'deindicizzazione-automatica-articoli' ), $statuses[ $archive_status ] ) . "\n";
		$message .= implode( "\n", array_map( 'icrea_deindex_plain_title', $summary['archived'] ) );
	}

	if ( $summary['tagged'] ) {
		$message .= "\n\n" . __( 'Articoli taggati come "scaduto":', 'deindicizzazione-automatica-articoli' ) . "\n";
		$message .= implode( "\n", array_map( 'icrea_deindex_plain_title', $summary['tagged'] ) );
	}

	if ( $summary['more_queued'] ) {
		$message .= "\n\n" . __( 'Altri articoli verranno elaborati alla prossima esecuzione.', 'deindicizzazione-automatica-articoli' );
	}

	if ( $summary['dry_run'] ) {
		$message = __( '[SIMULAZIONE] Nessuna modifica è stata apportata.', 'deindicizzazione-automatica-articoli' ) . "\n\n" . $message;
	}

	wp_mail( $recipients, $subject, $message );
}

/**
 * Template email predefinito.
 *
 * @return string
 */
function icrea_deindex_default_email_template() {
	return __( "I seguenti articoli sono stati deindicizzati:\n\n{post_titles}", 'deindicizzazione-automatica-articoli' );
}

/*
 * -------------------------------------------------------------------------
 * Widget della dashboard (solo amministratori)
 * -------------------------------------------------------------------------
 */

/**
 * Registra il widget della dashboard.
 */
function icrea_deindex_add_dashboard_widget() {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'icrea_deindex_dashboard_widget',
		__( 'Deindicizzazione Articoli - Stato', 'deindicizzazione-automatica-articoli' ),
		'icrea_deindex_dashboard_widget_content'
	);
}
add_action( 'wp_dashboard_setup', 'icrea_deindex_add_dashboard_widget' );

/**
 * Contenuto del widget della dashboard.
 */
function icrea_deindex_dashboard_widget_content() {
	$targets      = icrea_deindex_get_targets();
	$days         = absint( get_option( 'icrea_deindex_days', 7 ) );
	$archive_days = absint( get_option( 'icrea_deindex_archive_days', 0 ) );
	$next_run     = wp_next_scheduled( 'icrea_deindex_cron_hook' );
	$frequencies  = icrea_deindex_frequencies();
	$statuses     = icrea_deindex_archive_statuses();

	echo '<div class="icrea-dashboard-widget">';

	echo '<h4>' . esc_html__( 'Statistiche', 'deindicizzazione-automatica-articoli' ) . '</h4>';
	echo '<p>' . esc_html__( 'Articoli processati in totale:', 'deindicizzazione-automatica-articoli' ) . ' <strong>' . esc_html( number_format_i18n( (int) get_option( 'icrea_deindex_processed_count', 0 ) ) ) . '</strong></p>';

	if ( get_option( 'icrea_deindex_dry_run', 0 ) ) {
		echo '<p><strong>' . esc_html__( 'Modalità simulazione attiva: nessun articolo viene modificato.', 'deindicizzazione-automatica-articoli' ) . '</strong></p>';
	}

	$entries = array_slice( icrea_deindex_log_get(), -5 );
	echo '<h4>' . esc_html__( 'Attività recenti', 'deindicizzazione-automatica-articoli' ) . '</h4>';
	if ( $entries ) {
		echo '<ul>';
		foreach ( array_reverse( $entries ) as $entry ) {
			echo '<li><code>' . esc_html( $entry['time'] ) . '</code> ' . esc_html( $entry['msg'] ) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p>' . esc_html__( 'Nessuna attività recente registrata.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	}

	echo '<h4>' . esc_html__( 'Prossime azioni pianificate', 'deindicizzazione-automatica-articoli' ) . '</h4>';
	if ( ! $targets ) {
		echo '<p>' . esc_html__( 'Nessun contenuto configurato: il plugin non sta elaborando nulla.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	} elseif ( $next_run ) {
		/* translators: 1: data e ora, 2: tempo mancante */
		echo '<p>' . esc_html( sprintf( __( 'Prossima esecuzione: %1$s (tra %2$s)', 'deindicizzazione-automatica-articoli' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ), human_time_diff( time(), max( time(), $next_run ) ) ) ) . '</p>';
		$names = icrea_deindex_targets_label( $targets );
		echo '<ul>';
		if ( $days > 0 ) {
			/* translators: 1: contenuti monitorati, 2: giorni */
			echo '<li>' . esc_html( sprintf( __( 'Noindex dei contenuti (%1$s) più vecchi di %2$d giorni', 'deindicizzazione-automatica-articoli' ), $names, $days ) ) . '</li>';
		}
		if ( $archive_days > 0 ) {
			/* translators: 1: stato, 2: giorni */
			echo '<li>' . esc_html( sprintf( __( 'Archiviazione (stato: %1$s) dei contenuti più vecchi di %2$d giorni', 'deindicizzazione-automatica-articoli' ), $statuses[ icrea_deindex_get_archive_status() ], $archive_days ) ) . '</li>';
		}
		echo '</ul>';
		/* translators: %s: frequenza */
		echo '<p>' . esc_html( sprintf( __( 'Frequenza di esecuzione: %s', 'deindicizzazione-automatica-articoli' ), $frequencies[ icrea_deindex_get_frequency() ] ) ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'Nessuna azione pianificata. Verifica le impostazioni del plugin.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	}

	echo '<p class="icrea-dashboard-link"><a href="' . esc_url( admin_url( 'admin.php?page=icrea-deindex-articoli' ) ) . '">' . esc_html__( 'Gestisci impostazioni', 'deindicizzazione-automatica-articoli' ) . '</a></p>';
	echo '</div>';
}
