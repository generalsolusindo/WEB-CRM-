import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Cegah scroll wheel mengubah nilai <input type="number"> secara tidak sengaja:
// saat di-scroll, lepas fokus dari field sehingga wheel kembali menggulung halaman.
document.addEventListener(
    'wheel',
    () => {
        const el = document.activeElement;
        if (el && el.tagName === 'INPUT' && el.type === 'number') el.blur();
    },
    { passive: true },
);

createInertiaApp({
    title: (title) => (title ? `${title} - Website CRM` : 'Website CRM'),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
