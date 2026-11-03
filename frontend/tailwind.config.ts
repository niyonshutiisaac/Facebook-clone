import type { Config } from 'tailwindcss';

/**
 * The Avimoney design system.
 *
 * Every token here is a CSS variable (see src/styles/index.css) so dark mode is
 * a class flip on <html> rather than a second set of utility classes, and so the
 * same values can be read from JS when a component needs a raw colour
 * (canvas fills, chart palettes, native form controls).
 */
const config: Config = {
  darkMode: ['class', '[data-theme="dark"]'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        // Brand ----------------------------------------------------------
        facebook: {
          DEFAULT: '#1877f2',
          hover: '#166fe5',
          action: '#0866ff',
          'action-hover': '#0054c7',
          green: '#42b72a',
          'green-hover': '#36a420',
          red: '#f02849',
          'red-hover': '#d91e3c',
        },

        // Surfaces -------------------------------------------------------
        canvas: 'rgb(var(--canvas) / <alpha-value>)',
        surface: 'rgb(var(--surface) / <alpha-value>)',
        elevated: 'rgb(var(--elevated) / <alpha-value>)',
        subtle: 'rgb(var(--subtle) / <alpha-value>)',
        divider: 'rgb(var(--divider) / <alpha-value>)',
        overlay: 'rgb(var(--overlay) / <alpha-value>)',

        // Text -----------------------------------------------------------
        primary: 'rgb(var(--text-primary) / <alpha-value>)',
        secondary: 'rgb(var(--text-secondary) / <alpha-value>)',
        tertiary: 'rgb(var(--text-tertiary) / <alpha-value>)',
        accent: 'rgb(var(--text-accent) / <alpha-value>)',

        // Semantic -------------------------------------------------------
        hover: 'rgb(var(--hover) / <alpha-value>)',
        pressed: 'rgb(var(--pressed) / <alpha-value>)',
        skeleton: 'rgb(var(--skeleton) / <alpha-value>)',
      },

      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          '"Segoe UI Historic"',
          '"Segoe UI"',
          'Helvetica',
          'Arial',
          'sans-serif',
          '"Apple Color Emoji"',
          '"Segoe UI Emoji"',
          '"Segoe UI Symbol"',
        ],
        emoji: ['"Apple Color Emoji"', '"Segoe UI Emoji"', '"Noto Color Emoji"', 'sans-serif'],
      },

      fontSize: {
        // Facebook's own scale: a 15px body with a 13px secondary label.
        '2xs': ['0.6875rem', { lineHeight: '1.2727', letterSpacing: '0' }], // 11
        xs: ['0.75rem', { lineHeight: '1.3333', letterSpacing: '0' }], // 12
        sm: ['0.8125rem', { lineHeight: '1.2308', letterSpacing: '0' }], // 13
        base: ['0.875rem', { lineHeight: '1.2857', letterSpacing: '0' }], // 14
        md: ['0.9375rem', { lineHeight: '1.3333', letterSpacing: '-0.006em' }], // 15 ← body
        lg: ['1.0625rem', { lineHeight: '1.2353', letterSpacing: '-0.008em' }], // 17
        xl: ['1.25rem', { lineHeight: '1.2', letterSpacing: '-0.01em' }], // 20
        '2xl': ['1.5rem', { lineHeight: '1.1667', letterSpacing: '-0.012em' }], // 24
        '3xl': ['1.75rem', { lineHeight: '1.1429', letterSpacing: '-0.014em' }], // 28
        '4xl': ['2rem', { lineHeight: '1.125', letterSpacing: '-0.016em' }], // 32
      },

      spacing: {
        header: '3.5rem', // 56px
        'sidebar-left': '22.5rem', // 360px
        'sidebar-right': '21.875rem', // 350px
        'avatar-xs': '1.5rem',
        'avatar-sm': '2rem',
        'avatar-md': '2.5rem',
        'avatar-lg': '3.5rem',
        'avatar-xl': '10.5rem',
      },

      borderRadius: {
        card: '0.5rem', // 8px  — posts, cards
        control: '0.375rem', // 6px  — buttons, inputs
        bubble: '1.125rem', // 18px — comment + chat bubbles
        pill: '9999px',
      },

      boxShadow: {
        card: '0 1px 2px rgb(0 0 0 / 0.1), 0 0 0 1px rgb(0 0 0 / 0.04)',
        'card-dark': '0 1px 2px rgb(0 0 0 / 0.4), 0 0 0 1px rgb(255 255 255 / 0.04)',
        dropdown: '0 4px 16px rgb(0 0 0 / 0.2), 0 0 0 1px rgb(0 0 0 / 0.04)',
        modal: '0 12px 28px rgb(0 0 0 / 0.2), 0 2px 4px rgb(0 0 0 / 0.1)',
        'focus-ring': '0 0 0 2px rgb(var(--surface)), 0 0 0 4px #1877f2',
        composer: '0 2px 12px rgb(0 0 0 / 0.08)',
      },

      zIndex: {
        base: '0',
        sticky: '10',
        header: '100',
        'sidebar-overlay': '150',
        dropdown: '300',
        popover: '310',
        messenger: '350',
        modal: '400',
        toast: '500',
        tooltip: '600',
      },

      keyframes: {
        'fade-in': {
          from: { opacity: '0' },
          to: { opacity: '1' },
        },
        'scale-in': {
          from: { opacity: '0', transform: 'scale(0.96)' },
          to: { opacity: '1', transform: 'scale(1)' },
        },
        'slide-up': {
          from: { opacity: '0', transform: 'translateY(8px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        'reaction-pop': {
          '0%': { transform: 'scale(0.4) translateY(0)' },
          '60%': { transform: 'scale(1.25) translateY(-8px)' },
          '100%': { transform: 'scale(1) translateY(0)' },
        },
        shimmer: {
          '100%': { transform: 'translateX(100%)' },
        },
        'ken-burns': {
          '0%': { transform: 'scale(1) translate3d(0, 0, 0)' },
          '100%': { transform: 'scale(1.14) translate3d(0, -1.5%, 0)' },
        },
        'typing-bounce': {
          '0%, 60%, 100%': { transform: 'translateY(0)', opacity: '0.4' },
          '30%': { transform: 'translateY(-4px)', opacity: '1' },
        },
      },

      animation: {
        'fade-in': 'fade-in 120ms ease-out',
        'scale-in': 'scale-in 140ms cubic-bezier(0.08, 0.52, 0.37, 0.98)',
        'slide-up': 'slide-up 180ms cubic-bezier(0.08, 0.52, 0.37, 0.98)',
        'reaction-pop': 'reaction-pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1)',
        shimmer: 'shimmer 1.4s infinite',
        'ken-burns': 'ken-burns 18s ease-in-out infinite alternate',
        'typing-bounce': 'typing-bounce 1.2s infinite ease-in-out',
      },

      transitionTimingFunction: {
        fb: 'cubic-bezier(0.08, 0.52, 0.37, 0.98)',
      },

      maxWidth: {
        feed: '42.5rem', // 680px — Facebook's single column width
        profile: '61.25rem', // 980px
      },
    },
  },
  plugins: [],
};

export default config;
