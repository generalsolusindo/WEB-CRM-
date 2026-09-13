/**
 * Progress stepper horizontal — menandai tahap yang sudah lewat, tahap saat ini, dan yang belum.
 * `stages`: [{ value, label }] berurutan. `current`: value tahap aktif.
 */
export default function StageStepper({ stages, current }) {
    const currentIndex = stages.findIndex((s) => s.value === current);

    return (
        <div className="flex w-full items-start overflow-x-auto pb-1">
            {stages.map((stage, i) => {
                const done = currentIndex >= 0 && i < currentIndex;
                const active = i === currentIndex;
                const upcoming = !done && !active;

                return (
                    <div key={stage.value} className="flex min-w-[86px] flex-1 flex-col items-center">
                        <div className="flex w-full items-center">
                            <div className={`h-0.5 flex-1 ${i === 0 ? 'invisible' : done || active ? 'bg-success' : 'bg-border'}`} />
                            <span
                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                    done
                                        ? 'bg-success text-white'
                                        : active
                                        ? 'bg-navy text-white ring-4 ring-primary-soft'
                                        : 'bg-bg text-text-faint'
                                }`}
                            >
                                {done ? '✓' : i + 1}
                            </span>
                            <div className={`h-0.5 flex-1 ${i === stages.length - 1 ? 'invisible' : done ? 'bg-success' : 'bg-border'}`} />
                        </div>
                        <span className={`mt-2 text-center text-[11px] font-medium leading-tight ${
                            active ? 'text-navy' : upcoming ? 'text-text-faint' : 'text-text-muted'
                        }`}>
                            {stage.label}
                        </span>
                        {active && <span className="mt-0.5 text-[10px] font-semibold text-primary-strong">Sekarang</span>}
                    </div>
                );
            })}
        </div>
    );
}
