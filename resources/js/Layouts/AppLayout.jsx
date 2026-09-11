import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { FiCheckCircle, FiAlertTriangle, FiX } from 'react-icons/fi';
import Sidebar from '../Components/Sidebar';
import Topbar from '../Components/Topbar';

function Flash({ tone, children, onClose }) {
    const styles = tone === 'error'
        ? 'border-danger/25 bg-danger-soft text-danger'
        : 'border-success/25 bg-success-soft text-success';
    const Icon = tone === 'error' ? FiAlertTriangle : FiCheckCircle;

    return (
        <div className={`mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm ${styles}`}>
            <Icon className="mt-0.5 h-4 w-4 shrink-0" />
            <span className="flex-1">{children}</span>
            <button onClick={onClose} className="opacity-60 transition hover:opacity-100"><FiX className="h-4 w-4" /></button>
        </div>
    );
}

export default function AppLayout({ children }) {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState({ success: false, error: false });

    useEffect(() => {
        setDismissed({ success: false, error: false });
    }, [flash?.success, flash?.error]);

    return (
        <div className="flex h-screen gap-4 bg-bg p-4">
            <Sidebar />
            <div className="flex min-w-0 flex-1 flex-col gap-4">
                <Topbar />
                <main className="min-h-0 flex-1 overflow-y-auto pb-6 pr-0.5">
                    {flash?.success && !dismissed.success && (
                        <Flash tone="success" onClose={() => setDismissed((d) => ({ ...d, success: true }))}>{flash.success}</Flash>
                    )}
                    {flash?.error && !dismissed.error && (
                        <Flash tone="error" onClose={() => setDismissed((d) => ({ ...d, error: true }))}>{flash.error}</Flash>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
