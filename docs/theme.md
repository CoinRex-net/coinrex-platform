# CoinRex Theme Reference

## Purpose

This document records the **current visual theme used on `submit-review.php`** as the reference implementation for the future shared CoinRex frontend theme system.

The goal is to later move these repeated values into **one shared theme source**, so changing a single file updates all frontend pages instead of editing each page stylesheet individually.

---

## Reference Page

- Page: `submit-review.php`
- Stylesheet: `assets/css/submit-review.css`

This page currently represents the strongest version of the new CoinRex frontend direction:

- **Royal Blue** primary brand tone
- **Gold** highlight/accent tone
- **White** high-contrast text and form surfaces
- **Dark blue layered backgrounds**
- **Premium card gradients**
- **Clear input visibility**

---

## Core Theme Palette

### Primary Brand Colors

| Role | Value | Usage |
|---|---|---|
| Primary Blue | `#1D4ED8` | active accents, headings, buttons, emphasis |
| Primary Dark Blue | `#1E40AF` | button gradients, deeper emphasis |
| Primary Light Blue | `#93C5FD` | soft accent text, progress emphasis |
| Hero Blue | `#11306d` | hero gradient top layer |
| Deep Blue | `#0d2350` | hero / premium card mid tone |
| Navy Base | `#0a1730` | premium background base |

### Gold Accent Colors

| Role | Value | Usage |
|---|---|---|
| Gold Primary | `#D4AF37` | borders, accent chips, premium highlights |
| Gold Bright | `#FACC15` | icons, highlight emphasis |
| Gold Soft Text | `#ffe08a` | badge text, optional chips, accent labels |

### Neutral / Text Colors

| Role | Value | Usage |
|---|---|---|
| White | `#ffffff` | primary headings, labels, key text |
| White 90% | `rgba(255, 255, 255, 0.90)` | important body text on dark cards |
| White 88% | `rgba(255, 255, 255, 0.88)` | body copy on hero / cards |
| White 84% | `rgba(255, 255, 255, 0.84)` | secondary text on premium dark cards |
| Blue-tinted Light Text | `#dbeafe` | helper text, muted labels, metric captions |
| Muted Blue Gray | `#b9c7e8` / `#b8c7e6` | secondary muted text |
| Dark Input Text | `#0b1730` | text inside white inputs |
| Placeholder Gray | `#5b6982` | placeholders inside white fields |

### Status / Semantic Colors

| Role | Value | Usage |
|---|---|---|
| Error Red | `#EF4444` | error borders, delete/remove button, toasts, required asterisk |
| Error Soft | `#F87171` / `#FCA5A5` | error text / warnings |
| Success Green | `#22c55e` | character count success |
| Warning Gold | `#F59E0B` | star/rating family fallback, caution states |

---

## Global Surface Structure on Submit Review Page

## 1. Page Background

Selector:

- `.submit-review-main`

Colors:

- `rgba(29, 78, 216, 0.20)` top-left radial glow
- `rgba(212, 175, 55, 0.12)` top-right gold glow
- `linear-gradient(180deg, #081120 0%, #0b1730 100%)`

Purpose:

- sets the base theme tone for the page
- creates premium depth
- mixes blue + gold branding from the very first layer

---

## 2. Main Layout Container

Selectors:

- `.submit-container`
- `.submit-container-upgraded`

Behavior:

- standard centered container
- upgraded max width: `1180px`

No direct theme color, but this is the main wrapper all shared layouts should follow.

---

## Hero System

## 3. Hero Main Card

Selector:

- `.hero-copy`

Colors:

- radial glow: `rgba(59, 130, 246, 0.24)`
- gradient: `#11306d -> #0d2350 -> #0a1730`
- border: `rgba(212, 175, 55, 0.20)`

Text colors:

- heading: `#ffffff`
- paragraph: `rgba(255,255,255,0.88)`

Shadow:

- `0 28px 60px rgba(2, 8, 23, 0.18)`

Purpose:

- premium main hero panel
- strongest blue identity section

---

## 4. Hero Accent Text in Main Heading

Selector:

- `.hero-title-accent`

Color:

- `#1d4ed8`

Purpose:

- split heading style:
  - normal phrase in white
  - emphasized phrase in royal blue

Current example:

- `Share your` → white
- `Real Project Experience` → royal blue

---

## 5. Hero Top Badge

Selector:

- `.header-badge`

Colors:

- background: `rgba(255, 255, 255, 0.10)`
- border: `rgba(255, 255, 255, 0.18)`
- icon: `#facc15`
- text: `#ffffff`

Purpose:

- small premium hero label
- neutral glass effect with gold icon

---

## 6. Hero Feature Pills

Selector:

- `.hero-point`

Colors:

- background: `rgba(255,255,255,0.10)`
- border: `rgba(255,255,255,0.18)`
- text: `#ffffff`
- icon: `#facc15`

Purpose:

- short readable benefit tags

---

## 7. Hero Side Card

Selector:

- `.hero-sidecard`

Colors:

- background: `#132a59 -> #102042 -> #0b1630`
- border: `rgba(212, 175, 55, 0.22)`
- heading: `#ffffff`
- list text: `rgba(255,255,255,0.90)`
- bullet dot gradient: `#d4af37 -> #facc15`

Purpose:

- support checklist panel beside hero

---

## Badge / Kicker System

## 8. Shared Gold Kickers

Selectors:

- `.hero-sidecard-kicker`
- `.project-card-kicker`
- `.step-kicker`

Colors:

- background: `rgba(212, 175, 55, 0.16)`
- border: `rgba(212, 175, 55, 0.26)`
- text: `#ffe08a`

Purpose:

- reusable premium small labels
- should become a shared global component class

---

## Project Summary Card

## 9. Selected Project Card

Selector:

- `.selected-project-card-upgraded`

Colors:

- background: `#102349 -> #0c1e40 -> #0a1730`
- border: `rgba(212, 175, 55, 0.18)`
- shadow: `0 24px 50px rgba(2, 8, 23, 0.18)`

Text:

- project name: theme white
- helper line: `rgba(255,255,255,0.85)`
- verified badge: `#facc15`

Pills:

- `.project-summary-pills span`
  - background: `rgba(255,255,255,0.10)`
  - border: `rgba(255,255,255,0.14)`
  - text: `#ffffff`

---

## Trust / Reminder Section

## 10. Trust Section Wrapper

Selector:

- `.review-trust-card`

Colors:

- radial glow: `rgba(59, 130, 246, 0.18)`
- gradient: `#102349 -> #0d1f43 -> #091528`
- border: `rgba(212, 175, 55, 0.14)`

Text:

- heading: `#ffffff`
- paragraph: `rgba(255,255,255,0.82)`

---

## 11. Trust Cards

Selector:

- `.review-trust-item`

Colors:

- background: `rgba(16, 35, 73, 0.88) -> rgba(9, 21, 40, 0.92)`
- border: `rgba(255,255,255,0.12)`
- title: `#ffffff`
- body: `rgba(255,255,255,0.84)`

Icon block:

- `.review-trust-icon`
  - gradient: `rgba(212,175,55,0.18)` + `rgba(59,130,246,0.14)`
  - border: `rgba(212,175,55,0.24)`

Icon color:

- `.review-trust-icon i` → `#facc15`

---

## Form Wizard Structure

## 12. Main Form Card

Selector:

- `.submit-card-upgraded`

Colors:

- radial glow: `rgba(59,130,246,0.08)`
- background gradient: `#12306b -> #0f2859 -> #0a1730`
- border: `rgba(212,175,55,0.18)`

Purpose:

- main form shell for all wizard steps

---

## 13. Wizard Progress / Step Navigation

Selectors:

- `.wizard-track`
- `.wizard-fill`
- `.wizard-step-nav`

Colors:

- track: `var(--color-border-light)`
- fill: `var(--color-primary) -> var(--color-primary-dark)`
- inactive step text: `var(--color-text-muted)` / `#b9c7e8`
- active/completed text: `var(--color-primary-light)`
- active/completed circle background: `var(--color-primary)`

Purpose:

- step visibility and progress orientation

---

## 14. Step Tip Cards

Selector:

- `.step-tip-card`

Colors:

- gradient: `rgba(212,175,55,0.14)` + `rgba(59,130,246,0.10)`
- border: `rgba(212,175,55,0.22)`
- title: `#ffffff`
- text: `rgba(255,255,255,0.84)`

Alternate:

- `.tip-soft`
  - darker variation for optional info blocks

---

## 15. Optional / Required Chip

Selector:

- `.optional-chip`

Colors:

- background: `rgba(212, 175, 55, 0.14)`
- border: `rgba(212, 175, 55, 0.22)`
- text: `#ffe08a`

Current semantic label can be either required/optional visually.

---

## Input / Field Theme

## 16. Form Labels

Selector:

- `.form-group label`

Colors:

- main label: `#FFFFFF`
- helper note inside label: `#DBEAFE`
- required asterisk: `#EF4444`

Style:

- bold label weight
- slightly premium tracking
- helper note can be placed on the **next line** using a block style

Example pattern now used on submit review page:

```text
Transaction Hash (TX Hash) *
Purpose: Used to match on-chain activity when possible
```

Implementation classes:

- `.required-asterisk`
- `.field-note`
- `.field-note-block`

Current styling:

- `.required-asterisk`
  - color: `#EF4444`
  - weight: `800`
- `.field-note-block`
  - display: `block`
  - margin-top: `6px`
  - font-size: `11px`
  - line-height: `1.55`

---

## 17. Text Fields / Inputs / Textareas / Selects

Selectors:

- `.form-group input`
- `.form-group textarea`
- `.form-group select`

Colors:

- background: `#ffffff`
- border: `rgba(212, 175, 55, 0.35)`
- text: `#0b1730`
- placeholder: `#5b6982`

Focus state:

- border: `#1d4ed8`
- focus ring: `rgba(29, 78, 216, 0.18)`

Purpose:

- make the field immediately obvious
- high contrast against dark theme

This is one of the most important shared-theme decisions.

---

## 18. Wallet Selection Panel

Selector:

- `.wallet-explain`

Colors:

- background: `rgba(19, 42, 89, 0.92) -> rgba(13, 28, 60, 0.98)`
- border: `rgba(212,175,55,0.20)`
- title: `#ffffff`
- body: `rgba(255,255,255,0.88)`

Wallet option cards:

- `.wallet-option`
  - background: `rgba(18, 27, 46, 0.95) -> rgba(12, 21, 37, 0.95)`
  - border: `var(--color-border-light)`
  - label title: `#ffffff`
  - helper text: `#dbeafe`

---

## 19. Upload Area

Selector:

- `.file-upload-area`

Colors:

- dashed border: `var(--color-border-medium)`
- background: `rgba(24, 34, 56, 0.92) -> rgba(13, 23, 40, 0.96)`
- icon: `#facc15`
- title: `#ffffff`
- helper text: `#dbeafe`

Hover:

- stronger blue border / brighter panel background

Preview block:

- `.file-preview`
  - background: `rgba(255,255,255,0.96)`
  - border: `rgba(212,175,55,0.30)`
  - icon: `#1d4ed8`
  - filename: `#0b1730`
  - remove button: `#ef4444`

---

## 20. Safety / Info Note

Selector:

- `.proof-safety-note`

Colors:

- background: `rgba(59,130,246,0.16)` + `rgba(14,165,233,0.08)`
- border: `rgba(59,130,246,0.18)`
- text: `#bfdbfe`

Purpose:

- informational security callout

---

## Review Writing Step

## 21. Star Rating

Selectors:

- `.stars i`
- `.rating-text`

Colors:

- stars: `#d4af37`
- glow: `rgba(212,175,55,0.18)`
- text label: `#ffffff`

Character count:

- default: `#dbeafe`
- JS success: `#22c55e`
- JS invalid: `#ef4444`

---

## Scoring / Optional Analytics Step

## 22. Signals Intro Banner

Selector:

- `.signals-intro`

Colors:

- background: `rgba(29,78,216,0.18)` + `rgba(212,175,55,0.10)`
- border: `rgba(212,175,55,0.18)`
- title: `#ffffff`
- text: `rgba(255,255,255,0.84)`

Badge:

- `.signals-intro-badge`
  - background: `rgba(255,255,255,0.12)`
  - border: `rgba(255,255,255,0.18)`
  - text: `#ffffff`

---

## 23. Score Cards

Selector:

- `.score-item`

Colors:

- background: `rgba(18, 42, 88, 0.90) -> rgba(10, 24, 47, 0.96)`
- border: `rgba(212,175,55,0.16)`
- title: `#ffffff`
- helper text: `rgba(255,255,255,0.76)`
- output number: `#facc15`

Score chip:

- `.score-card-chip`
  - background: `rgba(212,175,55,0.16)`
  - border: `rgba(212,175,55,0.22)`
  - text: `#ffe08a`

Scale text:

- `#dbeafe`

---

## Reward Estimate Card

## 24. Reward Preview

Selector:

- `.reward-preview.improved`

Colors:

- background: `rgba(29,78,216,0.12)` + `rgba(59,130,246,0.06)`
- border: `rgba(29,78,216,0.25)`
- heading: `#ffffff`
- heading icon: `#facc15`
- big reward number: `#ffffff`
- small suffix: `#dbeafe`
- note text: `rgba(255,255,255,0.84)`

Breakdown mini cards:

- background: `rgba(255,255,255,0.10)`
- border: `rgba(255,255,255,0.16)`
- label text: `#dbeafe`
- value text: `#ffffff`

---

## Final Confirmation Step

## 25. Consent Panel

Selector:

- `.consent-panel`

Colors:

- background: `#12306b -> #0f2758 -> #0a1730`
- border: `rgba(212,175,55,0.18)`
- title: `#ffffff`
- body text: `rgba(255,255,255,0.84)`

Status pill:

- background: `rgba(255,255,255,0.12)`
- border: `rgba(255,255,255,0.18)`
- text: `#ffffff`

Final info cards:

- `.final-review-item`
  - background: `rgba(255,255,255,0.10)`
  - border: `rgba(255,255,255,0.16)`
  - caption: `#dbeafe`
  - value: `#ffffff`

---

## 26. Final Checklist Block

Selector:

- `.beginner-checklist`

Colors:

- background: `rgba(255,255,255,0.10)`
- border: `rgba(255,255,255,0.16)`
- heading: `#ffffff`
- heading icon: `#facc15`
- checklist text: `rgba(255,255,255,0.88)`
- bullet: `#facc15`

---

## 27. Terms Checkbox Section

Selector:

- `.terms-checkbox-wrapper`

Colors:

- background: `rgba(20, 29, 50, 0.94) -> rgba(12, 21, 37, 0.96)`
- border: `var(--color-border-light)`
- text: currently `var(--color-text-secondary)`
- link: currently `var(--color-primary)`

Checkbox checked state:

- background: `var(--color-primary)`
- icon check: `#fff`

This should later be standardized into a shared checkbox component.

---

## 28. Submit Hint Panel

Selector:

- `.submit-hint`

Colors:

- background: `rgba(59,130,246,0.12)` + `rgba(96,165,250,0.06)`
- border: `rgba(59,130,246,0.16)`
- text: `#ffffff`

---

## Buttons

## 29. Primary Action Buttons

Selectors:

- `.btn-submit`

Colors:

- background gradient: `var(--color-primary) -> var(--color-primary-dark)`
- text: `#fff`
- shadow: `rgba(29,78,216,0.22)`

Purpose:

- next / submit / key CTA actions

---

## 30. Secondary Buttons

Selectors:

- `.btn-cancel`

Colors:

- background: `var(--color-bg-elevated)`
- border: `var(--color-border-light)`
- text: `rgba(255,255,255,0.88)`
- hover border/text: `var(--color-primary)`

---

## 31. Toasts

Selectors:

- `.toast.success`
- `.toast.error`

Colors:

- toast base: `var(--color-bg-elevated)`
- text: `rgba(255,255,255,0.88)`
- success accent: `#1D4ED8`
- error accent: `#ef4444`

---

## 32. Error States / Locked Review States

### Error Message Panel

Selector:

- `.error-message`

Colors:

- background: `rgba(239,68,68,0.12)`
- border: `rgba(239,68,68,0.25)`
- text: `#fca5a5`
- links: `#f87171`

### Locked Review Icon

Selector:

- `.review-status-icon`

Colors:

- background: `rgba(239,68,68,0.12)`
- icon: `#f87171`

### Locked Kicker

Selector:

- `.review-status-kicker`

Colors:

- background: `rgba(239,68,68,0.12)`
- border: `rgba(239,68,68,0.22)`
- text: `#fca5a5`

---

## Responsive Theme Rules

Mobile keeps the same palette but changes:

- reduced paddings
- single-column layout for:
  - hero
  - trust cards
  - proof grid
  - pros/cons grid
  - wallet grid
  - reward breakdown
  - checklist
- sticky action bar at bottom

Important note:

The color system itself does **not** change on mobile — only spacing/layout shifts.

---

## Hardcoded Colors That Should Become Shared Tokens Later

The submit-review page still uses many hardcoded values directly in CSS.

These should later be moved into a shared theme file or global token map.

### Highest-priority token candidates

#### Brand tokens

- `#1D4ED8`
- `#1E40AF`
- `#93C5FD`
- `#D4AF37`
- `#FACC15`

#### Background tokens

- `#081120`
- `#0b1730`
- `#0d1b34`
- `#12254a`
- `#11306d`
- `#0d2350`
- `#0a1730`

#### Surface tokens

- `rgba(13, 27, 52, 0.92)`
- `rgba(16, 35, 73, 0.72)`
- `rgba(255,255,255,0.10)`
- `rgba(255,255,255,0.12)`
- `rgba(255,255,255,0.16)`

#### Text tokens

- `#ffffff`
- `#dbeafe`
- `#b9c7e8`
- `#5b6982`
- `#0b1730`

#### State tokens

- `#ef4444`
- `#f87171`
- `#fca5a5`
- `#22c55e`

---

## Recommended Shared Theme Architecture

## Option A — Shared CSS token file

Create a shared file such as:

- `assets/css/theme.css`

and define:

```css
:root {
  --theme-primary: #1D4ED8;
  --theme-primary-dark: #1E40AF;
  --theme-primary-light: #93C5FD;

  --theme-gold: #D4AF37;
  --theme-gold-bright: #FACC15;
  --theme-gold-soft: #ffe08a;

  --theme-bg-main-start: #081120;
  --theme-bg-main-end: #0b1730;
  --theme-surface: #0d1b34;
  --theme-surface-elevated: #12254a;

  --theme-text-primary: #ffffff;
  --theme-text-secondary: #dbeafe;
  --theme-text-muted: #b9c7e8;
  --theme-input-text: #0b1730;
  --theme-input-placeholder: #5b6982;

  --theme-danger: #ef4444;
  --theme-danger-soft: #fca5a5;
  --theme-success: #22c55e;
}
```

Then all page CSS files should consume those variables instead of hardcoding the values.

---

## Option B — Shared component classes

Create reusable component classes for repeated UI patterns:

- hero cards
- gold kickers
- white high-contrast input fields
- premium blue/gold info cards
- shared primary / secondary buttons
- trust / warning / success panels

Examples:

- `.theme-hero-card`
- `.theme-gold-kicker`
- `.theme-input`
- `.theme-panel-premium`
- `.theme-btn-primary`
- `.theme-btn-secondary`

---

## Option C — Hybrid approach recommended

Best approach for CoinRex:

1. **Create one shared token file** for colors, shadows, radii, and spacing.
2. **Create shared utility/component classes** for repeated patterns.
3. Refactor each page gradually to remove local hardcoded colors.

This is the safest path because it:

- avoids breaking all pages at once
- gives a clear migration path
- keeps the new theme consistent
- allows one-file theme changes later

---

## Suggested Next Refactor Step

To convert CoinRex to a true shared theme system, the next step should be:

1. Create `assets/css/theme.css`
2. Move all submit-review reference colors into named variables
3. Load `theme.css` before page-level CSS
4. Replace hardcoded colors page-by-page with variables
5. Extract repeated UI patterns into shared component classes

---

## Implemented Shared Theme Architecture

The following shared theme system has now been implemented in the live CoinRex frontend.

### 1. Shared theme file created

Implemented file:

- `assets/css/theme.css`

This file now acts as the primary shared theme control layer for CoinRex frontend styling.

It currently contains:

- core brand tokens
- text tokens
- border tokens
- input tokens
- semantic/status tokens
- shadow tokens
- shared gradients and panel backgrounds
- shared utility/component theme patterns

### 2. Global theme load path

The shared theme file is now loaded globally from:

- `includes/header.php`

Load order implemented:

1. `assets/css/theme.css`
2. `assets/css/header.css`
3. page-specific CSS files

This ensures page CSS consumes the shared theme instead of redefining the whole visual system.

### 3. Dark default + light-ready structure

Implemented theme scopes:

- `:root, [data-theme="dark"]` → default CoinRex dark theme
- `[data-theme="light"]` → future-ready light theme scaffold

Current default body attribute:

```html
<body data-theme="dark">
```

This means CoinRex is now structurally prepared for future dual-theme support without another major re-architecture.

---

## Implemented Shared Theme Tokens / Patterns

The live shared theme layer now includes patterns such as:

- shared public page backgrounds
- shared public hero backgrounds
- shared premium card/panel surfaces
- shared glass badge/pill surfaces
- shared gold kicker/chip styling
- shared primary button gradients
- shared secondary button surfaces
- shared premium input states
- shared reward/info/warning panel surfaces

Examples of shared implemented theme groups now used in code:

- `--theme-page-bg`
- `--theme-body-bg`
- `--theme-hero-bg`
- `--theme-project-card-bg`
- `--theme-submit-card-bg`
- `--theme-public-page-bg`
- `--theme-public-hero-bg`
- `--theme-public-info-card`
- `--theme-kicker-bg`
- `--theme-kicker-border`
- `--theme-glass-bg`
- `--theme-glass-border`

Implemented shared component-style classes/selectors in `theme.css` include patterns for:

- gradient text
- kicker/badge systems
- glass badges/pills
- primary buttons
- secondary buttons
- premium panels
- theme inputs

---

## Files Migrated to Shared Theme System

### Global/shared layer

- `assets/css/theme.css`
- `includes/header.php`
- `assets/css/header.css`

### Core frontend pages migrated/refactored

- `assets/css/index.css`
- `assets/css/about.css`
- `assets/css/projects.css`
- `assets/css/project-detail.css`
- `assets/css/reviews.css`
- `assets/css/faq.css`
- `assets/css/terms.css`
- `assets/css/blog.css`

### Supporting frontend/account/reward pages migrated/refactored

- `assets/css/auth.css`
- `assets/css/my-reviews.css`
- `assets/css/profile.css`
- `assets/css/reward-pages.css`
- `assets/css/reward-pages-boosthub.css` (partially aligned via shared tokens)
- `assets/css/submit-review.css`

---

## Migration Notes from Implementation

### What was changed in practice

The implementation did not stop at creating tokens.

The actual frontend migration included:

1. centralizing shared tokens in `assets/css/theme.css`
2. removing duplicated root token definitions from key CSS files
3. converting `submit-review.css` into the main shared-theme reference consumer
4. expanding the shared theme into public pages
5. cleaning conflicting/stacked legacy override blocks where they prevented the new visual system from rendering correctly

### Important real-world implementation lesson

In legacy page styles, some pages had multiple visual layers in the same CSS file.

That means future theme work should always check for:

- duplicated selectors later in the file
- conflicting override sections
- older gradient blocks that still render after token migration
- malformed CSS introduced during multi-phase refactors

This is especially important for large page styles such as:

- `terms.css`
- `index.css`
- `projects.css`
- `reviews.css`

---

## Current Recommended Workflow for Future Theme Work

When continuing CoinRex theming in future, follow this order:

1. update shared tokens/patterns in `assets/css/theme.css`
2. verify `includes/header.php` still loads `theme.css` before page CSS
3. update page CSS to consume theme variables and shared patterns
4. check for duplicate selectors or legacy override blocks in the target file
5. visually QA desktop + tablet + mobile for the affected page

---

## Current Theme System Status

CoinRex frontend is now using a hybrid shared theme architecture in production-style code:

### Implemented

- one shared theme file controlling the main visual language
- dark theme as default
- light theme scaffold prepared
- multiple public and supporting frontend pages migrated
- shared token + shared pattern structure established

### Still possible in future

- further extraction of repeated page-specific patterns into explicit reusable classes
- full browser-driven QA polish page-by-page
- stronger standardization of remaining niche page modules
- enabling user-facing dark/light switching logic

---

## Summary

The `submit-review.php` theme currently uses:

- **Royal Blue** as the main brand and structural accent
- **Gold** for highlight, premium emphasis, and icon accent
- **White / Blue-tinted white** for readability on dark surfaces
- **White form fields with dark text** for clear usability
- **Dark blue layered gradients** for premium page depth

This page should be treated as the **reference theme blueprint** for future CoinRex shared frontend theming.
