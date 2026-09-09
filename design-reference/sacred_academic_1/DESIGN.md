---
name: Sacred Academic
colors:
  surface: '#0d1322'
  surface-dim: '#0d1322'
  surface-bright: '#33394a'
  surface-container-lowest: '#080e1d'
  surface-container-low: '#151b2b'
  surface-container: '#191f2f'
  surface-container-high: '#242a3a'
  surface-container-highest: '#2f3445'
  on-surface: '#dde2f8'
  on-surface-variant: '#dbc0c4'
  inverse-surface: '#dde2f8'
  inverse-on-surface: '#2a3040'
  outline: '#a38b8e'
  outline-variant: '#554245'
  surface-tint: '#ffb1c0'
  primary: '#ffb1c0'
  on-primary: '#63082a'
  primary-container: '#7b1e3b'
  on-primary-container: '#ff8ca6'
  inverse-primary: '#a03b57'
  secondary: '#e9c16d'
  on-secondary: '#402d00'
  secondary-container: '#6a4e00'
  on-secondary-container: '#e9c16d'
  tertiary: '#c1c7cf'
  on-tertiary: '#2b3137'
  tertiary-container: '#3c4349'
  on-tertiary-container: '#a9afb7'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffd9df'
  primary-fixed-dim: '#ffb1c0'
  on-primary-fixed: '#3f0017'
  on-primary-fixed-variant: '#81233f'
  secondary-fixed: '#ffdea0'
  secondary-fixed-dim: '#e9c16d'
  on-secondary-fixed: '#261a00'
  on-secondary-fixed-variant: '#5c4300'
  tertiary-fixed: '#dde3eb'
  tertiary-fixed-dim: '#c1c7cf'
  on-tertiary-fixed: '#161c22'
  on-tertiary-fixed-variant: '#41474e'
  background: '#0d1322'
  on-background: '#dde2f8'
  surface-variant: '#2f3445'
typography:
  display-lg:
    fontFamily: EB Garamond
    fontSize: 56px
    fontWeight: '600'
    lineHeight: 64px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: EB Garamond
    fontSize: 32px
    fontWeight: '500'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: EB Garamond
    fontSize: 28px
    fontWeight: '500'
    lineHeight: 36px
  title-md:
    fontFamily: Source Serif 4
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Source Serif 4
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Source Serif 4
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  base: 8px
  container-padding: 24px
  gutter: 16px
  margin-mobile: 16px
  margin-desktop: 64px
---

## Brand & Style
The brand personality is authoritative, reverent, and deeply intellectual, evoking the atmosphere of a nocturnal library or an ancient observatory. It targets a scholarly audience that values quiet focus and traditional prestige within a digital landscape.

The design style is **Modern Corporate with a High-Contrast Academic finish**. It blends the structure of an institutional design system with a sophisticated color palette. The aesthetic response should feel "hushed"—stable, polished, and premium—relying on deep navy surfaces and gold accents to signal quality and timelessness.

## Colors
The palette is rooted in a "Nocturnal Scholastic" theme. The **Background (#0B1120)** is a deep, academic navy that provides a calm, low-strain canvas. 

- **Primary Burgundy (#7B1E3B):** Used sparingly as a "seal of quality" for high-level brand moments, active states of critical importance, or heritage elements.
- **Secondary Gold (#FFD57F):** The primary interactive driver in dark mode. It provides high-contrast legibility against the navy background for calls to action, highlights, and icons.
- **Surface Hierarchy:** Depth is created through ascending shades of blue. Lower elevations use darker tints, while "Surface High" containers (like modals or floating cards) use lighter slate-blues to draw the eye forward.

## Typography
Typography follows a classical editorial hierarchy. **EB Garamond** is reserved for high-level displays to provide a sense of history and prestige. **Source Serif 4** handles the bulk of reading tasks, offering superior legibility in dark mode with its balanced x-height.

**Inter** is utilized for functional UI elements (labels, buttons, metadata) to provide a modern, utilitarian counterpoint to the serif fonts, ensuring the interface feels like a sophisticated tool rather than just a static document. Use wide letter-spacing on uppercase labels to enhance the "archival" feel.

## Layout & Spacing
The layout utilizes a **fixed grid** approach on desktop (12 columns) to maintain rigorous, symmetrical compositions reminiscent of formal manuscripts. 

- **Rhythm:** A strict 8px baseline grid ensures vertical harmony.
- **Margins:** Generous outer margins (64px on desktop) create a "frame" effect, focusing the user's attention on the central content.
- **Mobile:** On mobile, margins compress to 16px, and the layout collapses to a single-column stack, maintaining white space (or "navy space") between sections to prevent visual clutter.

## Elevation & Depth
In this dark, academic environment, depth is achieved through **Tonal Layers** rather than heavy shadows. 

1. **Base Layer:** The deep navy background (#0B1120).
2. **Intermediate Layers:** Surface containers (Cards, Sidebars) use slightly lighter values (#0F172A or #1E293B).
3. **Ghost Outlines:** Elements are defined by subtle, low-contrast 1px borders in a muted slate (#334155) to give them structure without breaking the reverent, dark atmosphere.
4. **Focused Highlights:** Use the Secondary Gold for thin top-borders on "active" or "featured" cards to denote high hierarchy.

## Shapes
Shapes are disciplined and conservative. A **Soft (0.25rem)** corner radius is the standard, providing just enough approachability to feel modern while retaining the "sharp" precision of a printed academic journal. 

Avoid large, pill-shaped elements; instead, use rectangular buttons and containers with micro-radii to maintain the structured, institutional character of the design system.

## Components
- **Buttons:** Primary buttons use the **Secondary Gold (#FFD57F)** with dark navy text for maximum visibility. Secondary buttons should be "Ghost" style—transparent with a Primary Burgundy border and burgundy text.
- **Cards:** Use "Surface Low" background colors with no shadow, defined by a 1px "Surface High" border. Headlines within cards should use EB Garamond.
- **Input Fields:** Dark navy backgrounds with a subtle bottom-border only, mimicking a signature line. Focus state shifts the border to Gold.
- **Lists:** Separated by thin, low-opacity slate lines. Use the Gold color for bullet points or numbering to add a rhythmic "flicker" of light to text-heavy areas.
- **Chips/Tags:** Small, rectangular with 2px radius. Use Burgundy background with white text for "Verified" or "Official" tags; use Navy background with Gold text for general categories.