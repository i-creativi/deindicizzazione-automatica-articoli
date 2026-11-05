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
		'manage_options',
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
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per modificare queste impostazioni.', 'deindicizzazione-automatica-articoli' ) );
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
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'deindicizzazione-automatica-articoli' ) );
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
 * Pagina delle impostazioni.
 */
function icrea_deindex_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lettura di parametri di navigazione.
	$current_tab = isset( $_GET['tab'] ) && 'logs' === $_GET['tab'] ? 'logs' : 'settings';
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

	echo '<nav class="nav-tab-wrapper">';
	echo '<a href="' . esc_url( $page_url ) . '" class="nav-tab' . ( 'settings' === $current_tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Impostazioni', 'deindicizzazione-automatica-articoli' ) . '</a>';
	echo '<a href="' . esc_url( add_query_arg( 'tab', 'logs', $page_url ) ) . '" class="nav-tab' . ( 'logs' === $current_tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Log', 'deindicizzazione-automatica-articoli' ) . '</a>';
	echo '</nav>';

	if ( 'logs' === $current_tab ) {
		icrea_deindex_render_logs_tab();
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
