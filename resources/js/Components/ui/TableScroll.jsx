import { useEffect, useRef, useState } from 'react';

/**
 * Pembungkus tabel lebar: tetap bisa digeser ke samping, tapi pengguna diberi tanda jelas —
 * bayangan di tepi yang masih menyimpan isi, dan keterangan "Geser ke samping" di HP selama tabel lebih lebar dari layar.
 * Bisa difokus lewat keyboard (panah kiri/kanan menggulung).
 */
export default function TableScroll({ children, className = '', label = 'Tabel data' }) {
    const scrollerRef = useRef(null);
    const [edge, setEdge] = useState({ left: false, right: false });
    const [overflowing, setOverflowing] = useState(false);

    useEffect(() => {
        const scroller = scrollerRef.current;
        if (!scroller) return undefined;

        const update = () => {
            setEdge({
                left: scroller.scrollLeft > 1,
                right: scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 1,
            });
            setOverflowing(scroller.scrollWidth > scroller.clientWidth + 1);
        };

        update();
        scroller.addEventListener('scroll', update, { passive: true });
        const observer = new ResizeObserver(update);
        observer.observe(scroller);
        if (scroller.firstElementChild) observer.observe(scroller.firstElementChild);

        return () => {
            scroller.removeEventListener('scroll', update);
            observer.disconnect();
        };
    }, []);

    return (
        <div className="relative">
            {overflowing && (
                <div className="border-b border-border bg-surface-2 px-4 py-1.5 text-[11px] font-medium text-text-muted sm:hidden">
                    Geser ke samping untuk melihat semua kolom →
                </div>
            )}
            <div
                ref={scrollerRef}
                role="region"
                aria-label={label}
                tabIndex={0}
                className={`overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40 ${className}`.trim()}
            >
                {children}
            </div>
            <span aria-hidden="true" className={`pointer-events-none absolute inset-y-0 left-0 w-5 bg-gradient-to-r from-navy/15 to-transparent transition-opacity ${edge.left ? 'opacity-100' : 'opacity-0'}`} />
            <span aria-hidden="true" className={`pointer-events-none absolute inset-y-0 right-0 w-5 bg-gradient-to-l from-navy/15 to-transparent transition-opacity ${edge.right ? 'opacity-100' : 'opacity-0'}`} />
        </div>
    );
}
