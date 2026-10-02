import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // คลาสสี Tailwind บางส่วนถูกประกาศไว้ใน PHP เช่น DeskState::meta()
        // ถ้าไม่สแกนไฟล์เหล่านี้ คลาสจะถูกตัดทิ้งตอน build และสีจะไม่ขึ้น
        './app/**/*.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
