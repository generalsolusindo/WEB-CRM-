import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />

            <main className="grid min-h-screen place-items-center bg-slate-950 px-6 text-white">
                <section className="max-w-2xl text-center">
                    <p className="mb-3 text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400">
                        Website CRM
                    </p>
                    <h1 className="text-4xl font-bold tracking-tight sm:text-6xl">
                        Laravel, Inertia, React, dan Tailwind siap digunakan.
                    </h1>
                    <p className="mt-6 text-lg leading-8 text-slate-300">
                        Setup dasar berhasil terhubung dari route Laravel hingga halaman React dengan styling Tailwind CSS v4.
                    </p>
                </section>
            </main>
        </>
    );
}
