# Peptide Store Core (plugin)
Custom functionality for our WooCommerce research-peptide store. Functionality
lives here; design lives in the child theme. See project handoff for full context.

## Extension points (extend WITHOUT editing core)
- `peptidestore_schema_organization` — modify sitewide Organization JSON-LD.
- `peptidestore_schema_product` ($data,$product) — add COA URL/purity/testing/brand.
- `peptidestore_schema_article` ($data,$post_id) — extend Article schema.
- `peptidestore_page_faqs` ($faqs,$post_id) — supply FAQs ([['question','answer'],...]) to emit FAQPage schema.
- `peptidestore_enable_age_gate` — return true to enable the acknowledgement gate.

## Rename before scaling
peptidestore→slug, Peptide_Store→Brand (classes/namespace), PEPTIDE_STORE→BRAND (constants).
