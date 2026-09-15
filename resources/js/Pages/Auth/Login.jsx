import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FiUser, FiLock, FiEye, FiEyeOff, FiShield } from 'react-icons/fi';
import { Button } from '../../Components/ui';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    function submit(e) {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <>
            <Head title="Masuk" />

            <div className="flex min-h-screen bg-bg">
                <div className="relative hidden w-full max-w-md flex-col justify-between overflow-hidden bg-gradient-to-br from-navy via-navy to-primary px-10 py-12 text-white lg:flex xl:max-w-lg">
                    <div className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/5 blur-3xl" />
                    <div
                        className="pointer-events-none absolute inset-0 opacity-[0.07]"
                        style={{
                            backgroundImage: 'radial-gradient(currentColor 1px, transparent 1px)',
                            backgroundSize: '22px 22px',
                        }}/>

                    <div className="relative w-fit rounded-xl bg-white p-4 shadow-lg">
                        <img src="/images/logo-gs.png" alt="General Solusindo" className="h-11 w-auto" />
                    </div>

                    <div className="relative space-y-4">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/80 ring-1 ring-white/20">
                            <FiShield className="h-3.5 w-3.5" />
                            Portal Internal
                        </span>
                        <h2 className="text-3xl font-semibold leading-tight">
                            Satu platform untuk seluruh alur kerja General Solusindo.
                        </h2>
                        <p className="text-sm leading-relaxed text-white/70">
                            Kelola lead, quotation, procurement, hingga project delivery dalam satu sistem yang terintegrasi dan mudah dipantau.
                        </p>
                    </div>

                    <p className="relative text-xs text-white/50">
                        &copy; {new Date().getFullYear()} General Solusindo. Internal use only.
                    </p>
                </div>

                <div className="flex w-full flex-1 items-center justify-center px-4 py-12">
                    <div className="w-full max-w-sm rounded-2xl border border-border bg-surface p-8 shadow-sm">
                        <div className="mb-6 flex justify-center lg:hidden">
                            <img src="/images/logo-gs.png" alt="General Solusindo" className="h-12 w-auto" />
                        </div>

                        <h1 className="mb-1 text-xl font-semibold text-text">
                            Masuk ke CRM Internal
                        </h1>
                        <p className="mb-6 text-sm text-text-muted">
                            Silakan masuk menggunakan akun internal Anda.
                        </p>

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label htmlFor="username" className="mb-1 block text-sm font-medium text-text">
                                    Username
                                </label>
                                <div className="relative">
                                    <FiUser className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-faint" />
                                    <input
                                        id="username"
                                        type="text"
                                        value={data.username}
                                        autoComplete="username"
                                        autoCapitalize="none"
                                        autoFocus
                                        onChange={(e) => setData('username', e.target.value.toLowerCase())}
                                        className="w-full rounded-lg border border-border bg-surface py-2 pl-9 pr-3 text-sm text-text outline-none transition hover:border-text-faint focus:border-primary focus:shadow-[0_0_0_3px_var(--color-primary-ring)]"
                                    />
                                </div>
                                {errors.username && (
                                    <p className="mt-1 text-sm text-danger">{errors.username}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1 block text-sm font-medium text-text">
                                    Password
                                </label>
                                <div className="relative">
                                    <FiLock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-faint" />
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="w-full rounded-lg border border-border bg-surface py-2 pl-9 pr-9 text-sm text-text outline-none transition hover:border-text-faint focus:border-primary focus:shadow-[0_0_0_3px_var(--color-primary-ring)]"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((v) => !v)}
                                        tabIndex={-1}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-text-faint transition hover:text-text-muted"
                                    >
                                        {showPassword ? <FiEyeOff className="h-4 w-4" /> : <FiEye className="h-4 w-4" />}
                                    </button>
                                </div>
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

                            <Button type="submit" loading={processing} className="w-full justify-center">
                                {processing ? 'Memproses...' : 'Masuk'}
                            </Button>
                        </form>

                        <p className="mt-6 flex items-center justify-center gap-1.5 text-center text-xs text-text-faint">
                            <FiShield className="h-3.5 w-3.5 shrink-0" />
                            Akses khusus karyawan internal
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
