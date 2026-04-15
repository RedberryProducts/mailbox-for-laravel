import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  // Base: title-md weight, lg roundedness per design.md "Buttons" spec.
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg title-sm font-medium transition-[filter,background-color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        // Primary: 135° gradient from primary → primary-container, the
        // signature polish for actionable / destructive moments.
        default:
          "bg-primary-gradient text-on-primary hover:brightness-110 active:brightness-95",
        destructive:
          "bg-primary-gradient text-on-primary hover:brightness-110 active:brightness-95",
        // Secondary: transparent + ghost border at 15% opacity.
        outline:
          "bg-transparent border border-outline-variant text-on-surface hover:bg-surface-container-low",
        secondary:
          "bg-surface-container-low text-on-surface hover:bg-surface-container",
        // Tertiary: no chrome, primary text marks it as actionable.
        ghost: "bg-transparent text-on-surface hover:bg-surface-container-low",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-9 px-4 py-2",
        "xs": "h-7 rounded-md px-2",
        "sm": "h-8 rounded-md px-3 label-md",
        "lg": "h-10 rounded-lg px-8",
        "icon": "h-9 w-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
