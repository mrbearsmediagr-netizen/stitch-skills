# BM Custom Header & Footer

Απλό WordPress plugin για custom **header** και **footer** σε οποιοδήποτε κλασικό theme. Τα templates φτιάχνονται είτε με **Elementor (δωρεάν έκδοση — δεν χρειάζεται Pro)** είτε με **σκέτο HTML**, και είναι πλήρως συμβατά με **WPML** για πολυγλωσσικά sites.

## Εγκατάσταση

1. Πλασάρισε τον φάκελο `bm-header-footer/` στο `wp-content/plugins/` (ή ανέβασε το ZIP από Πρόσθετα → Προσθήκη νέου → Μεταφόρτωση).
2. Ενεργοποίησε το plugin.
3. Πήγαινε στο **Εμφάνιση → Header & Footer**.

## Χρήση

1. **Προσθήκη νέου προτύπου** → δώσε τίτλο (π.χ. «Main Header»).
2. Στο κουτί **Ρυθμίσεις προτύπου** διάλεξε:
   - **Τύπος**: Header ή Footer.
   - **Πηγή περιεχομένου**:
     - *Elementor / Επεξεργαστής WordPress* → πάτα «Edit with Elementor» και σχεδίασέ το (δουλεύει με το δωρεάν Elementor).
     - *Custom HTML* → κόλλησε τον HTML κώδικά σου στο πεδίο (επιτρέπονται `<style>`, `<script>` και shortcodes).
3. **Δημοσίευση**. Το header/footer αντικαθιστά αυτόματα αυτό του theme σε όλο το site.
4. Για απενεργοποίηση, βάλε το πρότυπο σε *Πρόχειρο* (ή σβήσε το).

Σημειώσεις:

- Αν υπάρχουν πολλά δημοσιευμένα πρότυπα ίδιου τύπου, χρησιμοποιείται το πιο πρόσφατα δημοσιευμένο.
- **Αποθήκευσε πρώτα τον Τύπο (Header/Footer)** και μετά πάτα «Edit with Elementor» — αλλιώς το πρότυπο καταχωρείται με τον προεπιλεγμένο τύπο (Header).
- **Δημοσίευσε Header και Footer μαζί (ως ζευγάρι).** Τα κλασικά themes ανοίγουν τα layout wrappers τους στο header και τα κλείνουν στο footer· αν αντικατασταθεί μόνο η μία πλευρά, το markup μένει αταίριαστο. Σε themes με «βαριά» wrappers (Astra, GeneratePress, OceanWP) το περιεχόμενο των σελίδων χάνει το container styling του theme — γι' αυτό προτείνεται Hello Elementor ή σελίδες με full-width/Elementor layout.

## WPML

- Το post type είναι δηλωμένο ως μεταφράσιμο (`wpml-config.xml`). Στο **WPML → Settings → Post Types Translation** το «Header & Footer» εμφανίζεται ως translatable.
- Μετάφρασε κάθε πρότυπο στις γλώσσες του site (με το «+» στη λίστα ή με τον WPML Translation Editor). Το πεδίο Custom HTML (`_bmhf_html`) μεταφράζεται· ο τύπος και το mode αντιγράφονται αυτόματα.
- Στο frontend εμφανίζεται αυτόματα το πρότυπο της τρέχουσας γλώσσας. Αν δεν υπάρχει μετάφραση, εμφανίζεται το πρωτότυπο (fallback).

## Shortcode

Οποιοδήποτε πρότυπο μπορεί να εμφανιστεί και με shortcode (π.χ. μέσα σε σελίδα ή widget):

```
[bmhf_template id="123"]
```

Το ID φαίνεται στη στήλη «Shortcode» της λίστας προτύπων. Με WPML, το shortcode εμφανίζει αυτόματα τη μετάφραση της τρέχουσας γλώσσας.

## Συμβατότητα

- **Themes**: Όλα τα κλασικά themes που καλούν `get_header()` / `get_footer()` (Hello Elementor, Astra, OceanWP, GeneratePress, Twenty Twenty-One κ.λπ.). Τα block themes (FSE, π.χ. Twenty Twenty-Four) δεν υποστηρίζουν αυτόματη αντικατάσταση — εκεί χρησιμοποίησε το shortcode.
- **Elementor**: Δωρεάν έκδοση αρκεί. Το plugin δουλεύει και χωρίς Elementor (Custom HTML ή κανονικός επεξεργαστής).
- **WPML**: Πλήρης υποστήριξη. Δουλεύει και με Polylang (μέσω του `wpml_object_id` compatibility layer).
- **Απαιτήσεις**: WordPress 6.5+, PHP 7.4+.

## Hooks για developers

- `bmhf_template_id` (filter): άλλαξε ποιο template φορτώνει ανά τύπο.
- `bmhf_enabled` (filter): απενεργοποίησε την αντικατάσταση υπό συνθήκες (π.χ. `add_filter( 'bmhf_enabled', fn( $on ) => ! is_page( 42 ) );`).
- `bmhf_before_header`, `bmhf_after_header`, `bmhf_before_footer`, `bmhf_after_footer` (actions).

## Ασφάλεια

Το πεδίο Custom HTML αποθηκεύεται αυτούσιο μόνο για χρήστες με δικαίωμα `unfiltered_html` (administrators). Για άλλους ρόλους φιλτράρεται με `wp_kses_post`. Το φίλτρο εφαρμόζεται στο επίπεδο του meta (`register_post_meta` sanitize callback), οπότε ισχύει και για αποθηκεύσεις μέσω του WPML Translation Editor — μεταφραστές χωρίς `unfiltered_html` δεν μπορούν να περάσουν `<script>` στο header/footer.
