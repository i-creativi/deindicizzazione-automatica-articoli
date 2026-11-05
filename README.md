# Deindicizzazione Automatica Articoli

Plugin WordPress per deindicizzare e archiviare automaticamente articoli datati da categorie specifiche. Supporta multi-categoria, email personalizzate, log e dashboard.

## Descrizione

Deindicizzazione Automatica Articoli è un plugin completo per la gestione SEO degli articoli datati nel tuo sito WordPress. Il plugin permette di gestire in modo automatico gli articoli datati del tuo sito, migliorando la SEO e mantenendo l'indice di Google pulito da contenuti obsoleti.

## Funzionalità principali

- **Supporto multi-categoria**: seleziona più categorie da deindicizzare
- **Deindicizzazione automatica**: imposta articoli come noindex/nofollow dopo X giorni
- **Modalità archiviazione**: cambia lo stato degli articoli (bozza/privato) dopo Y giorni
- **Notifiche email personalizzabili**: template con placeholder per personalizzare le notifiche
- **Log completo delle attività**: interfaccia admin per visualizzare e gestire i log
- **Dashboard widget**: visualizza statistiche e prossime azioni pianificate
- **Filtro per autore**: includi o escludi articoli di specifici autori
- **Modalità simulazione (dry-run)**: testa la configurazione senza modificare gli articoli
- **Frequenza cron configurabile**: scegli tra esecuzione oraria, giornaliera o settimanale
- **Tag "scaduto"**: opzione per aggiungere automaticamente un tag agli articoli vecchi
- **Rigenerazione sitemap**: supporto per Rank Math e Yoast SEO

## Requisiti

- WordPress 5.0 o superiore
- PHP 7.2 o superiore
- Plugin SEO (Rank Math o Yoast SEO) per funzionalità complete

## Installazione

1. Carica il plugin nella directory `/wp-content/plugins/`
2. Attiva il plugin dal menu 'Plugin' in WordPress
3. Configura le impostazioni in WP Admin > Deindex Articoli
4. Seleziona le categorie da monitorare e imposta i giorni per la deindicizzazione

## Configurazione

### Impostazioni di base

1. **Categorie da deindicizzare**: seleziona una o più categorie i cui articoli verranno deindicizzati dopo il periodo specificato
2. **Giorni prima di noindex**: imposta il numero di giorni dopo i quali gli articoli verranno impostati come noindex, nofollow
3. **Giorni prima di archiviazione**: imposta il numero di giorni dopo i quali cambiare lo stato degli articoli (0 = disabilitato)
4. **Stato di archiviazione**: scegli se impostare gli articoli archiviati come bozza o privato
5. **Frequenza di esecuzione**: scegli la frequenza con cui il plugin controllerà gli articoli (ogni ora, ogni giorno, ogni settimana)

### Notifiche Email

1. **Email di notifica**: inserisci gli indirizzi email separati da virgola per ricevere notifiche
2. **Oggetto email**: personalizza l'oggetto delle email di notifica
3. **Template email**: personalizza il contenuto delle email usando i placeholder disponibili:
   - `{post_titles}`: elenco dei titoli degli articoli processati
   - `{category}`: categorie monitorate
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

## Risoluzione problemi

### Il plugin non deindicizza gli articoli

- Verifica di aver selezionato almeno una categoria
- Controlla che ci siano articoli nelle categorie selezionate più vecchi del numero di giorni impostato
- Verifica che il cron di WordPress funzioni correttamente

### Le email di notifica non vengono ricevute

- Controlla che gli indirizzi email siano inseriti correttamente
- Verifica che il server WordPress possa inviare email
- Controlla la cartella spam

### La sitemap non viene rigenerata

- Verifica di aver attivato l'opzione "Rigenera sitemap automaticamente"
- Controlla di avere installato e attivato Rank Math o Yoast SEO
- Verifica che il plugin SEO abbia i permessi per modificare la sitemap

## FAQ

### Posso selezionare più categorie?
Sì, puoi selezionare tutte le categorie che desideri monitorare.

### Cosa significa "modalità archiviazione"?
Oltre a impostare noindex/nofollow, puoi configurare il plugin per cambiare lo stato degli articoli (da pubblicato a bozza o privato) dopo un certo numero di giorni.

### Come funziona la modalità simulazione?
Attivando questa opzione, il plugin eseguirà tutte le operazioni normalmente ma senza modificare effettivamente gli articoli. Riceverai comunque le email di notifica e i log, utile per testare la configurazione.

### Il plugin funziona con qualsiasi plugin SEO?
Il plugin è ottimizzato per funzionare con Rank Math e Yoast SEO. La funzionalità di base (impostare noindex/nofollow) funziona con Rank Math. La rigenerazione della sitemap è supportata per entrambi.

## Changelog

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