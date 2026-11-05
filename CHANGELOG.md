# Changelog

Tutte le modifiche rilevanti del plugin **Deindicizzazione Automatica Articoli**.

## 2.4.0 — 2026-11-05
Testata su WordPress 7.1.4 / PHP 8.3 con Rank Math 1.0.281, Yoast SEO e senza plugin SEO; Plugin Check senza errori.

### Correzioni
- Il noindex di Rank Math veniva salvato con `serialize()` dentro `update_post_meta`, quindi serializzato due volte: Rank Math leggeva una stringa e lasciava l'articolo `index, follow`. Ora si salva l'array; all'aggiornamento i meta rotti vengono rimossi e la prima esecuzione riapplica il noindex.
- Ogni esecuzione rielaborava tutti gli articoli vecchi (email ogni ora con l'elenco completo, contatore gonfiato, sitemap rigenerata ogni volta). Ora gli articoli trattati sono marcati con `_icrea_deindex_noindexed` / `_icrea_deindex_archived`.
- Possibile errore fatale con PHP 8 su `method_exists( rank_math()->xml_sitemap, ... )`; `wpseo_rebuild_sitemap()` non esiste più in Yoast. Ora si svuotano le cache con `RankMath\Sitemap\Cache::invalidate_storage()` e `WPSEO_Sitemaps_Cache::clear()`.
- Il log era `uploads/deindicizzazione-automatica-articoli/log.txt`, pubblico e senza limite. Ora è un'opzione non autocaricata (ultime 200 esecuzioni con novità); il file viene migrato e cancellato.
- Il widget della dashboard era visibile a tutti gli utenti loggati: ora solo con `manage_options`.
- Impostazioni non validate (frequenza, stato di archiviazione, modalità autori): una frequenza non valida faceva sparire il cron. Ora tutto è in whitelist e il cron si ripristina da solo se manca.

### Novità
- Noindex anche per Yoast SEO (meta + ricostruzione dell'indexable) e, senza plugin SEO, tramite il filtro core `wp_robots`.
- "noindex, follow" di default, opzione "Aggiungi anche nofollow".
- Categorie salvate per ID (migrazione automatica dagli slug), sottocategorie incluse.
- Query a blocchi (`fields => ids`, filtro tassonomia in SQL) invece di caricare tutti gli articoli; hook `icrea_deindex_batch_size`, `icrea_deindex_post_types`, `icrea_deindex_query_args`, `icrea_deindex_log_limit`, `icrea_deindex_after_run`.
- `uninstall.php`: rimuove opzioni, marcatori e cron (i meta SEO restano).
- Link "Impostazioni" nella lista plugin, stringhe traducibili, CSS in `admin/admin.css` caricato con enqueue, salvataggio e azioni sul log via `admin-post` con nonce e controllo permessi, download del log lato server.
- `readme.txt` in inglese (richiesto da Plugin Check), `Requires at least: 5.7`.

## 2.3.1 — 2026-11-05
- Prima pubblicazione su WordPress.org delle funzionalità 2.3 (le versioni .org precedenti erano ferme alla 2.2)
- Aggiornato "Tested up to" a WordPress 7.1

## 2.3
- Aggiunto supporto multi-categoria
- Aggiunta modalità archiviazione (cambio stato articoli)
- Aggiunta pagina log in admin
- Aggiunte email personalizzabili con placeholder
- Aggiunta opzione per rigenerazione sitemap
- Aggiunta modalità simulazione (dry-run)
- Aggiunto filtro per autore
- Aggiunto dashboard widget
- Aggiunta frequenza cron configurabile
- Aggiunta opzione per tag "scaduto"

## 2.2 — 2025-04-16
- Refactoring totale per approvazione WP.org
- Prima pubblicazione su WordPress.org
