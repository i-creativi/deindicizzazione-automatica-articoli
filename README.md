# Deindicizzazione Automatica Articoli

Plugin WordPress per deindicizzare e archiviare automaticamente articoli e altri tipi di contenuto (CPT) datati, filtrati per categoria o tassonomia. Supporta multi-categoria, email personalizzate, log e dashboard.

## Descrizione

Deindicizzazione Automatica Articoli è un plugin completo per la gestione SEO degli articoli datati nel tuo sito WordPress. Il plugin permette di gestire in modo automatico gli articoli datati del tuo sito, migliorando la SEO e mantenendo l'indice di Google pulito da contenuti obsoleti.

## Funzionalità principali

- **Supporto multi-categoria**: seleziona più categorie da deindicizzare (sottocategorie incluse)
- **Tipi di contenuto personalizzati (CPT)**: per ogni tipo pubblico scegli la tassonomia da usare come filtro (categorie, tag o tassonomie personalizzate) e i termini, oppure "tutti i contenuti" del tipo
- **Deindicizzazione automatica**: imposta gli articoli come noindex dopo X giorni ("noindex, follow" di default, nofollow opzionale)
- **Rank Math, Yoast SEO o nessun plugin SEO**: il noindex viene scritto per entrambi i plugin; senza plugin SEO interviene il robots di WordPress
- **Ogni articolo viene trattato una sola volta**: niente email ripetute, contatori corretti, elaborazione a blocchi sui siti grandi
- **Modalità archiviazione**: cambia lo stato degli articoli (bozza/privato) dopo Y giorni
- **Notifiche email personalizzabili**: template con placeholder per personalizzare le notifiche
- **Log delle attività**: salvato nel database (ultime 200 esecuzioni), scaricabile dall'admin
- **Dashboard widget** (solo amministratori): statistiche e prossime azioni pianificate
- **Filtro per autore**: includi o escludi articoli di specifici autori
- **Modalità simulazione (dry-run)**: testa la configurazione senza modificare gli articoli
- **Frequenza cron configurabile**: scegli tra esecuzione oraria, giornaliera o settimanale
- **Tag "scaduto"**: opzione per aggiungere automaticamente un tag agli articoli vecchi
- **Aggiornamento sitemap**: svuota la cache delle sitemap di Rank Math e Yoast SEO
- **Anteprima ed "Esegui ora"**: la scheda Articoli mostra cosa verrà elaborato alla prossima esecuzione e permette di lanciarla subito
- **Esclusione del singolo articolo**: dal riquadro "Deindicizzazione automatica" nell'editor o dall'anteprima
- **Ripristino**: annulla noindex, archiviazione e tag "scaduto" su un articolo, rimettendo i meta robots originali
- **WP-CLI**: `wp deindex status|preview|run|restore|exclude|include|list`

## Requisiti

- WordPress 5.7 o superiore
- PHP 7.2 o superiore
- Plugin SEO facoltativo: Rank Math o Yoast SEO (senza, il noindex usa il robots di WordPress)

## Installazione

1. Carica il plugin nella directory `/wp-content/plugins/`
2. Attiva il plugin dal menu 'Plugin' in WordPress
3. Configura le impostazioni in WP Admin > Deindex Articoli
4. Scegli i contenuti da elaborare (tipo + tassonomia + termini) e imposta i giorni per la deindicizzazione

## Configurazione

### Impostazioni di base

1. **Contenuti da elaborare**: spunta i tipi di contenuto (Articoli, Pagine, CPT); per ognuno scegli "Filtra per" (una tassonomia del tipo, sottocategorie incluse) e seleziona i termini, oppure "Tutti i contenuti (nessun filtro)". Attenzione ai tipi come i prodotti WooCommerce: il plugin li tratterebbe come gli articoli
2. **Giorni prima di noindex**: imposta il numero di giorni dopo i quali gli articoli verranno impostati come noindex
3. **Aggiungi anche nofollow**: disattivato di default (sconsigliato: con "follow" i link interni continuano a passare valore)
4. **Giorni prima di archiviazione**: imposta il numero di giorni dopo i quali cambiare lo stato degli articoli (0 = disabilitato)
5. **Stato di archiviazione**: scegli se impostare gli articoli archiviati come bozza o privato
6. **Frequenza di esecuzione**: scegli la frequenza con cui il plugin controllerà gli articoli (ogni ora, ogni giorno, ogni settimana)

### Notifiche Email

1. **Email di notifica**: inserisci gli indirizzi email separati da virgola per ricevere notifiche
2. **Oggetto email**: personalizza l'oggetto delle email di notifica
3. **Template email**: personalizza il contenuto delle email usando i placeholder disponibili:
   - `{post_titles}`: elenco dei titoli degli articoli processati
   - `{category}`: contenuti monitorati (es. "Articoli: News; Offerte: tutti i contenuti")
   - `{days}`: giorni impostati per la deindicizzazione

### Opzioni avanzate

1. **Filtro autori**: scegli se escludere o includere solo articoli di specifici autori
2. **Rigenera sitemap**: attiva per rigenerare automaticamente la sitemap dopo ogni operazione
3. **Modalità simulazione**: attiva per testare la configurazione senza modificare gli articoli
4. **Tag "scaduto"**: attiva per aggiungere automaticamente il tag "scaduto" agli articoli vecchi

## Utilizzo

Una volta configurato, il plugin funziona automaticamente in background secondo la frequenza impostata. Puoi monitorare le attività in diversi modi:

1. **Dashboard Widget**: visualizza statistiche e prossime azioni pianificate
2. **Pagina Log**: accedi a WP Admin > Deindex Articoli > Log per visualizzare lo storico delle operazioni
3. **Email di notifica**: ricevi notifiche via email quando gli articoli vengono processati
4. **Scheda Articoli**: WP Admin > Deindex Articoli > Articoli mostra l'anteprima della prossima esecuzione, gli articoli elaborati (con "Ripristina") e quelli esclusi; il pulsante **Esegui ora** lancia subito un'esecuzione

### Escludere o ripristinare un articolo

- **Escludere**: spunta "Escludi dalla deindicizzazione automatica" nel riquadro dell'editor, oppure usa "Escludi" nell'anteprima. L'articolo non verrà mai messo in noindex né archiviato.
- **Ripristinare**: "Ripristina indicizzazione" (riquadro dell'editor, lista articoli o scheda Articoli) rimette i meta robots com'erano prima, ripubblica l'articolo se era stato archiviato, toglie il tag "scaduto" aggiunto dal plugin e lo esclude, così la prossima esecuzione non lo rimette in noindex.

### WP-CLI

```bash
wp deindex status                       # configurazione e stato
wp deindex preview [--format=ids]       # cosa verrebbe elaborato
wp deindex run [--dry-run] [--no-email] # esecuzione immediata
wp deindex restore <id>...              # ripristina ed esclude
wp deindex exclude <id>...              # esclude
wp deindex include <id>...              # toglie l'esclusione
wp deindex list [--excluded]            # articoli elaborati / esclusi
```

## Risoluzione problemi

### Il plugin non deindicizza gli articoli

- Se la modalità simulazione è attiva, nessun articolo viene modificato
- Verifica di aver configurato almeno un tipo di contenuto con dei termini selezionati (o "tutti i contenuti")
- Controlla che ci siano articoli nelle categorie selezionate più vecchi del numero di giorni impostato
- Verifica che il cron di WordPress funzioni correttamente

### Le email di notifica non vengono ricevute

- Controlla che gli indirizzi email siano inseriti correttamente
- Verifica che il server WordPress possa inviare email
- Controlla la cartella spam

### La sitemap non viene aggiornata

- Verifica di aver attivato l'opzione di aggiornamento della sitemap
- Controlla di avere installato e attivato Rank Math o Yoast SEO
- Verifica che il plugin SEO abbia i permessi per modificare la sitemap

## FAQ

### Posso selezionare più categorie?
Sì, puoi selezionare tutte le categorie che desideri monitorare.

### Funziona con i tipi di contenuto personalizzati?
Sì, dalla 2.6.0. Ogni tipo pubblico compare nelle impostazioni: lo abiliti, scegli la tassonomia (anche personalizzata) e i termini, oppure tutti i contenuti del tipo. Un tipo senza tassonomie si può elaborare solo con "tutti i contenuti".

### Cosa significa "modalità archiviazione"?
Oltre a impostare noindex/nofollow, puoi configurare il plugin per cambiare lo stato degli articoli (da pubblicato a bozza o privato) dopo un certo numero di giorni.

### Come funziona la modalità simulazione?
Attivando questa opzione, il plugin eseguirà tutte le operazioni normalmente ma senza modificare effettivamente gli articoli. Riceverai comunque le email di notifica e i log, utile per testare la configurazione.

### Il plugin funziona con qualsiasi plugin SEO?
Il noindex viene scritto sia per Rank Math sia per Yoast SEO, così resta valido anche se il sito cambia plugin SEO. Senza plugin SEO il noindex passa dal robots di WordPress (filtro `wp_robots`). La cache delle sitemap viene svuotata per entrambi.

### Ho aggiornato dalla 2.3 o precedente: cosa cambia?
Fino alla 2.3.1 il noindex di Rank Math veniva salvato in un formato che Rank Math non leggeva, e gli articoli restavano indicizzati. La 2.4.0 ripulisce quei valori all'aggiornamento e applica il noindex alla prima esecuzione. Le impostazioni vengono migrate da sole (categorie da slug a ID) e il vecchio file di log in `uploads` viene spostato nel database e cancellato.

## Hook per sviluppatori
- `icrea_deindex_post_types`: tipi di contenuto elaborati (default: quelli configurati; un tipo aggiunto dal filtro senza configurazione usa le categorie degli articoli)
- `icrea_deindex_batch_size`: articoli per esecuzione e per azione (default 200)
- `icrea_deindex_query_args`: argomenti della WP_Query (una per tipo di contenuto, passato come 4° argomento)
- `icrea_deindex_terms_limit`: termini elencati per tassonomia nelle impostazioni (default 1000)
- `icrea_deindex_log_limit`: voci di log conservate (default 200)
- `icrea_deindex_after_run`: azione dopo ogni esecuzione, con il riepilogo
- `icrea_deindex_post_restored`: azione dopo il ripristino di un articolo
- `icrea_deindex_capability`: capability richiesta per gestire il plugin (default `manage_options`)

## Changelog

### 2.6.0
- Nuovo: tipi di contenuto personalizzati, ognuno con la sua tassonomia e i suoi termini (o tutti i contenuti del tipo)
- Anteprima, log, email e WP-CLI indicano il tipo di contenuto
- Il tag "scaduto" si aggiunge solo ai tipi che supportano i tag
- Migrazione automatica: le categorie selezionate diventano la configurazione degli Articoli

### 2.5.0
- Nuovo: scheda Articoli con anteprima della prossima esecuzione, articoli elaborati ed esclusi
- Nuovo: pulsante "Esegui ora"
- Nuovo: esclusione del singolo articolo (riquadro nell'editor, anteprima, WP-CLI)
- Nuovo: ripristino dell'articolo (meta robots originali, ripubblicazione, rimozione del tag "scaduto")
- Nuovo: comandi WP-CLI `wp deindex`
- Il tag "scaduto" viene tolto al ripristino solo se l'ha aggiunto il plugin

### 2.4.0
- Fix: il noindex di Rank Math veniva serializzato due volte e ignorato, quindi gli articoli non venivano mai deindicizzati; i valori rotti vengono ripuliti all'aggiornamento
- Fix: ogni articolo viene trattato una sola volta (prima ogni esecuzione rielaborava tutto, con email ripetute e contatore gonfiato)
- Fix: possibile errore fatale con PHP 8 nella rigenerazione della sitemap
- Fix: il log era un file pubblico in uploads; ora sta nel database
- Fix: widget della dashboard visibile solo agli amministratori
- Fix: validazione delle impostazioni (frequenza, stato, autori, email, giorni)
- Nuovo: supporto noindex per Yoast SEO e fallback sul robots di WordPress
- Nuovo: "noindex, follow" di default, nofollow opzionale
- Nuovo: sottocategorie incluse, categorie salvate per ID, elaborazione a blocchi, disinstallazione pulita, link Impostazioni
- Migliorato: stringhe traducibili, CSS caricato con enqueue, salvataggio via admin-post con nonce e permessi
- Requisito minimo: WordPress 5.7

### 2.3.1
- Prima pubblicazione su WordPress.org delle funzionalità 2.3 (le versioni .org precedenti erano ferme alla 2.2)
- Aggiornato "Tested up to" a WordPress 7.1

### 2.3
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

### 2.2
- Refactoring totale per approvazione WP.org

## Supporto

Per supporto, domande o segnalazioni di bug, contatta gli sviluppatori all'indirizzo [https://www.i-creativi.com/](https://www.i-creativi.com/).

## Licenza

Questo plugin è rilasciato sotto licenza GPLv2 o successiva.