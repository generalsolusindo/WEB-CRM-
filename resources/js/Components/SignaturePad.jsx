import { useRef, useState } from 'react';

/** Kanvas tanda tangan sederhana — mouse & sentuhan, tanpa library eksternal. */
export default function SignaturePad({ onChange }) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    const [isEmpty, setIsEmpty] = useState(true);

    function pos(e) {
        const rect = canvasRef.current.getBoundingClientRect();
        const point = e.touches ? e.touches[0] : e;
        return { x: point.clientX - rect.left, y: point.clientY - rect.top };
    }

    function start(e) {
        e.preventDefault();
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
                onMouseDown={start}
                onMouseMove={move}
                onMouseUp={end}
                onMouseLeave={end}
                onTouchStart={start}
                onTouchMove={move}
                onTouchEnd={end}
            />
            <div className="mt-2 flex items-center justify-between">
                <span className="text-xs text-text-muted">{isEmpty ? 'Tanda tangan di area di atas' : 'Tanda tangan tersimpan'}</span>
                <button type="button" onClick={clear} className="text-xs font-semibold text-info">Hapus & Ulangi</button>
            </div>
        </div>
    );
}
