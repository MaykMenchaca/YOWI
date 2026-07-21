---
name: Apex Athletic
colors:
  surface: '#f9f9ff'
  surface-dim: '#c6dbff'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff3ff'
  surface-container: '#e6eeff'
  surface-container-high: '#dde9ff'
  surface-container-highest: '#d4e3ff'
  on-surface: '#001c3a'
  on-surface-variant: '#424751'
  inverse-surface: '#0c3158'
  inverse-on-surface: '#ebf1ff'
  outline: '#727782'
  outline-variant: '#c2c6d2'
  surface-tint: '#1960a6'
  primary: '#004782'
  on-primary: '#ffffff'
  primary-container: '#185fa5'
  on-primary-container: '#c1d9ff'
  inverse-primary: '#a4c9ff'
  secondary: '#0060a8'
  on-secondary: '#ffffff'
  secondary-container: '#5da9fe'
  on-secondary-container: '#003d6d'
  tertiary: '#005222'
  on-tertiary: '#ffffff'
  tertiary-container: '#006d2f'
  on-tertiary-container: '#54f483'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d4e3ff'
  primary-fixed-dim: '#a4c9ff'
  on-primary-fixed: '#001c39'
  on-primary-fixed-variant: '#004883'
  secondary-fixed: '#d2e4ff'
  secondary-fixed-dim: '#a1c9ff'
  on-secondary-fixed: '#001c38'
  on-secondary-fixed-variant: '#004880'
  tertiary-fixed: '#66ff8e'
  tertiary-fixed-dim: '#3de273'
  on-tertiary-fixed: '#002109'
  on-tertiary-fixed-variant: '#005322'
  background: '#f9f9ff'
  on-background: '#001c3a'
  surface-variant: '#d4e3ff'
typography:
  headline-xl:
    fontFamily: Montserrat
    fontSize: 48px
    fontWeight: '800'
    lineHeight: '1.1'
    letterSpacing: 0.02em
  headline-lg:
    fontFamily: Montserrat
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: 0.02em
  headline-md:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-sm:
    fontFamily: Montserrat
    fontSize: 18px
    fontWeight: '700'
    lineHeight: '1.2'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-bold:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  price-display:
    fontFamily: Montserrat
    fontSize: 22px
    fontWeight: '700'
    lineHeight: '1'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
  section-padding: 80px
---

## Brand & Style
The design system is engineered for a premium sports nutrition audience that values performance, purity, and scientific rigor. The aesthetic leans into a high-performance **Corporate Modern** style with **Athletic Minimalism**, utilizing a restricted palette of technical blues and high-contrast typography to evoke a sense of energy and trust. 

The interface should feel breathable and structured, avoiding unnecessary clutter to focus on product efficacy and transparency. Visual interest is generated through precise grid alignments, crisp borders, and a sophisticated interplay between light backgrounds and deep navy accents.

## Colors
This design system utilizes a tiered blue palette to establish a technical, "laboratory-tested" feel. 
- **Primary Cobalt (#185FA5):** Reserved for core brand elements, headers, and primary CTAs. It represents authority and stability.
- **Accent Bright Blue (#378ADD):** Used for dynamic data points, such as pricing, active states, and highlights, providing a clear path for the eye.
- **Neutral Navy (#042C53):** Applied to primary text and the footer to ground the design with a sense of "elite" performance.
- **Success Green (#25D366):** Specifically designated for the WhatsApp integration and positive confirmation states, providing a high-visibility contrast against the blue foundation.

## Typography
The typography system pairs the high-energy, geometric structure of **Montserrat** for headings with the utilitarian clarity of **Inter** for body content. 
- All headlines must use **Uppercase** styling to reinforce an athletic, impactful tone. 
- Use `headline-xl` for hero banners and limited `headline-lg` for section titles.
- For mobile devices, `headline-xl` should scale down to `32px` to ensure legibility and prevent awkward word breaks.
- Pricing is treated as a specific display role using the accent blue color and a bold weight to ensure immediate visual conversion.

## Layout & Spacing
The design system employs a **Fixed Grid** system for desktop, centered within the viewport. 
- **Desktop:** 12-column grid with a 1280px maximum width.
- **Tablet:** 8-column grid with 24px margins.
- **Mobile:** 4-column grid with 16px margins.

Vertical rhythm is maintained through an 8px base unit. Section spacing should be generous (80px+) to maintain a premium, airy feel. Product grids should utilize a consistent 24px gutter to ensure individual items remain distinct and legible.

## Elevation & Depth
Depth in this design system is achieved through **Low-Contrast Outlines** and extremely soft ambient shadows. 
- **Borders:** A 1px solid border in `#B5D4F4` is the primary method of defining surfaces. 
- **Shadows:** Use a "Natural Ambient" shadow for interactive cards: `0px 4px 20px rgba(4, 44, 83, 0.05)`. This adds subtle lift without breaking the clean, flat aesthetic.
- **Layering:** The primary navigation bar should be sticky with a slight white blur effect (Backdrop Filter) to maintain context as users scroll through long product listings.

## Shapes
The shape language is "Rounded" to balance the aggressive uppercase typography with an approachable, modern feel. 
- Standard components (buttons, input fields) use a 0.5rem (8px) radius.
- Large containers (product cards) use 1rem (16px) to emphasize the soft shadow and border treatment.
- Interactive elements should never be fully sharp (0px) or fully pill-shaped, keeping the system looking structured and professional.

## Components
- **CTA Buttons:** Primary buttons are solid `#185FA5` with white `#FFFFFF` Montserrat bold text. Hover states should shift to a slightly darker shade of cobalt with a subtle scale-up effect (1.02x).
- **Product Cards:** Must have a white background, a 1px border of `#B5D4F4`, and a soft shadow on hover. Images should be centered with ample padding (at least 24px) from the card edge.
- **Navigation:** Horizontal layout. Links in Neutral Navy `#042C53` using Inter Medium. Active states are indicated by a 2px bottom border in Primary Cobalt.
- **Input Fields:** White background with `#B5D4F4` borders. On focus, the border shifts to Bright Blue `#378ADD` with a subtle glow.
- **Chips/Badges:** Small labels for "New", "Sale", or "High Protein" should use the Bright Blue `#378ADD` with white text, or a light blue background with navy text for secondary information.
- **Footer:** Full-width `#042C53` background with all text and icons in white or light blue tints to ensure high contrast and readability.
- **WhatsApp Button:** A floating action button (FAB) or a specific "Chat with Expert" button using `#25D366` and a white WhatsApp icon.