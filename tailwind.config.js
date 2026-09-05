/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: 'var(--shmpp-brand-50, #f0f7f4)',
          100: 'var(--shmpp-brand-100, #dceee5)',
          200: 'var(--shmpp-brand-200, #bbddd0)',
          300: 'var(--shmpp-brand-300, #8ec5b2)',
          400: 'var(--shmpp-brand-400, #5fa690)',
          500: 'var(--shmpp-brand-500, #3f8a74)',
          600: 'var(--shmpp-brand-600, #2f6e5d)',
          700: 'var(--shmpp-brand-700, #27584c)',
          800: 'var(--shmpp-brand-800, #22473e)',
          900: 'var(--shmpp-brand-900, #1e3b35)',
          950: 'var(--shmpp-brand-950, #0f211e)',
        },
        sand: {
          50: '#faf8f5',
          100: '#f3efe8',
          200: '#e6ddd0',
          300: '#d4c5b0',
        },
      },
      fontFamily: {
        display: ['"Fraunces"', 'Georgia', 'serif'],
        sans: ['"Source Sans 3"', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
  corePlugins: {
    preflight: true,
  },
  important: '.shmpp-root',
};
