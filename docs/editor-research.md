# Extending the text editor in the admin panel: research

Date: 8 October 2026. Question: how to give editors collapsible blocks (as on the old «Бібліотека» (Library) page), and why the visual editor «does not see» tables, image sizes and styling; which extension options exist.

> **Update 8.10.2026:** option C was chosen — the admin panel was moved to Filament 4 (branch `filament-4`); the main text editor is now TipTap, with tables, collapsible blocks and image sizes; the original HTML is not normalized without edits (`HtmlStateCast`), and the HTML mode with its buttons is kept. On 9.10.2026 the «PDF / відео» (PDF / video) button was added (a TipTap node for `<iframe>`): existing PDFs and videos open in visual mode without loss; on the test DB, 6 of 821 texts that TipTap simplifies remain (it was 47; what remains are `id` anchors). Below is the research made before the update.

## State before the update

- Filament **3.3**, the `RichEditor` field = **Trix** (Basecamp). Wrapper `App\Filament\Forms\Components\HtmlRichEditor`: the modes «Візуально / HTML» (Visual / HTML); HTML is CodeMirror 6 with auto-formatting.
- The Trix document model is strictly limited: paragraphs, H1, bold/italic/strikethrough, links, quote, code, lists, attachments. **No** tables, `<details>`, iframes, attributes `class`/`style`/`width`/`height`/`align`, alignment, colour, H2–H6 (except H1). Trix discards all of this on load — which is why for such text the admin has a guard `isTrixLossy()` and a suggestion to switch to HTML.
- Trix itself can be extended only with «attachment» insert buttons (an attachment with ready-made HTML that cannot be edited inside it). For tables, accordions and image sizes, that is a dead end.
- The site (`HtmlSanitizer`, `.prose-site`) already supports tables, `details/summary` (a single accordion style), YouTube/Maps/Docs iframes, and `style` through a whitelist. The limitation lies precisely in the editor.

## Done immediately (8.10.2026)

In HTML mode, above the code, there are two buttons (`resources/js/admin/html-sections.js`, test `tests/Frontend/HtmlSectionsTest.mjs`):

- **«+ Розгортний блок»** (“+ Collapsible block”) — inserts `<details><summary>…</summary>…</details>`; if HTML is selected, it becomes the block's content, and the heading at the start of the selection becomes its caption.
- **«Розділи → розгортні блоки»** (“Sections → collapsible blocks”) — each top-level heading (H2…H6), together with its content up to the next heading of the same level, is turned into a block. Undo — Ctrl/Cmd+Z.

### How to restore the look of the old «Бібліотека» (Library)

On the old site there are 20 panels, of which «Культмасові заходи» (Cultural mass events) contains 15 nested ones. In the new text all 20 became H4 headings (the import flattened the nesting). The procedure for the editor, separately for «Основний текст» (Main text) and for the English text:

1. HTML mode → «Розділи → розгортні блоки» (yields 19 blocks; «Культмасові заходи» remains a heading, since the next heading follows it immediately).
2. Select from `<h4>Культмасові заходи</h4>` to the start of the block «Нормативно-правові документи для бібліотек» → «+ Розгортний блок»: 15 blocks end up inside «Культмасові заходи».
3. Save («Зберегти» (Save)). Verified locally: 20 blocks, 15 of them nested; the style is uniform with «Ліцензування та акредитація» (Licensing and accreditation).

## Options for a full visual editor

| Option | Accordions | Tables (sizes, merging) | Images (size, alignment) | Preservation of imported HTML | Cost of the transition |
|---|---|---|---|---|---|
| **A. Keep Trix + HTML mode with buttons** (current) | buttons in HTML mode | HTML only | HTML only | full (HTML mode) | 0 |
| **B. Plugin `awcodes/filament-tiptap-editor` 3.x** (TipTap/ProseMirror for Filament 3) | `details` | `table` | resize/crop on insert; alignment — partially | TipTap schema: unknown tags and attributes (`style`, `align`, `class`) are dropped | low: field replacement, no Filament upgrade. **The package is marked deprecated** — the author recommends the native Filament 4 editor |
| **C. Upgrade to Filament 4** — native `RichEditor` on TipTap | `details` | `table` + cell merging/splitting, header rows | `resizableImages()` (keeping proportions); image alignment — not claimed | as B (TipTap schema); there is `customBlocks()` — custom blocks (file card, button, callout) with preview | high: a major upgrade of the whole admin panel (Schemas namespaces, resources, 2FA/login pages, `SettingsFormPage`, Livewire tests; there is a `filament/upgrade` script); own theme — Tailwind v4 (already used on the site) |
| **D. TinyMCE 7** (plugin `mohamedsabil83/filament-forms-tinymce` for Filament 3) | `accordion` plugin (open source) | table and cell properties: width, borders, padding | size, alignment, wrapping | the best: `valid_elements`/`extended_valid_elements` allow preserving almost any HTML | medium: field replacement; licence GPL v2+ (`license_key: 'gpl'`, self-hosting — suitable), bundle ~500 KB+, visually an «office»-style interface |
| E. CKEditor 5 (+ General HTML Support) | no standard accordion (own plugin) | yes, with styles | yes | good via GHS | medium; GPL or commercial licence, premium features are paid |

Common limitations of any visual editor:

- Old imported content (`<p align style="font-size:14pt">`, a PDF iframe with `margin-left`) is normalized by TipTap-based editors (B, C) on the first edit — for such texts the HTML mode remains necessary. TinyMCE (D) loses the least.
- The `HtmlSanitizer` remains the last barrier: everything the editor allows (new classes, `style`, width attributes) must be added to its whitelists, otherwise it is dropped on save. Text colour and font-size buttons should deliberately not be offered: the site's uniform look relies on `.prose-site`.
- Image sizes are better stored as `width`/`height` attributes or a limited set of classes (`img-left`, `img-half`), rather than arbitrary `style` — this preserves responsiveness and CLS.

## Recommendation

1. **Now:** the HTML mode buttons (done) — they cover accordions without risk to the existing 1600+ texts.
2. **Next step, if a visual editor is needed for tables and images without a major upgrade:** a pilot of **D (TinyMCE)** on one field (e.g. the body of news items), with a configuration for our sanitizer: accordion, tables, images with sizes and alignment (classes from a short list), without colour or fonts. Estimate: 1–2 days with tests and checks on typical imported texts (no loss on opening and saving).
3. **Strategically:** **C (Filament 4)** — when there is a window for upgrading the admin panel as a whole (after the production switch, not during the SEO migration of October–December 2026). Then also `customBlocks()` for repeating elements (file card, contact, link button). Option B should not be taken: the package is outdated and would lead to the same migration later.
