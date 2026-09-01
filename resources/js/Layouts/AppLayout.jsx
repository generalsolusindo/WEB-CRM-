import Sidebar from '../Components/Sidebar';
import Topbar from '../Components/Topbar';
import { usePage } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { flash } = usePage().props;

    return (
        <div className="flex h-screen bg-bg">
            <Sidebar />
            <div className="flex flex-1 flex-col overflow-hidden">
                <Topbar />
                <main className="flex-1 overflow-y-auto p-8">
                    {flash?.success && (
                        <div className="mx-auto mb-5 max-w-7xl rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm text-success">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mx-auto mb-5 max-w-7xl rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
                            {flash.error}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
