/** @type {import('tailwindcss').Config} */
export default {
    // Scope semua utility ke dalam wrapper plugin agar tidak konflik dengan CSS WordPress admin.
    important: '#jalagistrasi-wrap',
    content: [
        './templates/**/*.php',
        './src/**/*.php',
        './resources/js/**/*.js',
    ],
    corePlugins: {
        // Nonaktifkan reset CSS global — tidak boleh menimpa styling WordPress.
        preflight: false,
    },
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  'var(--jg-color-50)',
                    100: 'var(--jg-color-100)',
                    200: 'var(--jg-color-200)',
                    300: 'var(--jg-color-300)',
                    400: 'var(--jg-color-400)',
                    500: 'var(--jg-color-500)',
                    600: 'var(--jg-color-600)',
                    700: 'var(--jg-color-700)',
                    800: 'var(--jg-color-800)',
                    900: 'var(--jg-color-900)',
                },
            },
            fontFamily: {
                sans: [
                    'system-ui',
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"Segoe UI"',
                    'Roboto',
                    '"Helvetica Neue"',
                    'Arial',
                    'sans-serif',
                ],
            },
        },
    },
    plugins: [],
}
