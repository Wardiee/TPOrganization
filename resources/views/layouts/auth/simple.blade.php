<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="tp-navy min-h-dvh bg-navy font-sans text-ink antialiased">
        <div class="flex min-h-dvh flex-col items-center justify-center gap-8 px-4 pt-[max(2.5rem,env(safe-area-inset-top))] pb-[max(2.5rem,env(safe-area-inset-bottom))]">
            <div class="flex flex-col items-center gap-3 text-center">
                <img src="/images/logo.png" alt="Tropang Potchi Brotherhood logo" class="size-24">
                <div class="flex flex-col items-center gap-1">
                    <p class="font-display text-2xl font-extrabold tracking-[0.03em] text-white">TROPANG POTCHI</p>
                    <p class="text-sm text-navy-muted">Management System</p>
                </div>
                <p class="rounded-full bg-yellow px-3 py-1 text-xs font-bold text-ink">Where memories are made</p>
            </div>

            <main class="w-full max-w-sm rounded-2xl border border-line bg-white p-6 shadow-[0_12px_40px_rgba(10,14,48,0.35)] sm:p-8">
                {{ $slot }}
            </main>

            <p class="text-xs text-navy-muted">Tropang Potchi Brotherhood · Since 2020</p>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
