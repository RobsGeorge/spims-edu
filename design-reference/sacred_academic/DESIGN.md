---
name: Sacred Academic
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf6'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e6eeff'
  surface-container-high: '#dde9ff'
  surface-container-highest: '#d3e3ff'
  on-surface: '#0b1c30'
  on-surface-variant: '#554245'
  inverse-surface: '#213146'
  inverse-on-surface: '#ebf1ff'
  outline: '#887175'
  outline-variant: '#dbc0c4'
  surface-tint: '#a03b57'
  primary: '#380014'
  on-primary: '#ffffff'
  primary-container: '#5d0326'
  on-primary-container: '#e26f8b'
  inverse-primary: '#ffb1c1'
  secondary: '#785a02'
  on-secondary: '#ffffff'
  secondary-container: '#ffd578'
  on-secondary-container: '#795a03'
  tertiary: '#001d09'
  on-tertiary: '#ffffff'
  tertiary-container: '#003415'
  on-tertiary-container: '#6d9f76'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffd9df'
  primary-fixed-dim: '#d4af37'
  on-primary-fixed: '#3f0017'
  on-primary-fixed-variant: '#812340'
  secondary-fixed: '#ffdf9c'
  secondary-fixed-dim: '#eac166'
  on-secondary-fixed: '#251a00'
  on-secondary-fixed-variant: '#5b4300'
  tertiary-fixed: '#bbefc1'
  tertiary-fixed-dim: '#9fd3a6'
  on-tertiary-fixed: '#00210b'
  on-tertiary-fixed-variant: '#21502e'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e3ff'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1'
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.02em
rounded:
  sm: 0.5rem
  DEFAULT: 1rem
  md: 1.5rem
  lg: 2rem
  xl: 3rem
  full: 9999px
spacing:
  xs: 0.25rem
  sm: 0.5rem
  md: 1rem
  lg: 1.5rem
  xl: 2rem
  2xl: 3rem
  gutter: 24px
  sidebar-width: 280px
  container-max: 1440px
---

## Brand & Style
Sacred Academic is a design system that blends the prestigious, institutional feel of higher education with a modern, clean digital experience. The brand personality is **authoritative yet accessible**, evoking feelings of trust, tradition, and clarity. 

The visual style is **Corporate / Modern** with subtle **Minimalist** influences. It utilizes a refined color palette, significant whitespace to reduce cognitive load, and generous corner radii to soften the academic structure. The system is designed to handle multi-lingual requirements (specifically Arabic RTL support) with equal visual weight and sophistication, ensuring a seamless cross-cultural user experience.

## Colors
The palette is rooted in "Sacred" tones: a deep, scholarly Burgundy (`primary`) and a refined Academic Gold (`secondary`). 

- **Primary (#5d0326):** Used for brand identity, primary actions, and major headings.
- **Secondary (#785a02):** Used for accents, secondary highlights, and progress indicators.
- **Backgrounds:** The system uses a "Cool Tint" strategy for backgrounds (`#f8f9ff`), providing a crisp contrast to the warm primary tones.
- **Dark Mode:** In dark mode, the primary color shifts toward the Gold (`primary-fixed-dim`) to maintain legibility and a "premium" feel against dark surfaces.
- **Functional:** Standard semantic colors (Success, Danger, Info) are used for status communication but are slightly desaturated to fit the academic aesthetic.

## Typography
The system relies on **Inter** for all Latin-based text to ensure maximum legibility and a modern, utilitarian feel. For Arabic layouts, **IBM Plex Sans Arabic** is used to maintain a consistent professional weight and clear baseline.

- **Scale:** A tight scale that prioritizes clear hierarchy. Display styles use tighter letter spacing and heavier weights to command attention.
- **Line Height:** Body text uses a generous 1.6x line height to support long-form reading common in academic contexts.
- **Weight:** Semi-bold (600) is preferred for UI headers to create a strong anchor without the aggression of a full bold.

## Layout & Spacing
The system utilizes a **Fluid Grid** with fixed maximum constraints. 

- **Containers:** Content is typically housed in cards with `1.5rem` (lg) internal padding. 
- **Rhythm:** A 4px/8px baseline grid is used. Spacing between sections (e.g., between cards) should default to `2rem` (xl) on desktop and `1rem` (md) on mobile.
- **RTL:** Layouts must be fully mirrored. Icons that imply direction (arrows, progress bars) must flip, while brand-mark icons and static symbols (language, dark_mode) remain constant.

## Elevation & Depth
Depth is achieved through **Tonal Layers** and **Soft Shadows**, moving away from harsh lines.

- **Surface Levels:** The background uses `surface-container-low`, while primary content areas use `surface-container-lowest` (pure white in light mode).
- **Shadows:** A custom `shadow-soft` (`0px 4px 20px rgba(0,0,0,0.05)`) is used for cards and floating elements to provide a gentle lift. In dark mode, the shadow opacity increases and the color tints toward the background color (`rgba(0,0,0,0.3)`).
- **Borders:** Thin, low-contrast borders (`outline-variant/30`) are used even on shadowed elements to define boundaries without adding visual noise.

## Shapes
The system uses an **Extra-Rounded** shape language to create a welcoming, modern feel.

- **Large Containers:** Main screen wrappers and layout blocks use a `2rem` (32px) radius.
- **Standard Components:** Buttons, input fields, and inner cards use a `1rem` (16px) radius.
- **Small Elements:** Tooltips and badges use a `0.5rem` (8px) radius.

## Components
- **Buttons:** Primary buttons are pill-shaped with `primary` background and `on-primary` text. They feature a slight lift on hover and a subtle scale-down (95%) on active states.
- **Input Fields:** Use a `1rem` radius. Borders use `outline-variant` by default, shifting to `primary` with a 1px ring on focus. Backgrounds should be slightly darker than the card they sit on.
- **Cards:** White or very light gray backgrounds with `shadow-soft`. Headlines within cards should use `headline-md` or `headline-sm`.
- **TopAppBar:** Transparent or surface-colored with minimal elements. Icons are contained in circular hover states.
- **Chips/Badges:** Small, high-contrast labels used for status or mode indicators (e.g., the "Light Mode" tag), typically anchored to the corner of a parent container.