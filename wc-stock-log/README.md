# WC Stock Log – Stock History / Ιστορικό Αποθέματος

**EN** · Lightweight, bilingual (English/Greek) WooCommerce plugin that logs **every stock change** on products and variations — **who**, **when**, **from where**, old → new quantity — Shopify-style «View adjustment history».

**GR** · Πολύ ελαφρύ, δίγλωσσο (Αγγλικά/Ελληνικά) plugin για WooCommerce που καταγράφει **κάθε αλλαγή αποθέματος** σε προϊόντα και παραλλαγές — **ποιος**, **πότε**, **από πού**, παλιά → νέα ποσότητα — στο στιλ του «View adjustment history» του Shopify.

> 🌐 Η γλώσσα ακολουθεί αυτόματα τη γλώσσα του WordPress (site ή προφίλ χρήστη): Ελληνικά → ελληνικό UI, English → αγγλικό UI. Base strings στα αγγλικά, πλήρης ελληνική μετάφραση σε `languages/` (`el` + `el_GR`), έτοιμο `.pot` για άλλες γλώσσες.

---

## 🇬🇷 Ελληνικά

### Τι καταγράφει

| Πεδίο | Περιγραφή |
|---|---|
| Ημερομηνία & ώρα | Αποθηκεύεται σε UTC, εμφανίζεται στη ζώνη ώρας του site |
| Προϊόν / Παραλλαγή | Όνομα + χαρακτηριστικά παραλλαγής (π.χ. «Μέγεθος: 40») + SKU, αποθηκευμένα μόνιμα (μένουν και αν διαγραφεί το προϊόν) |
| Παλιό → Νέο απόθεμα | Και η μεταβολή (+3 / −2) με χρωματιστό badge |
| Συμβάν (πηγή) | Παραγγελία (με link), επαναφορά από ακύρωση, επιστροφή χρημάτων (restock), επεξεργασία προϊόντος, γρήγορη/μαζική επεξεργασία, εισαγωγή CSV, REST API/εξωτερική εφαρμογή (π.χ. ERP, Skroutz sync), cron, WP-CLI, ιστότοπος/ταμείο |
| Χρήστης | Ο συνδεδεμένος χρήστης — και για REST API κλήσεις, ο χρήστης του API key. «Σύστημα / Επισκέπτης» όταν δεν υπάρχει χρήστης |

### Δυνατότητες

- **Σελίδα «WooCommerce → Ιστορικό αποθέματος»** με φίλτρα: αναζήτηση (όνομα/SKU), συμβάν, χρήστης, εύρος ημερομηνιών· ταξινόμηση και σελιδοποίηση.
- **Σύνδεσμος «Προβολή ιστορικού αποθέματος»** στην καρτέλα Αποθέματος κάθε προϊόντος **και σε κάθε παραλλαγή**.
- **Εξαγωγή CSV** (με BOM — σωστά ελληνικά στο Excel), σεβόμενη τα ενεργά φίλτρα.
- **Αυτόματος καθαρισμός**: διατήρηση σε ημέρες (προεπιλογή 365, `0` = για πάντα), ημερήσιο cron, διαγραφή σε δόσεις.
- **Συμβατό με HPOS** (custom order tables).

### Γιατί είναι ελαφρύ

- Δικός του **πίνακας βάσης με indexes** (`wp_wsl_stock_log`) — όχι post meta, options με `autoload=no`.
- **1 INSERT ανά πραγματική αλλαγή**· τα no-op αγνοούνται.
- Στο frontend φορτώνει **μόνο ο logger** (μερικά hooks) — καθόλου CSS/JS· admin κώδικας μόνο στο admin, CSS μόνο στη σελίδα του ιστορικού.
- Καμία εξωτερική κλήση, καμία βιβλιοθήκη.

### Εγκατάσταση

1. Ανέβασε το φάκελο `wc-stock-log/` στο `wp-content/plugins/` (ή το ZIP από *Πρόσθετα → Προσθήκη νέου → Μεταφόρτωση*).
2. Ενεργοποίησέ το — ο πίνακας δημιουργείται αυτόματα.
3. *WooCommerce → Ιστορικό αποθέματος*.

> **Σημείωση:** Αλλαγές με **απευθείας SQL** στη βάση (εκτός των API του WooCommerce) δεν μπορούν να καταγραφούν — ισχύει για κάθε plugin καταγραφής. Με τη **διαγραφή** του plugin σβήνονται πίνακας, ρυθμίσεις και cron.

---

## 🇬🇧 English

### What it records

| Field | Description |
|---|---|
| Date & time | Stored in UTC, displayed in the site's timezone |
| Product / Variation | Name + variation attributes (e.g. "Size: 40") + SKU, stored permanently (history survives product deletion) |
| Old → New stock | Plus the adjustment (+3 / −2) as a colored badge |
| Event (source) | Order (linked), order restock on cancellation, refund restock, product edit, quick/bulk edit, CSV import, REST API/external app (e.g. ERP syncs), cron, WP-CLI, storefront/checkout |
| User | The logged-in user — for REST API calls, the API key's user. "System / Guest" when there is none |

### Features

- **"WooCommerce → Stock history" page** with filters: search (name/SKU), event, user, date range; sorting and pagination.
- **"View stock history" link** on each product's Inventory tab **and on every variation**.
- **CSV export** (with BOM for Excel), honoring the active filters.
- **Automatic cleanup**: retention in days (default 365, `0` = forever), daily cron, batched deletes.
- **HPOS compatible** (custom order tables).

### Why it's lightweight

- Own **indexed database table** (`wp_wsl_stock_log`) — no post meta, options are `autoload=no`.
- **1 INSERT per real change**; no-ops are skipped.
- On the frontend only the logger loads (a few hooks) — no CSS/JS; admin code loads only in wp-admin, CSS only on the log page.
- No external calls, no libraries.

### How it works (technical)

Covers **both** WooCommerce stock paths:

1. **`wc_update_product_stock()`** — orders, refunds, cancellations, REST adjust: `woocommerce_{product|variation}_before_set_stock` / `_set_stock`.
2. **CRUD save** (`$product->set_stock_quantity()` + `save()`) — product edit, quick/bulk edit, CSV import, REST API: `_set_stock` fires from the data store before `apply_changes()`, so the old value is read from `get_data()` and the new one from `get_changes()` — zero extra queries.

Order attribution uses the same status hooks WooCommerce itself uses (`woocommerce_payment_complete`, `processing/completed/on-hold` for reduce, `cancelled/pending` for restock), **plus** a safety net on `woocommerce_reduce_order_stock` / `woocommerce_restore_order_stock` / `woocommerce_restock_refunded_item` that retroactively corrects source/order on rows written in the same request.

> **Note:** Changes made with **direct SQL** (bypassing WooCommerce APIs) cannot be captured — true for any audit plugin. **Deleting** the plugin removes the table, options and cron.

### Developer hooks

```php
// Exclude something from logging
add_filter( 'wsl_should_log', function ( $should, $product, $old, $new ) {
    return $should;
}, 10, 4 );

// After every logged change (e.g. low-stock alerts)
add_action( 'wsl_logged', function ( $row_id, $row ) {}, 10, 2 );

// Rows per page on the admin table (default 50)
add_filter( 'wsl_per_page', fn () => 100 );
```

### Translations / Μεταφράσεις

- Base strings: English. Greek (`el`, `el_GR`) ships in `languages/` as `.po`/`.mo`.
- Template `languages/wc-stock-log.pot` included for additional languages.

Requirements: WordPress 6.0+, WooCommerce 7.0+, PHP 7.4+.
