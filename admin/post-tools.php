<?php
/**
 * Strumenti sul singolo articolo: riquadro nell'editor, azione nella lista articoli, ripristino ed esclusione.
 *
 * @package DeindicizzazioneAutomaticaArticoli
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * URL (con nonce) di un'azione sull'articolo.
 *
 * @param int    $post_id ID articolo.
 * @param string $action  'restore', 'exclude' o 'include'.
 * @return string
 */
function icrea_deindex_post_action_url( $post_id, $action ) {
	$url = add_query_arg(
		array(
			'action'  => 'icrea_deindex_post',
			'do'      => $action,
			'post_id' => (int) $post_id,
		),
		admin_url( 'admin-post.php' )
	);
	return wp_nonce_url( $url, 'icrea_deindex_post_' . $action . '_' . (int) $post_id );
}

/**
 * Esegue un'azione sull'articolo (admin-post) e torna alla pagina di partenza.
 */
function icrea_deindex_handle_post_action() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verificato con check_admin_referer().
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	$action  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	// phpcs:enable

	check_admin_referer( 'icrea_deindex_post_' . $action . '_' . $post_id );

	if ( ! $post_id || ! get_post( $post_id ) || ! icrea_deindex_user_can_manage_post( $post_id ) ) {
		wp_die( esc_html__( 'Non hai i permessi per questa operazione.', 'deindicizzazione-automatica-articoli' ), '', array( 'response' => 403 ) );
	}

	switch ( $action ) {
		case 'restore':
			$notice = is_wp_error( icrea_deindex_restore_post( $post_id ) ) ? 'error' : 'restored';
			break;
		case 'exclude':
			icrea_deindex_set_excluded( $post_id, true );
			$notice = 'excluded';
			break;
		case 'include':
			icrea_deindex_set_excluded( $post_id, false );
			$notice = 'included';
			break;
		default:
			$notice = 'error';
	}

	$redirect = wp_get_referer();
	$redirect = $redirect ? $redirect : admin_url( 'admin.php?page=icrea-deindex-articoli&tab=posts' );
	$redirect = remove_query_arg( array( 'icrea_deindex_notice', 'run', 'nd', 'na', 'dry', 'more', 'saved', 'cleared' ), $redirect );
	wp_safe_redirect( add_query_arg( 'icrea_deindex_notice', $notice, $redirect ) );
	exit;
}
add_action( 'admin_post_icrea_deindex_post', 'icrea_deindex_handle_post_action' );

/**
 * Messaggi delle azioni sull'articolo.
 *
 * @return array codice => array( tipo, messaggio ).
 */
function icrea_deindex_post_notices() {
	return array(
		'restored' => array( 'success', __( 'Indicizzazione ripristinata: l\'articolo è stato escluso dalle esecuzioni future.', 'deindicizzazione-automatica-articoli' ) ),
		'excluded' => array( 'success', __( 'Articolo escluso dalla deindicizzazione automatica.', 'deindicizzazione-automatica-articoli' ) ),
		'included' => array( 'success', __( 'Esclusione rimossa: l\'articolo verrà elaborato normalmente.', 'deindicizzazione-automatica-articoli' ) ),
		'error'    => array( 'error', __( 'Operazione non riuscita.', 'deindicizzazione-automatica-articoli' ) ),
	);
}

/**
 * Codice del messaggio da mostrare, se presente nell'URL.
 *
 * @return string
 */
function icrea_deindex_current_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo visualizzazione.
	$code = isset( $_GET['icrea_deindex_notice'] ) ? sanitize_key( wp_unslash( $_GET['icrea_deindex_notice'] ) ) : '';
	return array_key_exists( $code, icrea_deindex_post_notices() ) ? $code : '';
}

/**
 * Mostra il messaggio nelle pagine classiche dell'admin (lista articoli, pagina del plugin).
 */
function icrea_deindex_admin_notices() {
	$code   = icrea_deindex_current_notice();
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// Nell'editor il messaggio compare dentro il riquadro del plugin.
	if ( ! $code || ( $screen && 'post' === $screen->base ) ) {
		return;
	}
	$notices = icrea_deindex_post_notices();
	echo '<div class="notice notice-' . esc_attr( $notices[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $notices[ $code ][1] ) . '</p></div>';
}
add_action( 'admin_notices', 'icrea_deindex_admin_notices' );

/**
 * Rimuove il parametro del messaggio dall'URL dopo la visualizzazione.
 *
 * @param string[] $args Parametri rimovibili.
 * @return string[]
 */
function icrea_deindex_removable_query_args( $args ) {
	$args[] = 'icrea_deindex_notice';
	return $args;
}
add_filter( 'removable_query_args', 'icrea_deindex_removable_query_args' );

/**
 * Azione "Ripristina indicizzazione" nella lista articoli.
 *
 * @param string[] $actions Azioni.
 * @param WP_Post  $post    Articolo.
 * @return string[]
 */
function icrea_deindex_row_actions( $actions, $post ) {
	if ( ! icrea_deindex_user_can_manage_post( $post->ID ) ) {
		return $actions;
	}
	$state = icrea_deindex_get_post_state( $post->ID );
	if ( $state['noindexed'] || $state['archived'] ) {
		$actions['icrea_deindex_restore'] = '<a href="' . esc_url( icrea_deindex_post_action_url( $post->ID, 'restore' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Ripristinare l\'indicizzazione di questo articolo?', 'deindicizzazione-automatica-articoli' ) ) . '\');">' . esc_html__( 'Ripristina indicizzazione', 'deindicizzazione-automatica-articoli' ) . '</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', 'icrea_deindex_row_actions', 10, 2 );
add_filter( 'page_row_actions', 'icrea_deindex_row_actions', 10, 2 );

/**
 * Riquadro "Deindicizzazione automatica" nell'editor: sui tipi configurati e su ogni contenuto già elaborato
 * o escluso (anche se il suo tipo è stato poi tolto dalle impostazioni).
 *
 * @param string       $post_type Tipo di contenuto.
 * @param WP_Post|null $post      Contenuto.
 */
function icrea_deindex_add_meta_box( $post_type, $post = null ) {
	if ( ! current_user_can( icrea_deindex_capability() ) ) {
		return;
	}
	$show = in_array( $post_type, icrea_deindex_post_types(), true );
	if ( ! $show && $post instanceof WP_Post ) {
		$state = icrea_deindex_get_post_state( $post->ID );
		$show  = $state['noindexed'] || $state['archived'] || $state['excluded'];
	}
	if ( ! $show ) {
		return;
	}
	add_meta_box(
		'icrea_deindex_box',
		__( 'Deindicizzazione automatica', 'deindicizzazione-automatica-articoli' ),
		'icrea_deindex_render_meta_box',
		$post_type,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'icrea_deindex_add_meta_box', 10, 2 );

/**
 * Contenuto del riquadro.
 *
 * @param WP_Post $post Articolo.
 */
function icrea_deindex_render_meta_box( $post ) {
	$state       = icrea_deindex_get_post_state( $post->ID );
	$date_format = get_option( 'date_format' );
	$code        = icrea_deindex_current_notice();

	if ( $code ) {
		$notices = icrea_deindex_post_notices();
		echo '<div class="notice notice-' . esc_attr( $notices[ $code ][0] ) . ' inline"><p>' . esc_html( $notices[ $code ][1] ) . '</p></div>';
	}

	if ( $state['noindexed'] ) {
		/* translators: %s: data */
		echo '<p>' . esc_html( sprintf( __( 'Messo in noindex il %s.', 'deindicizzazione-automatica-articoli' ), wp_date( $date_format, $state['noindexed'] ) ) ) . '</p>';
	}
	if ( $state['archived'] ) {
		/* translators: %s: data */
		echo '<p>' . esc_html( sprintf( __( 'Archiviato il %s.', 'deindicizzazione-automatica-articoli' ), wp_date( $date_format, $state['archived'] ) ) ) . '</p>';
	}

	wp_nonce_field( 'icrea_deindex_mb_' . $post->ID, 'icrea_deindex_mb_nonce' );
	echo '<label><input type="checkbox" name="icrea_deindex_exclude" value="1" ' . checked( $state['excluded'], true, false ) . '> ' . esc_html__( 'Escludi dalla deindicizzazione automatica', 'deindicizzazione-automatica-articoli' ) . '</label>';

	if ( $state['noindexed'] || $state['archived'] ) {
		echo '<p class="description">' . esc_html__( 'L\'esclusione blocca solo le azioni future. Per rendere di nuovo indicizzabile l\'articolo usa "Ripristina".', 'deindicizzazione-automatica-articoli' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( icrea_deindex_post_action_url( $post->ID, 'restore' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Ripristinare l\'indicizzazione? Le modifiche non salvate all\'articolo andranno perse.', 'deindicizzazione-automatica-articoli' ) ) . '\');">' . esc_html__( 'Ripristina indicizzazione', 'deindicizzazione-automatica-articoli' ) . '</a></p>';
	}
}

/**
 * Salva l'esclusione impostata nel riquadro.
 *
 * @param int $post_id ID articolo.
 */
function icrea_deindex_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['icrea_deindex_mb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['icrea_deindex_mb_nonce'] ) ), 'icrea_deindex_mb_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! icrea_deindex_user_can_manage_post( $post_id ) ) {
		return;
	}
	icrea_deindex_set_excluded( $post_id, ! empty( $_POST['icrea_deindex_exclude'] ) );
}
add_action( 'save_post', 'icrea_deindex_save_meta_box' );
