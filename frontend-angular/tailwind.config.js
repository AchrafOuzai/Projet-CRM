/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{html,ts,scss}"],
  theme: {
    extend: {
      colors: {
        primary:   { DEFAULT: '#2563eb', hover: '#1d4ed8', light: '#eff6ff', 200: '#bfdbfe' },
        slate:     { sidebar: '#1e293b', dark: '#0f172a', hover: '#334155', border: '#334155' },
        success:   { DEFAULT: '#059669', light: '#d1fae5' },
        warning:   { DEFAULT: '#d97706', light: '#fef3c7' },
        danger:    { DEFAULT: '#dc2626', light: '#fee2e2' },
        info:      { DEFAULT: '#0284c7', light: '#e0f2fe' },
      },
      fontFamily: {
        sans:    ['"DM Sans"', 'sans-serif'],
        display: ['"Syne"', 'sans-serif'],
      },
      boxShadow: {
        'card':    '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
        'card-md': '0 4px 12px rgba(0,0,0,0.08)',
        'card-lg': '0 10px 30px rgba(0,0,0,0.10)',
        'btn':     '0 2px 6px rgba(37,99,235,0.25)',
      },
    },
  },
  plugins: [],
};