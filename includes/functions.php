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

/**
 * ID delle categorie monitorate.
 *
 * @return int[]
 */
function icrea_deindex_get_category_ids() {
	$ids = get_option( 'icrea_deindex_category_ids', array() );
	return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
}

/**
 * Nomi delle categorie monitorate (per email, log e widget).
 *
 * @param int[] $category_ids ID categorie.
 * @return string[]
 */
function icrea_deindex_get_category_names( $category_ids ) {
	$names = array();
	foreach ( $category_ids as $id ) {
		$term = get_term( $id, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$names[] = $term->name;
		}
	}
	return $names;
}

/*
 * -------------------------------------------------------------------------
 * Migrazione dalle versioni precedenti
 * -------------------------------------------------------------------------
 */

/**
 * Migra dati e opzioni quando cambia la versione del plugin.
 *
 * - 2.2/2.3 salvavano le categorie per slug: ora si usano gli ID (resistono al cambio di slug).
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

	// Categorie: da slug a ID.
	if ( false === get_option( 'icrea_deindex_category_ids', false ) ) {
		$slugs = get_option( 'icrea_deindex_categories', array() );
		if ( empty( $slugs ) ) {
			$old = get_option( 'icrea_deindex_category', '' );
			$slugs = $old ? array( $old ) : array();
		}
		$ids = array();
		foreach ( (array) $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		update_option( 'icrea_deindex_category_ids', $ids );
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
 * Elaborazione
 * -------------------------------------------------------------------------
 */

/**
 * ID degli articoli pubblicati, più vecchi di $days giorni, nelle categorie monitorate.
 *
 * @param int    $days         Giorni.
 * @param int[]  $category_ids Categorie (incluse le sottocategorie).
 * @param string $exclude_meta Escludi gli articoli che hanno già questo meta ('' = nessuna esclusione).
 * @param int    $limit        Numero massimo di articoli.
 * @return int[]
 */
function icrea_deindex_find_posts( $days, $category_ids, $exclude_meta, $limit ) {
	$args = array(
		'post_type'           => apply_filters( 'icrea_deindex_post_types', array( 'post' ) ),
		'post_status'         => 'publish',
		'fields'              => 'ids',
		'posts_per_page'      => $limit,
		'orderby'             => 'date',
		'order'               => 'ASC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $category_ids,
				'include_children' => true,
			),
		),
		'date_query'          => array(
			array(
				'column' => 'post_date',
				'before' => absint( $days ) . ' days ago',
			),
		),
	);

	if ( $exclude_meta ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => $exclude_meta,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	$authors = array_filter( array_map( 'absint', (array) get_option( 'icrea_deindex_authors', array() ) ) );
	if ( $authors ) {
		if ( 'include' === get_option( 'icrea_deindex_authors_mode', 'exclude' ) ) {
			$args['author__in'] = $authors;
		} else {
			$args['author__not_in'] = $authors;
		}
	}

	$query = new WP_Query( apply_filters( 'icrea_deindex_query_args', $args, $days, $exclude_meta ) );
	return array_map( 'intval', $query->posts );
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
 * @return array|null Riepilogo dell'esecuzione, null se non c'è nulla da fare.
 */
function icrea_deindex_old_articles() {
	$category_ids = icrea_deindex_get_category_ids();
	if ( empty( $category_ids ) ) {
		return null;
	}

	if ( get_transient( 'icrea_deindex_running' ) ) {
		return null;
	}
	set_transient( 'icrea_deindex_running', 1, 10 * MINUTE_IN_SECONDS );

	$days           = absint( get_option( 'icrea_deindex_days', 7 ) );
	$archive_days   = absint( get_option( 'icrea_deindex_archive_days', 0 ) );
	$archive_status = icrea_deindex_get_archive_status();
	$dry_run        = (bool) get_option( 'icrea_deindex_dry_run', 0 );
	$nofollow       = (bool) get_option( 'icrea_deindex_nofollow', 0 );
	$add_tag        = (bool) get_option( 'icrea_deindex_add_expired_tag', 0 );
	$limit          = max( 1, (int) apply_filters( 'icrea_deindex_batch_size', 200 ) );

	$summary = array(
		'dry_run'     => $dry_run,
		'deindexed'   => array(),
		'archived'    => array(),
		'tagged'      => array(),
		'more_queued' => false,
	);

	// Noindex.
	if ( $days > 0 ) {
		$ids = icrea_deindex_find_posts( $days, $category_ids, ICREA_DEINDEX_META_NOINDEXED, $limit );
		$summary['more_queued'] = count( $ids ) >= $limit;
		foreach ( $ids as $post_id ) {
			if ( ! $dry_run ) {
				icrea_deindex_apply_noindex( $post_id, $nofollow );
				if ( $add_tag && ! is_wp_error( wp_set_post_tags( $post_id, 'scaduto', true ) ) ) {
					$summary['tagged'][] = $post_id;
				}
			}
			$summary['deindexed'][] = $post_id;
		}
	}

	// Archiviazione (gli articoli archiviati non sono più "publish", quindi non vengono ripresi).
	if ( $archive_days > 0 ) {
		$ids = icrea_deindex_find_posts( $archive_days, $category_ids, '', $limit );
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
		icrea_deindex_send_email( $summary, $category_ids, $days, $archive_status );
		if ( ! $dry_run && get_option( 'icrea_deindex_regenerate_sitemap', 0 ) ) {
			icrea_deindex_flush_sitemaps();
		}
	}

	// Nel log solo le esecuzioni con novità: con il cron orario quelle vuote riempirebbero lo storico.
	if ( $touched ) {
		icrea_deindex_log_add( icrea_deindex_summary_message( $summary, $category_ids, $days, $archive_days ) );
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
 * @param int[] $category_ids Categorie.
 * @param int   $days         Giorni noindex.
 * @param int   $archive_days Giorni archiviazione.
 * @return string
 */
function icrea_deindex_summary_message( $summary, $category_ids, $days, $archive_days ) {
	$parts = array();
	if ( $summary['dry_run'] ) {
		$parts[] = __( '[SIMULAZIONE]', 'deindicizzazione-automatica-articoli' );
	}
	/* translators: %s: elenco categorie */
	$parts[] = sprintf( __( 'Categorie: %s.', 'deindicizzazione-automatica-articoli' ), implode( ', ', icrea_deindex_get_category_names( $category_ids ) ) );
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
 * @param int[]  $category_ids   Categorie.
 * @param int    $days           Giorni noindex.
 * @param string $archive_status Stato di archiviazione.
 */
function icrea_deindex_send_email( $summary, $category_ids, $days, $archive_status ) {
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
		array( implode( "\n", $titles ), implode( ', ', icrea_deindex_get_category_names( $category_ids ) ), (string) $days ),
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
	if ( ! current_user_can( 'manage_options' ) ) {
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
	$category_ids = icrea_deindex_get_category_ids();
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
	if ( ! $category_ids ) {
		echo '<p>' . esc_html__( 'Nessuna categoria selezionata: il plugin non sta elaborando articoli.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	} elseif ( $next_run ) {
		/* translators: 1: data e ora, 2: tempo mancante */
		echo '<p>' . esc_html( sprintf( __( 'Prossima esecuzione: %1$s (tra %2$s)', 'deindicizzazione-automatica-articoli' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ), human_time_diff( time(), max( time(), $next_run ) ) ) ) . '</p>';
		$names = implode( ', ', icrea_deindex_get_category_names( $category_ids ) );
		echo '<ul>';
		if ( $days > 0 ) {
			/* translators: 1: categorie, 2: giorni */
			echo '<li>' . esc_html( sprintf( __( 'Noindex degli articoli in %1$s più vecchi di %2$d giorni', 'deindicizzazione-automatica-articoli' ), $names, $days ) ) . '</li>';
		}
		if ( $archive_days > 0 ) {
			/* translators: 1: stato, 2: giorni */
			echo '<li>' . esc_html( sprintf( __( 'Archiviazione (stato: %1$s) degli articoli più vecchi di %2$d giorni', 'deindicizzazione-automatica-articoli' ), $statuses[ icrea_deindex_get_archive_status() ], $archive_days ) ) . '</li>';
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
