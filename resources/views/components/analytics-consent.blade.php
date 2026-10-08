{{--
    Банер згоди на Google Analytics 4 (App\Support\Analytics, docs/seo-plan.md).
    Виводиться гостям на будь-якому домені, коли задано Measurement ID.
    Сервер не віддає скрипт Google: resources/js/analytics.js (окремий чанк)
    показує банер, зберігає вибір у localStorage і лише після «Прийняти»
    підвантажує gtag. Без JS банер лишається прихованим (hidden).
--}}
@if (\App\Support\Analytics::bannerVisible())
    {{-- ID віддаємо лише основному домену (Analytics::tracking()). На тестовому
         хостингу банер працює так само, але data-ga4-id немає, а
         data-analytics-disabled — тож analytics.js ніколи не завантажує gtag. --}}
    <div id="analytics-consent"
         @if (\App\Support\Analytics::tracking()) data-ga4-id="{{ \App\Support\Analytics::measurementId() }}" @else data-analytics-disabled @endif
         data-consent-version="{{ \App\Support\Analytics::CONSENT_VERSION }}"
         data-query-keys="{{ json_encode(\App\Support\Seo::CANONICAL_QUERY) }}">
        <section data-consent-banner hidden role="region" aria-labelledby="analytics-consent-title"
                 aria-describedby="analytics-consent-text"
                 class="fixed inset-x-3 bottom-3 z-[45] max-h-[60vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-2xl shadow-brand-950/20 ring-1 ring-slate-200 sm:inset-x-auto sm:bottom-5 sm:left-5 sm:max-w-md sm:p-5">
            <p id="analytics-consent-title" class="font-display text-base font-bold text-brand-900">{{ __('layout.consent.title') }}</p>
            <p id="analytics-consent-text" class="mt-1.5 text-sm leading-relaxed text-slate-600">
                {{ __('layout.consent.text') }}
                {{-- Посилання лише на опубліковану CMS-сторінку політики — без «мертвого» 404 до її створення --}}
                @if (\App\Models\Page::published()->where('slug', 'polityka-konfidentsiynosti')->exists())
                    <a href="{{ \App\Support\LocalizedUrl::to('/polityka-konfidentsiynosti') }}"
                       class="font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-900">{{ __('layout.consent.policy') }}</a>
                @endif
            </p>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" data-consent-reject class="btn-outline flex-1 sm:flex-none">{{ __('layout.consent.reject') }}</button>
                <button type="button" data-consent-accept class="btn-primary flex-1 sm:flex-none">{{ __('layout.consent.accept') }}</button>
            </div>
        </section>
    </div>
@endif
