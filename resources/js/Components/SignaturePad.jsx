import { useRef, useState } from 'react';

/** Kanvas tanda tangan sederhana — mouse & sentuhan, tanpa library eksternal. */
export default function SignaturePad({ onChange }) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    const [isEmpty, setIsEmpty] = useState(true);

    function pos(e) {
        const rect = canvasRef.current.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left) * canvasRef.current.width / rect.width,
            y: (e.clientY - rect.top) * canvasRef.current.height / rect.height,
        };
    }

    function start(e) {
        if (!e.isPrimary || e.button !== 0) return;
        e.preventDefault();
        canvasRef.current.setPointerCapture(e.pointerId);
        drawing.current = true;
        const ctx = canvasRef.current.getContext('2d');
        const { x, y } = pos(e);
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function move(e) {
        if (!drawing.current) return;
        e.preventDefault();
        const ctx = canvasRef.current.getContext('2d');
        const { x, y } = pos(e);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        ctx.lineTo(x, y);
        ctx.stroke();
        setIsEmpty(false);
    }

    function end() {
        if (!drawing.current) return;
        drawing.current = false;
        onChange(canvasRef.current.toDataURL('image/png'));
    }

    function clear() {
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setIsEmpty(true);
        onChange(null);
    }

    return (
        <div>
            <canvas
                ref={canvasRef}
                width={400}
                height={160}
                className="w-full touch-none rounded-lg border border-border bg-white"
                aria-label="Area tanda tangan"
                onPointerDown={start}
                onPointerMove={move}
                onPointerUp={end}
                onPointerCancel={end}
                onLostPointerCapture={end}
            />
            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs text-text-muted">{isEmpty ? 'Tanda tangan di area di atas' : 'Tanda tangan tersimpan'}</span>
                <button type="button" onClick={clear} className="min-h-11 text-xs font-semibold text-info">Hapus & Ulangi</button>
            </div>
        </div>
    );
}
