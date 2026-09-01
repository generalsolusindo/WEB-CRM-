import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e) {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <>
            <Head title="Masuk" />

            <div className="flex min-h-screen items-center justify-center bg-bg px-4">
                <div className="w-full max-w-md rounded-xl border border-border bg-surface p-8 shadow-sm">
                    <h1 className="mb-1 text-xl font-semibold text-text">
                        Masuk ke CRM General Solusindo
                    </h1>
                    <p className="mb-6 text-sm text-text-muted">
                        Silakan masuk menggunakan akun internal Anda.
                    </p>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label htmlFor="email" className="mb-1 block text-sm font-medium text-text">
                                Email
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                autoComplete="username"
                                autoFocus
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-navy focus:ring-1 focus:ring-navy"
                            />
                            {errors.email && (
                                <p className="mt-1 text-sm text-danger">{errors.email}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1 block text-sm font-medium text-text">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text outline-none focus:border-navy focus:ring-1 focus:ring-navy"
                            />
                            {errors.password && (
                                <p className="mt-1 text-sm text-danger">{errors.password}</p>
                            )}
                        </div>

                        <label className="flex items-center gap-2 text-sm text-text-muted">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="rounded border-border"
                            />
                            Ingat saya
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-navy px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-light disabled:opacity-60"
                        >
                            {processing ? 'Memproses...' : 'Masuk'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
