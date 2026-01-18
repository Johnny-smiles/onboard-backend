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
        primary: '#FF4D5A',
        'primary-600': '#F13B4B',
        'primary-700': '#D72E3D',
        secondary: '#1BB8A7',
        success: '#22C55E',
        warning: '#F59E0B',
        danger: '#EF4444',
        info: '#2D7FF9',
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
