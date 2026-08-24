# M5-B — AIML Template Overlays Closure

**Status:** COMPLETE on `main` (implementation merged; formal tag/ZIP/deploy separately authorized)  
**Plan:** [M5B_AIML_TEMPLATE_OVERLAYS.md](../plans/M5B_AIML_TEMPLATE_OVERLAYS.md)  
**USA version:** **0.5.0**  
**AIML prerequisite:** **1.8.0** (M5-A.1 public descriptor factory)  
**Closed:** 2026-08-24

---

## 1. Verdict

**M5-B IMPLEMENTATION: PASS**

USA ships an optional AIML adapter that:

- Registers chrome-owned `usa_announcement` surfaces for Workspace/Jobs extract
- Extracts announcement `post_content` via `TranslationUnitDescriptor::from_source(..., Contract::FORMAT_HTML, ...)`
- Overlays visitor bodies through Extension `VisitorTranslationResolver` + `aiml_visitor_language()`
- Invalidates AIML only when body content actually changes (`aiml_mark_source_dirty`)
- Preserves protected merge-tag multisets and source-authoritative free-shipping requirements

When AIML is absent/incompatible, USA behaviour is unchanged (source templates only).

---

## 2. Prerequisite evidence

| Gate | Evidence |
|------|----------|
| AIML M5-A.1 on `main` | merge `980e463b73a59901dd50fc12b198c7f1813b0546` |
| DEV bind-mount | `/opt/biopentra/dev/ai-multilingual` → WP plugins |
| Feature probe | `AIML_VERSION=1.8.0`, `FORMAT_HTML=html`, `from_source` + live descriptor construction OK |

Formal AIML/USA GitHub tags remain required only for production/ZIP deploy.

---

## 3. DEV acceptance evidence (2026-08-24)

| Check | Result |
|-------|--------|
| USA bind-mount version | `USA_VERSION=0.5.0` active |
| AIML bind-mount version | `AIML_VERSION=1.8.0` active |
| Feature probe | `AimlCompatibility::is_compatible() === true` |
| Chrome admission | `usa_announcement` activated, `integration_units_only` |
| Extract | `p:universal_site_announcements:announcement:{id}:body` via `from_source` + `FORMAT_HTML` |
| Overlay hit (sv) | Localized body returned for eligible published non-stale translation |
| Signature mismatch | Falls back to source template |
| Dirty | `aiml_mark_source_dirty('post', $id)` returns true for admitted chrome CPT |
| CPT privacy | `public=false`, `show_in_rest=false`, no public rewrite/archive change |
| Unit tests | `107` tests OK |

---

## 4. Key paths

- `src/Integration/AimlCompatibility.php`
- `src/Integration/AimlIntegration.php`
- `src/Integration/TemplateOverlay.php`
- `src/Plugin.php`
- `src/Announcement/Repository.php`
- `src/Admin/AnnouncementMetaBoxes.php`
- `tests/unit/AimlCompatibilityTest.php`
- `tests/unit/TemplateOverlaySignatureTest.php`
