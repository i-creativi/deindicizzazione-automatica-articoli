<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Menu di amministrazione.
 */
function icrea_deindex_admin_menu() {
	add_menu_page(
		__( 'Deindex Articoli', 'deindicizzazione-automatica-articoli' ),
		__( 'Deindex Articoli', 'deindicizzazione-automatica-articoli' ),
		icrea_deindex_capability(),
		'icrea-deindex-articoli',
		'icrea_deindex_settings_page',
		'dashicons-hidden'
	);
}
add_action( 'admin_menu', 'icrea_deindex_admin_menu' );

/**
 * CSS dell'admin (pagina del plugin e dashboard).
 *
 * @param string $hook_suffix Pagina corrente.
 */
function icrea_deindex_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_icrea-deindex-articoli' === $hook_suffix || 'index.php' === $hook_suffix ) {
		wp_enqueue_style( 'icrea-deindex-admin', ICREA_DEINDEX_PLUGIN_URL . 'admin/admin.css', array(), ICREA_DEINDEX_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'icrea_deindex_admin_assets' );

/**
 * Salvataggio delle impostazioni (admin-post).
 */
function icrea_deindex_handle_save() {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		wp_die( esc_html__( 'Non hai i permessi per modificare queste impostazioni.', 'deindicizzazione-automatica-articoli' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'icrea_deindex_save', 'icrea_deindex_nonce' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verificato sopra.
	$category_ids = isset( $_POST['icrea_deindex_category_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['icrea_deindex_category_ids'] ) ) ) ) : array();
	update_option( 'icrea_deindex_category_ids', $category_ids );

	update_option( 'icrea_deindex_days', max( 1, intval( wp_unslash( $_POST['icrea_deindex_days'] ?? 7 ) ) ) );
	update_option( 'icrea_deindex_archive_days', max( 0, intval( wp_unslash( $_POST['icrea_deindex_archive_days'] ?? 0 ) ) ) );

	$archive_status = sanitize_key( wp_unslash( $_POST['icrea_deindex_archive_status'] ?? 'draft' ) );
	update_option( 'icrea_deindex_archive_status', array_key_exists( $archive_status, icrea_deindex_archive_statuses() ) ? $archive_status : 'draft' );

	$emails = array();
	foreach ( explode( ',', sanitize_text_field( wp_unslash( $_POST['icrea_deindex_emails'] ?? '' ) ) ) as $email ) {
		$email = sanitize_email( trim( $email ) );
		if ( $email && is_email( $email ) ) {
			$emails[] = $email;
		}
	}
	update_option( 'icrea_deindex_emails', implode( ', ', $emails ) );
	update_option( 'icrea_deindex_email_subject', sanitize_text_field( wp_unslash( $_POST['icrea_deindex_email_subject'] ?? '' ) ) );
	update_option( 'icrea_deindex_email_template', sanitize_textarea_field( wp_unslash( $_POST['icrea_deindex_email_template'] ?? '' ) ) );

	update_option( 'icrea_deindex_nofollow', isset( $_POST['icrea_deindex_nofollow'] ) ? 1 : 0 );
	update_option( 'icrea_deindex_regenerate_sitemap', isset( $_POST['icrea_deindex_regenerate_sitemap'] ) ? 1 : 0 );
	update_option( 'icrea_deindex_dry_run', isset( $_POST['icrea_deindex_dry_run'] ) ? 1 : 0 );
	update_option( 'icrea_deindex_add_expired_tag', isset( $_POST['icrea_deindex_add_expired_tag'] ) ? 1 : 0 );

	$authors = isset( $_POST['icrea_deindex_authors'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['icrea_deindex_authors'] ) ) ) ) : array();
	update_option( 'icrea_deindex_authors', $authors );
	$authors_mode = sanitize_key( wp_unslash( $_POST['icrea_deindex_authors_mode'] ?? 'exclude' ) );
	update_option( 'icrea_deindex_authors_mode', 'include' === $authors_mode ? 'include' : 'exclude' );

	$frequency = sanitize_key( wp_unslash( $_POST['icrea_deindex_cron_frequency'] ?? 'hourly' ) );
	$frequency = array_key_exists( $frequency, icrea_deindex_frequencies() ) ? $frequency : 'hourly';
	update_option( 'icrea_deindex_cron_frequency', $frequency );
	// phpcs:enable

	if ( get_option( 'icrea_deindex_cron_frequency_current' ) !== $frequency || ! wp_next_scheduled( 'icrea_deindex_cron_hook' ) ) {
		icrea_deindex_schedule_event();
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'icrea-deindex-articoli', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_icrea_deindex_save', 'icrea_deindex_handle_save' );

/**
 * Azioni sul log: svuota / scarica (admin-post).
 */
function icrea_deindex_handle_log_action() {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'deindicizzazione-automatica-articoli' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'icrea_log_action', 'icrea_log_nonce' );

	$action = isset( $_POST['icrea_deindex_log_action'] ) ? sanitize_key( wp_unslash( $_POST['icrea_deindex_log_action'] ) ) : '';

	if ( 'download' === $action ) {
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="deindicizzazione-log.txt"' );
		echo icrea_deindex_log_as_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file di testo scaricato, non HTML.
		exit;
	}

	if ( 'clear' === $action ) {
		update_option( 'icrea_deindex_log', array(), false );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'icrea-deindex-articoli', 'tab' => 'logs', 'cleared' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_icrea_deindex_log', 'icrea_deindex_handle_log_action' );

/**
 * "Esegui ora" (admin-post): lancia subito un'esecuzione con le impostazioni salvate.
 */
function icrea_deindex_handle_run() {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'deindicizzazione-automatica-articoli' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'icrea_deindex_run', 'icrea_deindex_run_nonce' );

	$args = array(
		'page' => 'icrea-deindex-articoli',
		'tab'  => 'posts',
	);

	if ( ! icrea_deindex_get_category_ids() ) {
		$args['run'] = 'nocat';
	} elseif ( get_transient( 'icrea_deindex_running' ) ) {
		$args['run'] = 'busy';
	} else {
		$summary = icrea_deindex_old_articles( array( 'source' => 'manual' ) );
		if ( is_array( $summary ) ) {
			$args['run']  = 'done';
			$args['nd']   = count( $summary['deindexed'] );
			$args['na']   = count( $summary['archived'] );
			$args['dry']  = (int) $summary['dry_run'];
			$args['more'] = (int) $summary['more_queued'];
		} else {
			$args['run'] = 'busy';
		}
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_icrea_deindex_run', 'icrea_deindex_handle_run' );

/**
 * Esito di "Esegui ora".
 */
function icrea_deindex_render_run_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo visualizzazione dell'esito.
	$run = isset( $_GET['run'] ) ? sanitize_key( wp_unslash( $_GET['run'] ) ) : '';
	if ( 'nocat' === $run ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Nessuna categoria selezionata: non c\'è nulla da elaborare.', 'deindicizzazione-automatica-articoli' ) . '</p></div>';
	} elseif ( 'busy' === $run ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Un\'esecuzione è già in corso. Riprova tra qualche minuto.', 'deindicizzazione-automatica-articoli' ) . '</p></div>';
	} elseif ( 'done' === $run ) {
		$deindexed = isset( $_GET['nd'] ) ? absint( $_GET['nd'] ) : 0;
		$archived  = isset( $_GET['na'] ) ? absint( $_GET['na'] ) : 0;
		$message   = sprintf(
			/* translators: 1: articoli deindicizzati, 2: articoli archiviati */
			__( 'Esecuzione completata: %1$d articoli messi in noindex, %2$d archiviati.', 'deindicizzazione-automatica-articoli' ),
			$deindexed,
			$archived
		);
		if ( ! empty( $_GET['dry'] ) ) {
			$message = __( '[SIMULAZIONE] Nessun articolo è stato modificato.', 'deindicizzazione-automatica-articoli' ) . ' ' . $message;
		}
		if ( ! empty( $_GET['more'] ) ) {
			$message .= ' ' . __( 'Altri articoli sono in coda: verranno elaborati alla prossima esecuzione.', 'deindicizzazione-automatica-articoli' );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
	// phpcs:enable
}

/**
 * Scheda Articoli: anteprima della prossima esecuzione, articoli elaborati ed esclusi.
 */
function icrea_deindex_render_posts_tab() {
	$preview  = icrea_deindex_preview( 50 );
	$next_run = wp_next_scheduled( 'icrea_deindex_cron_hook' );

	echo '<div class="icrea-posts-container">';

	// Esegui ora.
	echo '<div class="icrea-stats-box icrea-run-box">';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="icrea_deindex_run">';
	wp_nonce_field( 'icrea_deindex_run', 'icrea_deindex_run_nonce' );
	echo '<button type="submit" class="button button-primary"' . disabled( null === $preview, true, false ) . '>' . esc_html__( 'Esegui ora', 'deindicizzazione-automatica-articoli' ) . '</button> ';
	if ( $next_run ) {
		/* translators: %s: data e ora */
		echo '<span class="description">' . esc_html( sprintf( __( 'Prossima esecuzione automatica: %s', 'deindicizzazione-automatica-articoli' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ) ) . '</span>';
	}
	if ( get_option( 'icrea_deindex_dry_run', 0 ) ) {
		echo '<p><strong>' . esc_html__( 'Modalità simulazione attiva: l\'esecuzione non modificherà gli articoli.', 'deindicizzazione-automatica-articoli' ) . '</strong></p>';
	}
	echo '</form>';
	echo '</div>';

	// Anteprima.
	echo '<h2>' . esc_html__( 'Anteprima della prossima esecuzione', 'deindicizzazione-automatica-articoli' ) . '</h2>';
	if ( null === $preview ) {
		echo '<p>' . esc_html__( 'Nessuna categoria selezionata: seleziona almeno una categoria nelle impostazioni.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	} else {
		$batch = max( 1, (int) apply_filters( 'icrea_deindex_batch_size', 200 ) );

		/* translators: %d: numero articoli */
		echo '<h3>' . esc_html( sprintf( __( 'Da mettere in noindex (%d)', 'deindicizzazione-automatica-articoli' ), $preview['deindex']['total'] ) ) . '</h3>';
		icrea_deindex_render_posts_table( $preview['deindex']['ids'], 'preview', $preview['deindex']['total'] );

		if ( absint( get_option( 'icrea_deindex_archive_days', 0 ) ) > 0 ) {
			/* translators: %d: numero articoli */
			echo '<h3>' . esc_html( sprintf( __( 'Da archiviare (%d)', 'deindicizzazione-automatica-articoli' ), $preview['archive']['total'] ) ) . '</h3>';
			icrea_deindex_render_posts_table( $preview['archive']['ids'], 'preview', $preview['archive']['total'] );
		}

		if ( max( $preview['deindex']['total'], $preview['archive']['total'] ) > $batch ) {
			/* translators: %d: articoli per esecuzione */
			echo '<p class="description">' . esc_html( sprintf( __( 'Ogni esecuzione elabora al massimo %d articoli per azione: i restanti vengono elaborati nelle esecuzioni successive.', 'deindicizzazione-automatica-articoli' ), $batch ) ) . '</p>';
		}
	}

	// Elaborati.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- paginazione.
	$page      = isset( $_GET['pdone'] ) ? max( 1, absint( $_GET['pdone'] ) ) : 1;
	$processed = icrea_deindex_get_marked_posts( 'processed', $page, 50 );
	/* translators: %d: numero articoli */
	echo '<h2>' . esc_html( sprintf( __( 'Articoli elaborati (%d)', 'deindicizzazione-automatica-articoli' ), $processed['total'] ) ) . '</h2>';
	echo '<p class="description">' . esc_html__( '"Ripristina" rimette i meta robots originali, ripubblica l\'articolo se era stato archiviato e lo esclude dalle esecuzioni future.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	icrea_deindex_render_posts_table( $processed['ids'], 'processed', $processed['total'] );
	if ( $processed['pages'] > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'pdone', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => $processed['pages'],
				)
			)
		);
		echo '</div></div>';
	}

	// Esclusi.
	$excluded = icrea_deindex_get_marked_posts( 'excluded', 1, 100 );
	/* translators: %d: numero articoli */
	echo '<h2>' . esc_html( sprintf( __( 'Articoli esclusi (%d)', 'deindicizzazione-automatica-articoli' ), $excluded['total'] ) ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Gli articoli esclusi non vengono mai messi in noindex né archiviati. Puoi escludere un articolo anche dal riquadro "Deindicizzazione automatica" nella sua pagina di modifica.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	icrea_deindex_render_posts_table( $excluded['ids'], 'excluded', $excluded['total'] );

	echo '</div>';
}

/**
 * Tabella di articoli con le azioni disponibili.
 *
 * @param int[]  $ids   ID articoli.
 * @param string $mode  'preview', 'processed' o 'excluded'.
 * @param int    $total Totale (per indicare quanti non sono elencati).
 */
function icrea_deindex_render_posts_table( $ids, $mode, $total ) {
	if ( ! $ids ) {
		echo '<p>' . esc_html__( 'Nessun articolo.', 'deindicizzazione-automatica-articoli' ) . '</p>';
		return;
	}

	$date_format = get_option( 'date_format' );
	$statuses    = get_post_statuses();

	echo '<table class="widefat striped icrea-posts-table"><thead><tr>';
	echo '<th>' . esc_html__( 'Titolo', 'deindicizzazione-automatica-articoli' ) . '</th>';
	echo '<th>' . esc_html__( 'Pubblicato', 'deindicizzazione-automatica-articoli' ) . '</th>';
	if ( 'processed' === $mode ) {
		echo '<th>' . esc_html__( 'Stato', 'deindicizzazione-automatica-articoli' ) . '</th>';
		echo '<th>' . esc_html__( 'Noindex', 'deindicizzazione-automatica-articoli' ) . '</th>';
		echo '<th>' . esc_html__( 'Archiviato', 'deindicizzazione-automatica-articoli' ) . '</th>';
	} else {
		echo '<th>' . esc_html__( 'Categorie', 'deindicizzazione-automatica-articoli' ) . '</th>';
	}
	echo '<th>' . esc_html__( 'Azioni', 'deindicizzazione-automatica-articoli' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $ids as $post_id ) {
		$state = icrea_deindex_get_post_state( $post_id );
		$link  = get_edit_post_link( $post_id );
		$title = icrea_deindex_plain_title( $post_id );
		$title = '' !== $title ? $title : __( '(senza titolo)', 'deindicizzazione-automatica-articoli' );

		echo '<tr>';
		echo '<td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td>';
		echo '<td>' . esc_html( get_the_date( $date_format, $post_id ) ) . '</td>';

		if ( 'processed' === $mode ) {
			$status = get_post_status( $post_id );
			echo '<td>' . esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status ) . '</td>';
			echo '<td>' . ( $state['noindexed'] ? esc_html( wp_date( $date_format, $state['noindexed'] ) ) : '&mdash;' ) . '</td>';
			echo '<td>' . ( $state['archived'] ? esc_html( wp_date( $date_format, $state['archived'] ) ) : '&mdash;' ) . '</td>';
		} else {
			echo '<td>' . esc_html( implode( ', ', wp_list_pluck( get_the_category( $post_id ), 'name' ) ) ) . '</td>';
		}

		echo '<td>';
		if ( icrea_deindex_user_can_manage_post( $post_id ) ) {
			if ( 'processed' === $mode ) {
				echo '<a class="button button-small" href="' . esc_url( icrea_deindex_post_action_url( $post_id, 'restore' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Ripristinare l\'indicizzazione di questo articolo?', 'deindicizzazione-automatica-articoli' ) ) . '\');">' . esc_html__( 'Ripristina', 'deindicizzazione-automatica-articoli' ) . '</a>';
			} elseif ( 'excluded' === $mode ) {
				echo '<a class="button button-small" href="' . esc_url( icrea_deindex_post_action_url( $post_id, 'include' ) ) . '">' . esc_html__( 'Togli esclusione', 'deindicizzazione-automatica-articoli' ) . '</a>';
			} else {
				echo '<a class="button button-small" href="' . esc_url( icrea_deindex_post_action_url( $post_id, 'exclude' ) ) . '">' . esc_html__( 'Escludi', 'deindicizzazione-automatica-articoli' ) . '</a>';
			}
		}
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';

	if ( 'processed' !== $mode && $total > count( $ids ) ) {
		/* translators: %d: numero articoli non elencati */
		echo '<p class="description">' . esc_html( sprintf( __( '...e altri %d articoli.', 'deindicizzazione-automatica-articoli' ), $total - count( $ids ) ) ) . '</p>';
	}
}

/**
 * Pagina delle impostazioni.
 */
function icrea_deindex_settings_page() {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		return;
	}

	$tabs = array(
		'settings' => __( 'Impostazioni', 'deindicizzazione-automatica-articoli' ),
		'posts'    => __( 'Articoli', 'deindicizzazione-automatica-articoli' ),
		'logs'     => __( 'Log', 'deindicizzazione-automatica-articoli' ),
	);

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lettura di parametri di navigazione.
	$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
	$current_tab = array_key_exists( $current_tab, $tabs ) ? $current_tab : 'settings';
	$saved       = isset( $_GET['saved'] );
	$cleared     = isset( $_GET['cleared'] );
	// phpcs:enable

	$page_url = admin_url( 'admin.php?page=icrea-deindex-articoli' );

	echo '<div class="wrap"><h1>' . esc_html__( 'Deindicizzazione Articoli', 'deindicizzazione-automatica-articoli' ) . '</h1>';

	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Impostazioni salvate.', 'deindicizzazione-automatica-articoli' ) . '</p></div>';
	}
	if ( $cleared ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log svuotato con successo.', 'deindicizzazione-automatica-articoli' ) . '</p></div>';
	}
	icrea_deindex_render_run_notice();

	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $slug => $label ) {
		$url = 'settings' === $slug ? $page_url : add_query_arg( 'tab', $slug, $page_url );
		echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $current_tab === $slug ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';

	if ( 'logs' === $current_tab ) {
		icrea_deindex_render_logs_tab();
	} elseif ( 'posts' === $current_tab ) {
		icrea_deindex_render_posts_tab();
	} else {
		icrea_deindex_render_settings_tab();
	}

	echo '</div>';
}

/**
 * Scheda Log.
 */
function icrea_deindex_render_logs_tab() {
	$log_text = icrea_deindex_log_as_text();

	echo '<div class="icrea-log-container">';
	echo '<div class="icrea-stats-box">';
	echo '<h3>' . esc_html__( 'Statistiche', 'deindicizzazione-automatica-articoli' ) . '</h3>';
	echo '<p>' . esc_html__( 'Articoli processati totali:', 'deindicizzazione-automatica-articoli' ) . ' <strong>' . esc_html( number_format_i18n( (int) get_option( 'icrea_deindex_processed_count', 0 ) ) ) . '</strong></p>';
	$last_run = (int) get_option( 'icrea_deindex_last_run', 0 );
	if ( $last_run ) {
		echo '<p>' . esc_html__( 'Ultima esecuzione:', 'deindicizzazione-automatica-articoli' ) . ' <strong>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run ) ) . '</strong></p>';
	}
	echo '</div>';

	echo '<h3>' . esc_html__( 'Log delle operazioni', 'deindicizzazione-automatica-articoli' ) . '</h3>';
	/* translators: %d: numero di voci */
	echo '<p class="description">' . esc_html( sprintf( __( 'Vengono conservate le ultime %d esecuzioni.', 'deindicizzazione-automatica-articoli' ), icrea_deindex_log_limit() ) ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="icrea-log-actions">';
	echo '<input type="hidden" name="action" value="icrea_deindex_log">';
	wp_nonce_field( 'icrea_log_action', 'icrea_log_nonce' );
	echo '<button type="submit" name="icrea_deindex_log_action" value="download" class="button">' . esc_html__( 'Scarica log', 'deindicizzazione-automatica-articoli' ) . '</button> ';
	echo '<button type="submit" name="icrea_deindex_log_action" value="clear" class="button" onclick="return confirm(\'' . esc_js( __( 'Svuotare il log?', 'deindicizzazione-automatica-articoli' ) ) . '\');">' . esc_html__( 'Svuota log', 'deindicizzazione-automatica-articoli' ) . '</button>';
	echo '</form>';

	echo '<div class="icrea-log-content">';
	if ( '' !== $log_text ) {
		echo '<pre>' . esc_html( $log_text ) . '</pre>';
	} else {
		echo '<p>' . esc_html__( 'Nessun log disponibile.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	}
	echo '</div>';
	echo '</div>';
}

/**
 * Scheda Impostazioni.
 */
function icrea_deindex_render_settings_tab() {
	$category_ids     = icrea_deindex_get_category_ids();
	$days             = absint( get_option( 'icrea_deindex_days', 7 ) );
	$archive_days     = absint( get_option( 'icrea_deindex_archive_days', 0 ) );
	$archive_status   = icrea_deindex_get_archive_status();
	$emails           = get_option( 'icrea_deindex_emails', '' );
	$email_subject    = get_option( 'icrea_deindex_email_subject', '' );
	$email_subject    = $email_subject ? $email_subject : __( 'Articoli deindicizzati automaticamente', 'deindicizzazione-automatica-articoli' );
	$email_template   = get_option( 'icrea_deindex_email_template', '' );
	$email_template   = $email_template ? $email_template : icrea_deindex_default_email_template();
	$nofollow         = (int) get_option( 'icrea_deindex_nofollow', 0 );
	$regenerate       = (int) get_option( 'icrea_deindex_regenerate_sitemap', 0 );
	$dry_run          = (int) get_option( 'icrea_deindex_dry_run', 0 );
	$authors          = array_map( 'absint', (array) get_option( 'icrea_deindex_authors', array() ) );
	$authors_mode     = get_option( 'icrea_deindex_authors_mode', 'exclude' );
	$cron_frequency   = icrea_deindex_get_frequency();
	$add_expired_tag  = (int) get_option( 'icrea_deindex_add_expired_tag', 0 );

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="icrea_deindex_save">';
	wp_nonce_field( 'icrea_deindex_save', 'icrea_deindex_nonce' );

	echo '<div class="icrea-settings-container">';

	// Sezione 1: impostazioni di base.
	echo '<div class="icrea-settings-section">';
	echo '<h3>' . esc_html__( 'Impostazioni di base', 'deindicizzazione-automatica-articoli' ) . '</h3>';

	echo '<div class="icrea-field-group"><fieldset>';
	echo '<legend><strong>' . esc_html__( 'Categorie da deindicizzare', 'deindicizzazione-automatica-articoli' ) . '</strong></legend>';
	$all_categories = get_categories( array( 'hide_empty' => false ) );
	if ( $all_categories ) {
		echo '<div class="icrea-checklist">';
		foreach ( $all_categories as $category ) {
			echo '<label><input type="checkbox" name="icrea_deindex_category_ids[]" value="' . esc_attr( $category->term_id ) . '" ' . checked( in_array( (int) $category->term_id, $category_ids, true ), true, false ) . '> ' . esc_html( $category->name ) . '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Sono incluse anche le sottocategorie.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'Nessuna categoria trovata.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	}
	echo '</fieldset></div>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_days"><strong>' . esc_html__( 'Giorni prima di noindex', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<input type="number" id="icrea_deindex_days" name="icrea_deindex_days" value="' . esc_attr( $days ) . '" min="1" class="small-text">';
	echo '<p class="description">' . esc_html__( 'Numero di giorni dalla pubblicazione dopo i quali gli articoli vengono impostati come noindex.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	echo '</div>';

	echo '<div class="icrea-field-group">';
	echo '<label><input type="checkbox" name="icrea_deindex_nofollow" value="1" ' . checked( $nofollow, 1, false ) . '> ' . esc_html__( 'Aggiungi anche nofollow', 'deindicizzazione-automatica-articoli' ) . '</label>';
	echo '<p class="description">' . esc_html__( 'Sconsigliato: con "noindex, follow" i motori di ricerca continuano a seguire i link interni degli articoli vecchi.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	echo '</div>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_archive_days"><strong>' . esc_html__( 'Giorni prima di archiviazione', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<input type="number" id="icrea_deindex_archive_days" name="icrea_deindex_archive_days" value="' . esc_attr( $archive_days ) . '" min="0" class="small-text">';
	echo '<p class="description">' . esc_html__( 'Numero di giorni dopo i quali cambiare lo stato degli articoli (0 = disabilitato).', 'deindicizzazione-automatica-articoli' ) . '</p>';
	echo '</div>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_archive_status"><strong>' . esc_html__( 'Stato di archiviazione', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<select id="icrea_deindex_archive_status" name="icrea_deindex_archive_status">';
	foreach ( icrea_deindex_archive_statuses() as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '" ' . selected( $archive_status, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	echo '</div>';

	echo '<div class="icrea-field-group"><fieldset>';
	echo '<legend><strong>' . esc_html__( 'Frequenza di esecuzione', 'deindicizzazione-automatica-articoli' ) . '</strong></legend>';
	foreach ( icrea_deindex_frequencies() as $value => $label ) {
		echo '<label class="icrea-inline"><input type="radio" name="icrea_deindex_cron_frequency" value="' . esc_attr( $value ) . '" ' . checked( $cron_frequency, $value, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset></div>';

	echo '</div>';

	// Sezione 2: notifiche email.
	echo '<div class="icrea-settings-section">';
	echo '<h3>' . esc_html__( 'Notifiche Email', 'deindicizzazione-automatica-articoli' ) . '</h3>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_emails"><strong>' . esc_html__( 'Email di notifica', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<input type="text" id="icrea_deindex_emails" name="icrea_deindex_emails" value="' . esc_attr( $emails ) . '" class="regular-text">';
	echo '<p class="description">' . esc_html__( 'Indirizzi email separati da virgola. L\'email parte solo quando ci sono articoli nuovi da segnalare.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	echo '</div>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_email_subject"><strong>' . esc_html__( 'Oggetto email', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<input type="text" id="icrea_deindex_email_subject" name="icrea_deindex_email_subject" value="' . esc_attr( $email_subject ) . '" class="regular-text">';
	echo '</div>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_email_template"><strong>' . esc_html__( 'Template email', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<textarea id="icrea_deindex_email_template" name="icrea_deindex_email_template" rows="5" class="large-text code">' . esc_textarea( $email_template ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Placeholder disponibili:', 'deindicizzazione-automatica-articoli' ) . ' <code>{post_titles}</code>, <code>{category}</code>, <code>{days}</code></p>';
	echo '</div>';

	echo '</div>';

	// Sezione 3: opzioni avanzate.
	echo '<div class="icrea-settings-section">';
	echo '<h3>' . esc_html__( 'Opzioni avanzate', 'deindicizzazione-automatica-articoli' ) . '</h3>';

	echo '<div class="icrea-field-group">';
	echo '<label for="icrea_deindex_authors_mode"><strong>' . esc_html__( 'Filtro autori', 'deindicizzazione-automatica-articoli' ) . '</strong></label>';
	echo '<select id="icrea_deindex_authors_mode" name="icrea_deindex_authors_mode">';
	echo '<option value="exclude" ' . selected( $authors_mode, 'exclude', false ) . '>' . esc_html__( 'Escludi questi autori', 'deindicizzazione-automatica-articoli' ) . '</option>';
	echo '<option value="include" ' . selected( $authors_mode, 'include', false ) . '>' . esc_html__( 'Includi solo questi autori', 'deindicizzazione-automatica-articoli' ) . '</option>';
	echo '</select>';
	echo '</div>';

	echo '<div class="icrea-field-group"><fieldset>';
	echo '<legend><strong>' . esc_html__( 'Seleziona autori', 'deindicizzazione-automatica-articoli' ) . '</strong></legend>';
	$all_authors = get_users(
		array(
			'role__in'   => array( 'administrator', 'editor', 'author', 'contributor' ),
			'fields'     => array( 'ID', 'display_name' ),
		)
	);
	if ( $all_authors ) {
		echo '<div class="icrea-checklist">';
		foreach ( $all_authors as $author ) {
			echo '<label><input type="checkbox" name="icrea_deindex_authors[]" value="' . esc_attr( $author->ID ) . '" ' . checked( in_array( (int) $author->ID, $authors, true ), true, false ) . '> ' . esc_html( $author->display_name ) . '</label>';
		}
		echo '</div>';
	} else {
		echo '<p>' . esc_html__( 'Nessun autore trovato.', 'deindicizzazione-automatica-articoli' ) . '</p>';
	}
	echo '</fieldset></div>';

	echo '<div class="icrea-field-group"><fieldset>';
	echo '<legend><strong>' . esc_html__( 'Opzioni aggiuntive', 'deindicizzazione-automatica-articoli' ) . '</strong></legend>';
	echo '<label class="icrea-block"><input type="checkbox" name="icrea_deindex_regenerate_sitemap" value="1" ' . checked( $regenerate, 1, false ) . '> ' . esc_html__( 'Aggiorna la sitemap (Rank Math / Yoast SEO) dopo ogni esecuzione con modifiche', 'deindicizzazione-automatica-articoli' ) . '</label>';
	echo '<label class="icrea-block"><input type="checkbox" name="icrea_deindex_dry_run" value="1" ' . checked( $dry_run, 1, false ) . '> ' . esc_html__( 'Modalità simulazione (non modifica articoli)', 'deindicizzazione-automatica-articoli' ) . '</label>';
	echo '<label class="icrea-block"><input type="checkbox" name="icrea_deindex_add_expired_tag" value="1" ' . checked( $add_expired_tag, 1, false ) . '> ' . esc_html__( 'Aggiungi il tag "scaduto" agli articoli deindicizzati', 'deindicizzazione-automatica-articoli' ) . '</label>';
	echo '</fieldset></div>';

	echo '</div>';
	echo '</div>';

	submit_button( __( 'Salva impostazioni', 'deindicizzazione-automatica-articoli' ) );
	echo '</form>';
}
