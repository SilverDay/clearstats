# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** Skyggn
**Generated:** 2026-08-18 08:18:46
**Category:** B2B Service

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#0F172A` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#334155` | `--color-secondary` |
| Accent/CTA | `#0369A1` | `--color-accent` |
| Background | `#F8FAFC` | `--color-background` |
| Foreground | `#020617` | `--color-foreground` |
| Muted | `#E8ECF1` | `--color-muted` |
| Border | `#E2E8F0` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| Ring | `#0F172A` | `--color-ring` |

**Color Notes:** Professional navy + blue CTA

### Typography

- **Heading Font:** Inter
- **Body Font:** Inter
- **Mood:** minimal, clean, swiss, functional, neutral, professional
- **Google Fonts:** [Inter + Inter](https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap)

**CSS Import:**
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
```

### Spacing Variables

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` / `0.25rem` | Tight gaps |
| `--space-sm` | `8px` / `0.5rem` | Icon gaps, inline spacing |
| `--space-md` | `16px` / `1rem` | Standard padding |
| `--space-lg` | `24px` / `1.5rem` | Section padding |
| `--space-xl` | `32px` / `2rem` | Large gaps |
| `--space-2xl` | `48px` / `3rem` | Section margins |
| `--space-3xl` | `64px` / `4rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: #0369A1;
  color: white;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* Secondary Button */
.btn-secondary {
  background: transparent;
  color: #0F172A;
  border: 2px solid #0F172A;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}
```

### Cards

```css
.card {
  background: #F8FAFC;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: all 200ms ease;
  cursor: pointer;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### Inputs

```css
.input {
  padding: 12px 16px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 200ms ease;
}

.input:focus {
  border-color: #0F172A;
  outline: none;
  box-shadow: 0 0 0 3px #0F172A20;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Trust & Authority (base tone only — Skyggn is a working tool, not a marketing site)

**Keywords:** Calm, precise, professional, quiet confidence. Trust is earned through clarity and restraint, not badges or certificates — this is software security engineers use for hours at a stretch, not a page they're persuaded by once.

**Best For:** Enterprise software, compliance/audit tooling, developer-adjacent professional tools.

**Key Effects:** None decorative. Motion exists only to explain state change (see Anti-Patterns). No badge carousels, no marketing embellishment — those keywords from the base style search describe landing-page trust signals, which don't apply to the in-app product.

> The generator's "Minimal Single Column" pattern (hero/CTA/footer) is a **marketing landing-page** pattern and does not apply to Skyggn's actual screens — see **Page Inventory & Layout Shell** below for the real information architecture. It's the right pattern *only* for a future public marketing/pricing page, if one is ever built outside the app shell.

---

## Dark Mode Tokens

Style search confirms full light + dark support. Same semantic roles, dark values are **desaturated/lighter tonal variants**, never a raw inversion (`ux` rule `color-dark-mode`). Default to OS preference (`prefers-color-scheme`); a manual toggle can be layered on later using the same tokens under a `.dark` class.

| Role | Light | Dark | CSS Variable |
|------|-------|------|--------------|
| Background | `#F8FAFC` | `#0B1220` | `--color-background` |
| Surface/Card | `#FFFFFF` | `#111827` | `--color-surface` |
| Foreground (text) | `#0F172A` | `#E2E8F0` | `--color-foreground` |
| Muted foreground | `#64748B` | `#94A3B8` | `--color-muted-foreground` |
| Muted surface | `#E8ECF1` | `#1E293B` | `--color-muted` |
| Border | `#E2E8F0` | `#27324066` | `--color-border` |
| Primary | `#0F172A` | `#E2E8F0` | `--color-primary` |
| On Primary | `#FFFFFF` | `#0B1220` | `--color-on-primary` |
| Accent (links, focus, active nav) | `#0369A1` | `#38BDF8` | `--color-accent` |
| Destructive | `#DC2626` | `#F87171` | `--color-destructive` |
| Ring (focus) | `#0F172A` | `#38BDF8` | `--color-ring` |

Both modes must independently pass 4.5:1 body text / 3:1 large text (WCAG AA) — verify separately, dark is not "light inverted and assumed fine" (`ux` rule `color-accessible-pairs`).

---

## Semantic Status Colors (Skyggn-specific)

The generic palette above covers chrome (nav, buttons, cards). Findings, risks, and mitigations need their own **fixed, consistent** color coding — these are load-bearing for scanning a dense findings table at speed, so they must never be repurposed for anything else in the UI.

### Severity (Finding / Risk)

Color alone never carries meaning (`ux` rule `color-not-only`) — every severity badge pairs its color with a text label and, in dense tables, an icon.

| Severity | Light bg / text | Dark bg / text | Notes |
|---|---|---|---|
| Critical | `#FEE2E2` / `#B91C1C` | `#7F1D1D66` / `#FCA5A5` | Reserve pure red for this tier only |
| High | `#FFEDD5` / `#C2410C` | `#7C2D1266` / `#FDBA74` | Orange, not red — keeps Critical visually singular |
| Medium | `#FEF3C7` / `#B45309` | `#78350F66` / `#FCD34D` | Amber |
| Low | `#E0E7FF` / `#4338CA` | `#312E8166` / `#A5B4FC` | Indigo, deliberately calm — not on the red/orange/amber ramp |
| Informational | `#F1F5F9` / `#475569` | `#1E293B` / `#94A3B8` | Neutral slate |

### Finding / Risk status (independent axis from severity — a Critical finding can be Mitigated)

| Status | Color | Notes |
|---|---|---|
| Open | severity color above | Status defers to severity color while unresolved |
| Confirmed | severity color above | Same |
| Mitigated | `#059669` (emerald) | Distinct from "resolved" isn't needed as a separate hue — weight/icon differentiates |
| Risk Accepted | `#7C3AED` (violet) | Deliberately *not* green — accepted ≠ fixed, must read as its own decision |
| False Positive | `#94A3B8` (slate, reduced opacity) | Visually recedes — dismissed items shouldn't compete with active ones |

### STRIDE category tags

Six fixed hues, categorical (not sequential/diverging) since STRIDE categories have no inherent order. Chosen for AA contrast as badge text-on-tint and to stay distinguishable for the two common colorblindness types (deuteranopia/protanopia) — Spoofing/Elevation and Tampering/DoS are the pairs most likely to be confused on a red-green axis, so they're placed on opposite sides of the hue wheel.

| Category | Hex | Category | Hex |
|---|---|---|---|
| Spoofing | `#7C3AED` violet | Information Disclosure | `#0369A1` blue |
| Tampering | `#C2410C` orange | Denial of Service | `#BE123C` rose |
| Repudiation | `#0D9488` teal | Elevation of Privilege | `#A21CAF` fuchsia |

---

## Typography Scale

Base font: **Inter** (per typography search — dashboards/admin panels/enterprise apps, WCAG-friendly at small sizes, excellent Tailwind support). Add **JetBrains Mono** as a second family strictly for technical/data values: finding fingerprints, rule keys, IDs, JSON/schema snippets — never for UI labels or body copy.

> Current scaffold (`resources/css/app.css`) still has Laravel's default "Instrument Sans" — needs updating to Inter + JetBrains Mono when this system is implemented (see Open Items).

| Token | Size / Line-height | Weight | Use |
|---|---|---|---|
| `text-display` | 32px / 1.2 | 700 | Page-level titles only (rare — this is a dashboard, not a landing page) |
| `text-h1` | 24px / 1.3 | 600 | Section headers |
| `text-h2` | 18px / 1.4 | 600 | Card/panel headers |
| `text-body` | 16px / 1.5 | 400 | Default UI text, form inputs |
| `text-sm` | 14px / 1.5 | 400 | Secondary text, table body (dense tables may use this as the default row size) |
| `text-xs` | 12px / 1.4 | 500 | Labels, badges, table headers (uppercase, `+0.02em` tracking) |
| `font-mono` | matches surrounding size | 400–500 | Fingerprints, IDs, rule keys, code/JSON |

---

## Page Inventory & Layout Shell

Real information architecture, replacing the generator's marketing pattern — derived from CLAUDE.md's actual Inertia/JSON-API surface.

**Shell:** persistent left sidebar (240px, collapsible to icon-rail), not the top-nav bar Sprint 1 shipped as a placeholder. Matches the Data-Dense Dashboard reference pattern and scales better once Projects/Threat Models/Findings/Risks/Reports all need top-level nav slots — a top bar runs out of room fast. Top bar is retained only for: current tenant switcher, user menu, and (for super admins) the "entered tenant" banner required by spec §14.12.

| Screen | Route type | Density | Notes |
|---|---|---|---|
| Login / MFA / password reset | Inertia | Spacious (current `GuestLayout`, keep as-is) | Only screens where the marketing-adjacent centered-card treatment is correct |
| Dashboard | Inertia | Medium | KPI cards (open findings, at-risk projects) + recent activity |
| Super Admin \> Tenants | Inertia | Dense (data table) | |
| Tenant \> Users | Inertia | Dense (data table) | |
| Projects / Threat Models list | Inertia (Sprint 2) | Dense (data table) | |
| Findings / Risk Register | Inertia (Sprint 2+) | Dense (data table), severity-first sort | The highest-stakes screen in the product — legibility at speed matters most here |
| Visual model workspace (canvas) | JSON API + Vue Flow (built Sprint 3) | N/A — see Canvas below | |

---

## Data Table Spec (dense screens)

Per Data-Dense Dashboard reference pattern, adapted:

```css
--table-row-height: 36px;
--table-header-bg: var(--color-muted);
--table-font-size: 14px; /* text-sm */
--table-header-font-size: 12px; /* text-xs, uppercase, tracked */
```

- Sticky header on scroll.
- Row hover uses `--color-muted` background, never a shadow/lift (`ux` rule `layout-shift-avoid` — no transform on table rows).
- Sortable columns show a persistent (not hover-only) sort-direction affordance and set `aria-sort`.
- Severity/status always renders as the colored badge (label text always visible — never a bare color dot).
- Zero-state: "No findings match these filters" + a clear reset action, not a blank table.
- Row click opens detail (whole row is the tap target on touch, ≥44px effective height there even though visual row height is 36px on desktop-dense).

---

## Canvas / Diagram Editor (Vue Flow) — built Sprint 3

The one surface with no landing-page or dashboard precedent to borrow from — specified from the data model (`ModelElement.element_type`, `DataFlow`, `TrustBoundary`) rather than the generator.

- **Nodes:** white/surface card, 2px border in a per-`element_type` accent color (not severity colors — those are reserved for findings/risk and must not be reused here), icon + label. Selected state: `--color-ring` outline, not a fill change (keeps the node's own color legible while selected). *Shipped without per-type icons (label only) — add icons as a follow-up polish pass, not blocking.*
- **Trust boundaries:** dashed 2px border container, label pinned top-left, no fill (or near-transparent tint) so nested elements stay legible. *Shipped as a differently-styled node, not a true Vue Flow parent/container — visual containment (dragging elements to auto-join a boundary) isn't implemented; "group nodes" was explicitly out of Sprint 3's suggested scope (spec §33).*
- **Data flows (edges):** solid line, arrowhead indicating direction, protocol/label rendered on the edge itself (not hover-only — this is the kind of info a reviewer needs at a glance).
- **Findings overlay:** a node/flow with open findings gets a small severity-colored indicator badge at its corner — reuses the severity palette above, the only place canvas and findings color systems intentionally intersect.
- Canvas is a case where `--space-xs`/`--space-sm` (4–8px) govern node internal padding — it's the one part of the product closer to "dense" than even the data tables.

---

## Anti-Patterns (Do NOT Use)

- ❌ Playful design
- ❌ Hidden credentials
- ❌ AI purple/pink gradients

### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile

---

## Implementation Status

Approved and applied to the codebase (2026-08-18).

- [x] `resources/css/app.css`: Instrument Sans → Inter + JetBrains Mono (Google Fonts `@import`), full token set added as plain CSS custom properties (`:root` / `prefers-color-scheme` / `[data-theme]`), `@theme inline` maps them to Tailwind utilities (`bg-primary`, `text-destructive`, `border-border`, etc.)
- [x] `vite.config.js`: removed the now-unused Instrument Sans bunny-fonts plugin config
- [x] `AppLayout.vue`: top-nav → persistent left sidebar shell (240px, matches the style guide artifact)
- [x] `GuestLayout.vue` + all Sprint 1 auth pages (`Login`, `ForgotPassword`, `ResetPassword`, `TwoFactorSetup`, `TwoFactorChallenge`, `TwoFactorRecoveryCodes`): restyled to tokens, added visible focus rings (`focus:ring-ring`) and `cursor-pointer` on all interactive elements — neither existed before
- [x] `Admin/Tenants/Index.vue`, `Tenant/Users/Index.vue`: restyled tables to the dense-table spec (36px-ish rows, uppercase tracked headers, background-only hover)
- [x] `Components/SeverityBadge.vue`, `StatusBadge.vue`, `StrideTag.vue` — typed against `types/threat-model.d.ts`, ready for Sprint 2's Findings list
- [ ] **Deliberately not built:** a generic `DataTable` component. Only two simple tables exist so far and Sprint 2's Findings columns (sorting, filters) aren't defined yet — building that abstraction now would be guessing at a shape we don't know. Build it when Findings actually needs it, against real requirements.
