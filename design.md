# Design System Documentation

## 1. Overview & Creative North Star
### Creative North Star: Architectural Precision
This design system is built for the modern developer who views code as a craft. Moving away from the "generic dashboard" aesthetic, this system adopts an editorial approach to technical data. It is characterized by high-contrast typography, expansive negative space, and a rejection of traditional containment.

By utilizing "Architectural Precision," we treat the interface as a physical workspace where depth is defined by light and material rather than lines and boxes. The layout leverages intentional asymmetry and overlapping surfaces to guide the developer’s eye through complex data streams with the grace of a high-end broadsheet.

## 2. Colors & Surface Philosophy
The palette is rooted in a sophisticated range of cool grays and whites, punctuated by the surgical application of Laravel’s signature red.

### The "No-Line" Rule
To achieve a premium, custom feel, **1px solid borders are strictly prohibited for sectioning.**
Structural boundaries must be defined exclusively through background color shifts. For example, a navigation sidebar should utilize `surface-container-low` against a `surface` main content area. This creates a natural, soft transition that feels integrated rather than partitioned.

### Surface Hierarchy & Nesting
Treat the UI as a series of stacked architectural layers.
- **Base Layer:** `surface` (#fbf8ff)
- **Secondary Workspace:** `surface-container-low` (#f4f2fd)
- **Primary Focused Elements:** `surface-container-lowest` (#ffffff)
- **Elevated Overlays:** `surface-container-high` (#e8e7f1)

By nesting a `surface-container-lowest` card inside a `surface-container-low` section, you create a perceived lift that is clean and modern.

### Glass & Gradient Transitions
For floating elements, such as command palettes or tooltips, employ **Glassmorphism**. Use semi-transparent surface colors (80% opacity) combined with a `20px` backdrop blur.
- **Signature Polish:** For primary actions, use a subtle linear gradient from `primary` (#bc0003) to `primary_container` (#e71610) at a 135-degree angle. This adds "soul" and dimension to buttons that flat colors cannot replicate.

## 3. Typography
The typography system uses **Inter** to bridge the gap between technical utility and editorial elegance.

- **Display (lg, md, sm):** Reserved for large metrics and impact statements. These should use tight letter-spacing (-0.02em) to feel authoritative.
- **Headlines & Titles:** Used for section headers and page titles. The transition from `headline-lg` to `title-md` creates a clear path for the user’s eye.
- **Body (lg, md, sm):** Optimised for readability in logs and data tables. `body-md` is the workhorse of the system.
- **Labels:** Use `label-sm` in all-caps with increased letter-spacing (+0.05em) for metadata and status indicators to provide a "pro-tool" aesthetic.

## 4. Elevation & Depth
Depth in this design system is organic, not artificial.

### The Layering Principle
Avoid shadows for static elements. Instead, use the **Tonal Layering** method:
- Place a `surface-container-lowest` object on a `surface-container-low` background to signify importance.

### Ambient Shadows
When an element must float (e.g., a modal or a search dropdown), use "Ambient Shadows."
- **Value:** `0px 20px 40px`
- **Color:** `on-surface` (#1a1b22) at **4% to 8% opacity**. This mimics natural light rather than a digital drop-shadow.

### The "Ghost Border" Fallback
If accessibility requirements demand a container edge, use a **Ghost Border**:
- **Token:** `outline-variant` at **15% opacity**. Never use a 100% opaque border.

## 5. Components

### Sidebar Navigation
The sidebar should not have a trailing border. Use `surface-container-low` to distinguish the area. Active states should use a vertical "pill" indicator in `primary` (Red) or a subtle background shift to `surface-container-highest`.

### Search Inputs
- **Style:** Minimalist. No border. Use `surface-container-highest` as the background.
- **State:** On focus, transition the background to `surface-container-lowest` and apply an Ambient Shadow.
- **Typography:** Use `body-md` for input text and `label-md` for the search icon/shortcut key hints.

### Status Badges
Badges should avoid the "heavy" look of fully opaque backgrounds.
- **Success:** `tertiary_fixed_dim` with `on_tertiary_fixed`.
- **Error/Alert:** `primary_fixed` with `on_primary_fixed`.
- **Neutral:** `secondary_container` with `on_secondary_container`.
- **Shape:** Use the `full` roundedness scale for a soft, pill-shaped finish.

### Buttons
- **Primary:** Gradient-filled (`primary` to `primary_container`), `on_primary` text, `lg` (0.5rem) roundedness.
- **Secondary:** Transparent background with a Ghost Border.
- **Tertiary:** No background or border. Use `primary` color for text to signify actionability.

### Lists & Data Tables
**Forbid the use of horizontal divider lines.**
Separate data rows using vertical white space (8px–12px) or a subtle hover effect that shifts the background to `surface-container-low`. This maintains the "Editorial" flow of the page without the clutter of a grid.

## 6. Do's and Don'ts

### Do
- **Do** use `surface-container` tiers to create hierarchy.
- **Do** allow for generous white space around metrics to make them feel "premium."
- **Do** use `primary` (Red) sparingly—only for critical actions, errors, or brand moments.
- **Do** use Inter's medium and semi-bold weights for headlines to create contrast against body text.

### Don't
- **Don't** use 1px solid borders to separate the sidebar from the main content.
- **Don't** use pure black (#000000) for shadows; always use a low-opacity tint of `on-surface`.
- **Don't** use high-contrast dividers between list items.
- **Don't** clutter the screen; if a piece of information isn't vital, hide it behind a hover state or a secondary layer.
