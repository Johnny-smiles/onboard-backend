/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.vue',
    './resources/**/*.ts',
    './resources/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#0B5FFF',
        'primary-600': '#0A54E6',
        'primary-700': '#094BCC',
        secondary: '#7C3AED',
        success: '#16A34A',
        warning: '#D97706',
        danger: '#DC2626',
        info: '#0891B2',
      },
      container: {
        center: true,
        padding: '1.5rem',
        screens: {
          xl: '1280px',
        },
      },
      boxShadow: {
        card: '0 4px 12px -2px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.06)',
      },
      borderRadius: {
        lg: '1rem',
        xl: '1.25rem',
      },
      transitionTimingFunction: {
        brand: 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
      transitionDuration: {
        brand: '200ms',
      },
    },
  },
  plugins: [require('@tailwindcss/typography'), require('@tailwindcss/forms')],
};
