---
name: Velvet Glass Executive Dashboard
colors:
  surface: '#1f0e19'
  surface-dim: '#1f0e19'
  surface-bright: '#48333f'
  surface-container-lowest: '#190913'
  surface-container-low: '#281621'
  surface-container: '#2c1a25'
  surface-container-high: '#382430'
  surface-container-highest: '#432f3b'
  on-surface: '#fadaea'
  on-surface-variant: '#dac1bc'
  inverse-surface: '#fadaea'
  inverse-on-surface: '#3f2a36'
  outline: '#a28c88'
  outline-variant: '#55433f'
  surface-tint: '#ffb4a6'
  primary: '#ffbbaf'
  on-primary: '#5b1a10'
  primary-container: '#fa9583'
  on-primary-container: '#742c20'
  inverse-primary: '#974638'
  secondary: '#ffb0cc'
  on-secondary: '#640038'
  secondary-container: '#841b50'
  on-secondary-container: '#ff94be'
  tertiary: '#e9c3bc'
  on-tertiary: '#422a26'
  tertiary-container: '#cca8a2'
  on-tertiary-container: '#573d38'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdad4'
  primary-fixed-dim: '#ffb4a6'
  on-primary-fixed: '#3e0501'
  on-primary-fixed-variant: '#792f23'
  secondary-fixed: '#ffd9e4'
  secondary-fixed-dim: '#ffb0cc'
  on-secondary-fixed: '#3e0021'
  on-secondary-fixed-variant: '#841b50'
  tertiary-fixed: '#ffdad4'
  tertiary-fixed-dim: '#e3beb7'
  on-tertiary-fixed: '#2b1612'
  on-tertiary-fixed-variant: '#5b403b'
  background: '#1f0e19'
  on-background: '#fadaea'
  surface-variant: '#432f3b'
typography:
  display-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.03em
  display-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: 0em
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 18px
    letterSpacing: 0.01em
  label-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.5rem
---

## Brand & Style

This design system expresses a luxurious, high-focus administrative workspace. Combining the tactile elegance of deep wine and burgundy shades with the warm luminous quality of ripe peach accents, the visual direction merges high-end editorial restraint with advanced glassmorphism.

### Key Tenets
- **Crystalline Depth:** Content resides on semi-transparent, backdrop-filtered planes rather than flat opaque blocks. This creates a spatial canvas that feels weightless and multi-layered.
- **Warm Executive Warmth:** Replaces sterile enterprise slate grays with deep plum-tinted darks and gentle peach highlights, instilling confidence, prestige, and quiet luxury.
- **Subtlety & Precision:** Visual division is created through specular border highlights and soft tonal shifts rather than heavy opaque strokes or saturated containers.

## Colors

The palette leverages a dark burgundy canvas to make glass reflections and peach accents perform with maximum luminescence and contrast.

- **Primary (`#FA9583`):** Radiant peach. Used for active states, key interactive indicators, focused chart highlights, and primary call-to-actions.
- **Secondary (`#720942`):** Vivid wine. Serves as secondary button fills, luminous back-glows, active container backings, and subtle gradients.
- **Tertiary (`#FCD5CE`):** Soft peach mist. Deployed for subtle chip highlights, secondary metric tags, and high-legibility secondary text accents.
- **Neutral Deep (`#1C0B16`):** Deep plum-black base background. Replaces standard grays to create an ambient, nocturnal foundation that harmonizes with the wine palette.
- **Glass Surfaces:**
  - Base Card: `rgba(88, 24, 69, 0.22)` with `backdrop-filter: blur(20px)`
  - Elevated Popover/Flyout: `rgba(114, 9, 66, 0.35)` with `backdrop-filter: blur(28px)`
  - Specular Edge Highlight: Linear gradient from `rgba(255, 181, 167, 0.24)` at the top edge to `rgba(114, 9, 66, 0.08)` at the bottom edge.
- **Text & Content Hierarchy:**
  - High Contrast (Headlines/Metrics): `#FFF5F2`
  - Medium Contrast (Body/Labels): `rgba(252, 213, 206, 0.76)`
  - Low Contrast (Muted metadata): `rgba(252, 213, 206, 0.44)`

## Typography

Plus Jakarta Sans is utilized across all tiers. Its geometry provides high technical legibility for data-dense dashboards while retaining subtle friendly curves that complement the peach tone accents.

- **KPIs & Big Numbers:** Rendered using `display-lg` with tight tracking (`-0.03em`) and high-contrast `#FFF5F2` to guarantee instant scanability against frosted backdrops.
- **Hierarchy Rules:** Headline levels use medium-to-bold weights with zero decoration. Labels and table headers use `label-md` or `label-sm` in uppercase tracking (`+0.04em`) with muted peach opacity (`rgba(252, 213, 206, 0.7)`).

## Layout & Spacing

The dashboard employs a fluid 12-column grid system paired with strict inner card padding to ensure glass cards breathe comfortably.

### Breakpoint Structure
- **Desktop (1200px+):** 12 columns, 24px (`1.5rem`) gutters, 32px (`2rem`) canvas margins. Permanent floating sidebar navigation.
- **Tablet (768px - 1199px):** 8 columns, 16px (`1rem`) gutters, 24px (`1.5rem`) margins. Collapsed icon-rail navigation.
- **Mobile (< 768px):** 4 columns, 16px (`1rem`) gutters, 16px (`1rem`) margins. Full-width stacked glass tiles with bottom tab navigation.

### Rhythm Principles
- Metrics and executive summaries adopt generous `space-lg` internal card padding to prevent visual crowding across translucent surfaces.
- Element groupings inside a card are spaced with `space-sm` or `space-md` increments.

## Elevation & Depth

Depth is established strictly through simulated glass refraction, layered blur intensities, and atmospheric glow rather than standard drop shadows.

### Atmospheric Surface Stack
1. **Canvas Level (0):** Deepest background tone (`#1C0B16`) layered with two soft ambient background light orbs (peach `#FA9583` at 12% opacity and wine `#720942` at 25% opacity) that wander behind the UI.
2. **Standard Layer (1 - Cards, Panels):** 
   - Background: `rgba(88, 24, 69, 0.2)`
   - Backdrop filter: `blur(20px) saturate(140%)`
   - Border: `1px solid rgba(255, 181, 167, 0.16)`
   - Shadow: `0 8px 32px 0 rgba(14, 3, 11, 0.37)`
3. **Floating Layer (2 - Dropdowns, Hover States, Floating Toolbars):**
   - Background: `rgba(114, 9, 66, 0.4)`
   - Backdrop filter: `blur(28px) saturate(160%)`
   - Border: `1px solid rgba(255, 181, 167, 0.3)`
   - Shadow: `0 12px 40px 0 rgba(14, 3, 11, 0.5), inset 0 1px 0 0 rgba(255, 255, 255, 0.15)`
4. **Modal Layer (3 - Critical Overlays):**
   - Backdrop: `rgba(28, 11, 22, 0.7)` with `backdrop-filter: blur(12px)`
   - Panel: `rgba(88, 24, 69, 0.65)` with `1px solid rgba(250, 149, 131, 0.35)`
   - Shadow: `0 24px 64px 0 rgba(0, 0, 0, 0.6), 0 0 24px rgba(250, 149, 131, 0.15)`

## Shapes

The design uses balanced, modern curvature (Level 2). This ensures glass elements exhibit soft, organic corners where specular border highlights can curve elegantly without feeling overly circular or childlike.

- **Standard Elements (Buttons, Inputs, Badges):** `rounded` (8px / `0.5rem`).
- **Cards, Panels, Floating Sheets:** `rounded-lg` (16px / `1rem`).
- **Modals & Major Containers:** `rounded-xl` (24px / `1.5rem`).
- **Interactive Pills / Status Badges:** Fully circular (`rounded-full` / `9999px`).

## Components

### Buttons
- **Primary:** Gradient fill `linear-gradient(135deg, #FA9583 0%, #E76F51 100%)`, text color `#1C0B16` (bold weight), border `none`. Hover emits a soft glow: `box-shadow: 0 4px 20px rgba(250, 149, 131, 0.35)`.
- **Secondary (Glass):** Fill `rgba(114, 9, 66, 0.3)`, text `#FFF5F2`, border `1px solid rgba(255, 181, 167, 0.2)`. On hover, background shifts to `rgba(114, 9, 66, 0.5)` with border highlight `rgba(255, 181, 167, 0.4)`.
- **Ghost:** Transparent background, text `rgba(252, 213, 206, 0.8)`. Hover fills with `rgba(255, 255, 255, 0.05)`.

### Cards & Metric Containers
- Minimalist translucent containers with a top-to-bottom subtle gradient border.
- Metric values use `display-lg` with an optional subtle gradient text fill (`#FFF` to `#FFB5A7`).
- Subtitles and trends are nested directly below with minimum 12px separation. Trend indicators use green/red tinted with warm undertones to remain cohesive with the palette.

### Input Fields & Search Bars
- Background: `rgba(28, 11, 22, 0.45)`.
- Border: `1px solid rgba(255, 181, 167, 0.15)`.
- Text: `#FFF5F2` with placeholder in `rgba(252, 213, 206, 0.35)`.
- Focus State: Border transitions to `#FA9583`, accented by an outer glow `0 0 0 3px rgba(250, 149, 131, 0.2)`.

### Chips & Badges
- **Status Badges:** Compact pill shapes with subtle fills: `rgba(250, 149, 131, 0.12)`, text `#FFB5A7`, border `1px solid rgba(250, 149, 131, 0.24)`. Contains a 6px glowing indicator dot.
- **Filter Chips:** Frosted `rgba(88, 24, 69, 0.25)` surface with label in `rgba(252, 213, 206, 0.9)`. Active state fills with `rgba(250, 149, 131, 0.2)` and a solid `#FA9583` edge.

### Lists & Data Tables
- Header row uses uppercase `label-sm` text with a subtle bottom divider `1px solid rgba(255, 181, 167, 0.1)`.
- Table rows feature hover transition to `rgba(255, 181, 167, 0.04)`.
- Alternating striping is avoided to preserve translucency clarity.

### Checkboxes & Radio Buttons
- Unchecked: `1px solid rgba(255, 181, 167, 0.3)` on a `rgba(28, 11, 22, 0.5)` base.
- Checked: `#FA9583` solid fill with dark burgundy checkmark/inner-dot (`#1C0B16`).