=== Deindicizzazione Automatica Articoli ===
Contributors: icreativi
Tags: seo, deindex, rank math, yoast, articoli, archivio
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 2.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin per deindicizzare e archiviare automaticamente articoli datati da categorie specifiche. Supporta multi-categoria, email personalizzate, log e dashboard.

== Description ==
Deindicizzazione Automatica Articoli è un plugin completo per la gestione SEO degli articoli datati nel tuo sito WordPress.

= Funzionalità principali =
* Supporto multi-categoria: seleziona più categorie da deindicizzare
* Deindicizza automaticamente articoli dopo X giorni
* Modalità archiviazione: cambia lo stato degli articoli (bozza/privato) dopo Y giorni
* Notifiche email personalizzabili con template e placeholder
* Log completo delle attività con interfaccia admin
* Dashboard widget con statistiche e prossime azioni
* Filtro per autore: includi o escludi articoli di specifici autori
* Modalità simulazione (dry-run) per testare senza modificare articoli
* Frequenza cron configurabile (oraria, giornaliera, settimanale)
* Opzione per aggiungere tag "scaduto" agli articoli vecchi
* Rigenerazione automatica sitemap (Rank Math, Yoast SEO)

= Utilizzo =
Il plugin permette di gestire in modo automatico gli articoli datati del tuo sito, migliorando la SEO e mantenendo l'indice di Google pulito da contenuti obsoleti.

== Installation ==
1. Carica il plugin nella directory /wp-content/plugins/
2. Attiva il plugin dal menu 'Plugin' in WordPress
3. Configura le impostazioni in WP Admin > Deindex Articoli
4. Seleziona le categorie da monitorare e imposta i giorni per la deindicizzazione

== Frequently Asked Questions ==
= Posso selezionare più categorie? =
Sì, puoi selezionare tutte le categorie che desideri monitorare.

= Cosa significa "modalità archiviazione"? =
Oltre a impostare noindex/nofollow, puoi configurare il plugin per cambiare lo stato degli articoli (da pubblicato a bozza o privato) dopo un certo numero di giorni.

= Come funziona la modalità simulazione? =
Attivando questa opzione, il plugin eseguirà tutte le operazioni normalmente ma senza modificare effettivamente gli articoli. Riceverai comunque le email di notifica e i log, utile per testare la configurazione.

== Screenshots ==
1. Pannello impostazioni con supporto multi-categoria
2. Visualizzazione log e statistiche
3. Dashboard widget con prossime azioni

== Changelog ==
= 2.3.1 =
* Prima pubblicazione su WordPress.org delle funzionalità 2.3 (le versioni .org precedenti erano ferme alla 2.2)
* Aggiornato "Tested up to" a WordPress 7.1

= 2.3 =
* Aggiunto supporto multi-categoria
* Aggiunta modalità archiviazione (cambio stato articoli)
* Aggiunta pagina log in admin
* Aggiunte email personalizzabili con placeholder
* Aggiunta opzione per rigenerazione sitemap
* Aggiunta modalità simulazione (dry-run)
* Aggiunto filtro per autore
* Aggiunto dashboard widget
* Aggiunta frequenza cron configurabile
* Aggiunta opzione per tag "scaduto"

= 2.2 =
* Refactoring totale per approvazione WP.org
