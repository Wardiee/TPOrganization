<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-dvh bg-bg font-sans text-ink antialiased">
        <a href="#main" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-semibold text-blue focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

        <div class="lg:flex lg:min-h-dvh">
            <x-sidebar class="hidden lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-68 lg:shrink-0 lg:overflow-y-auto" />

            <main id="main" class="min-w-0 flex-1 px-4 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:px-10 lg:pt-8 lg:pb-12">
                <div class="mx-auto flex max-w-[1180px] flex-col gap-4 lg:gap-6">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-tab-bar class="lg:hidden" />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
