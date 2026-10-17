@props(['title' => null, 'description' => null, 'ogImage' => null, 'robots' => null])

@php
    use App\Models\MenuItem;
    use App\Models\QuickLink;
    use App\Models\Setting;

    $menu = MenuItem::navigation();
    $s = Setting::publicMap();
    $partners = QuickLink::visible()->location('footer_partner')->ordered()->get();
    $logo = ! empty($s['logo']) ? asset('storage/' . $s['logo']) : null;
    // Без завантаженої іконки — щит герба коледжу для вкладки (favicon.ico 16/32/48, icon-32.png) і повний герб для телефонів (180/192px)
    $favicon = ! empty($s['favicon']) ? asset('storage/' . $s['favicon']) : asset('favicon.ico');
    $touchIcon = ! empty($s['favicon']) ? $favicon : asset('apple-touch-icon.png');
    // Опис: власний опис сторінки, інакше загальний опис сайту; MetaText чистить HTML і обрізає ~160 символів
    $metaDesc = \App\Support\MetaText::from($description, $s['site_description'] ?? null, __('layout.description'));
    $siteName = app()->getLocale() === 'en' ? ($s['brand_name'] ?? __('layout.brand_name')) : config('app.name');
    // <title>: «Заголовок — ОТФК ОНТУ» без повтору бренду; головна — лише назва сайту
    $titleBrand = filled($s['brand_short'] ?? null) ? $s['brand_short'] : __('layout.brand_short');
    $pageTitle = \App\Support\MetaText::title($title, $siteName, $titleBrand, [$s['brand_name'] ?? null, config('app.name'), __('layout.brand_name')]);
    // Індексація (docs/seo-plan.md): тестовий домен закритий повністю, окремі
    // сторінки (пошук, архів за роком) передають robots самі. Canonical — лише
    // для індексованих сторінок, із значущими параметрами (page, category, year).
    // /en без повного незастарілого перекладу матеріалу — noindex, follow;
    // hreflang — лише коли індексуються обидві мовні версії (Seo::alternates).
    $robotsMeta = \App\Support\Seo::robots($robots);
    $canonical = \App\Support\Seo::canonical();
    $hreflang = \App\Support\Seo::alternates($robots);
    // Святкова тема (App\Support\HolidayTheme): null — звичайний вигляд
    $holidayKey = \App\Support\HolidayTheme::active();
    $holiday = \App\Support\HolidayTheme::config($holidayKey);
    // Чи веде пункт меню на поточну сторінку (порівнюємо шлях без домену й слешів) — для aria-current
    $currentPath = rtrim(request()->getPathInfo(), '/') ?: '/';
    $navCurrent = function (?string $href) use ($currentPath) {
        if (! $href || $href === '#') {
            return false;
        }
        $path = rtrim(parse_url($href, PHP_URL_PATH) ?: '/', '/') ?: '/';

        return $path === $currentPath;
    };
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Позначка, що JS активний — лише тоді вмикається scroll-reveal (без FOUC) --}}
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $pageTitle }}</title>
    <link rel="icon" href="{{ $favicon }}"@if (\Illuminate\Support\Str::endsWith($favicon, '.svg')) type="image/svg+xml"@endif>
    @empty($s['favicon'])
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    @endempty
    <link rel="apple-touch-icon" href="{{ $touchIcon }}">
    <meta name="description" content="{{ $metaDesc }}">
    @if ($robotsMeta)
        <meta name="robots" content="{{ $robotsMeta }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title ?: $siteName }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:url" content="{{ $canonical }}">
    @php $shareImage = $ogImage ?: $logo; @endphp
    @if ($shareImage)
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:alt" content="{{ $title ?: $siteName }}">
    @endif
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_GB' : 'uk_UA' }}">
    {{-- Велика картка лише коли є справжня обкладинка сторінки (не логотип-фолбек) --}}
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <link rel="alternate" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">
    <link rel="alternate" type="application/rss+xml" title="RSS — {{ __('layout.news') }}" href="{{ \App\Support\LocalizedUrl::route('news.feed') }}">
    @unless (\App\Support\Seo::isNoindex($robotsMeta))
        <link rel="canonical" href="{{ $canonical }}">
        @foreach ($hreflang as $hreflangCode => $hreflangUrl)
            <link rel="alternate" hreflang="{{ $hreflangCode }}" href="{{ $hreflangUrl }}">
        @endforeach
    @endunless
    {{-- EducationalOrganization: адреса PostalAddress, alternateName/sameAs — з «SEO → Розмітка та аналітика» --}}
    @php $jsonld = \App\Support\StructuredData::organization($s); @endphp
    <script type="application/ld+json">{!! json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800|cormorant-garamond:400,500,600,700|lora:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white text-slate-700"
      @if ($holiday) data-holiday="{{ $holidayKey }}" data-holiday-particles="{{ $holiday['particles']['type'] }}"
      data-holiday-colors="{{ implode(',', $holiday['particles']['colors']) }}" style="{{ \App\Support\HolidayTheme::style($holiday) }}" @endif>

    {{-- Пропустити навігацію (зʼявляється лише при фокусі з клавіатури) --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        {{ __('layout.skip') }}
    </a>

    {{-- ============================ БАНЕР ОГОЛОШЕНЬ ============================ --}}
    @php
        $annText = trim($s['announcement_text'] ?? '');
        $annType = $s['announcement_type'] ?? 'info';
        $annUrl = \App\Support\LocalizedUrl::to(trim($s['announcement_url'] ?? ''));
        $annStyles = [
            'info' => ['bar' => 'bg-gradient-to-r from-brand-800 via-brand-700 to-brand-800 text-white', 'badge' => 'bg-gold-400 text-brand-950', 'cta' => 'bg-white text-brand-800 hover:bg-gold-300 hover:text-brand-950'],
            'warning' => ['bar' => 'bg-gold-400 text-brand-950', 'badge' => 'bg-brand-900 text-white', 'cta' => 'bg-brand-900 text-white hover:bg-brand-800'],
            'danger' => ['bar' => 'bg-red-700 text-white', 'badge' => 'bg-white text-red-700', 'cta' => 'bg-white text-red-700 hover:bg-red-50'],
        ];
        $annStyle = $annStyles[$annType] ?? $annStyles['info'];
    @endphp
    @if ($annText !== '')
        <div id="announcement" x-data="{ hidden: false, full: false }"
             x-init="hidden = localStorage.getItem('ann-closed') === @js(md5($annText))"
             x-show="!hidden" x-cloak role="status" aria-live="polite"
             class="{{ $annStyle['bar'] }}">
            <div class="mx-auto flex w-full max-w-[1600px] items-start gap-3 px-4 py-2.5 sm:px-6 md:items-center lg:px-8">
                <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full md:mt-0 {{ $annStyle['badge'] }}">
                    <x-ico name="megaphone" class="h-4 w-4" />
                </span>
                <p class="min-w-0 flex-1 text-[13px] font-medium leading-snug md:text-sm md:line-clamp-none"
                   :class="full ? '' : 'line-clamp-3 cursor-pointer'" @click="full = true">{{ $annText }}</p>
                @if ($annUrl !== '')
                    <a href="{{ $annUrl }}"
                       class="hidden shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-semibold shadow-sm transition sm:inline-flex {{ $annStyle['cta'] }}">
                        {{ __('layout.announcement_more') }}
                        <x-ico name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                @endif
                <button type="button" aria-label="{{ __('layout.close_announcement') }}"
                        @click="hidden = true; try { localStorage.setItem('ann-closed', @js(md5($annText))) } catch (e) {}"
                        class="grid h-7 w-7 shrink-0 place-items-center rounded-full opacity-80 transition hover:bg-black/10 hover:opacity-100">
                    <x-ico name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            @if ($annUrl !== '')
                <div class="px-4 pb-2.5 pl-14 sm:hidden">
                    <a href="{{ $annUrl }}" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold {{ $annStyle['cta'] }}">
                        {{ __('layout.announcement_more') }}
                        <x-ico name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- ============================ ШАПКА ============================ --}}
    <header x-data="{ mobile: false, scrolled: false }" @scroll.window.throttle.50ms="scrolled = window.scrollY > 40">
        {{-- Утилітарна стрічка --}}
        <div class="hidden bg-brand-950 text-brand-100 lg:block">
            <div class="mx-auto flex h-9 w-full max-w-[1600px] items-center justify-between px-4 text-xs sm:px-6 lg:px-8">
                <div class="flex items-center gap-5">
                    @if (! empty($s['contact_phone']))
                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="inline-flex items-center gap-1.5 hover:text-white">
                            <x-ico name="phone" class="h-3.5 w-3.5" /> {{ $s['contact_phone'] }}
                        </a>
                    @endif
                    @if (! empty($s['contact_email']))
                        <a href="mailto:{{ $s['contact_email'] }}" class="inline-flex items-center gap-1.5 hover:text-white">
                            <x-ico name="envelope" class="h-3.5 w-3.5" /> {{ $s['contact_email'] }}
                        </a>
                    @endif
                    {{-- Жива позначка «зараз йде пара» (з розкладу дзвінків) --}}
                    @php $bellPeriods = \App\Models\BellPeriod::chipEnabled() ? \App\Models\BellPeriod::active() : collect(); @endphp
                    @if ($bellPeriods->isNotEmpty())
                        <a href="{{ \App\Support\LocalizedUrl::route('bells') }}"
                           x-data="bellChip(@js($bellPeriods->map(fn ($b) => ['id' => $b->id, 'sh' => $b->shift, 'n' => $b->number, 's' => substr($b->starts, 0, 5), 'e' => substr($b->ends, 0, 5)])->values()))"
                           x-init="tick(); setInterval(() => tick(), 30000)" x-show="label" x-cloak
                           class="inline-flex items-center gap-1.5 rounded-full bg-gold-400/15 px-2.5 py-0.5 font-medium text-gold-200 ring-1 ring-gold-400/30 transition hover:bg-gold-400/25">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-300 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-gold-400"></span>
                            </span>
                            <span x-text="label"></span>
                        </a>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    @if (! empty($s['social_facebook']))
                        <a href="{{ $s['social_facebook'] }}" target="_blank" rel="noopener" class="hover:text-white">Facebook</a>
                    @endif
                    @if (! empty($s['social_instagram']))
                        <a href="{{ $s['social_instagram'] }}" target="_blank" rel="noopener" class="hover:text-white">Instagram</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Липка частина: бренд (білий) + навігація (темна) — без білої смуги під меню --}}
        <div class="sticky top-0 z-40">
            {{-- Ряд бренду та дій --}}
            <div class="border-b border-transparent bg-white shadow-sm transition-[box-shadow,background-color,border-color] duration-300"
                 :class="scrolled ? 'border-slate-200/80 bg-white/90 shadow-md backdrop-blur-md' : ''">
                <div class="mx-auto flex h-16 w-full max-w-[1600px] items-center justify-between gap-3 px-4 sm:h-20 sm:gap-6 sm:px-6 lg:px-8">
                <a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <span class="relative shrink-0">
                    @if ($holiday)
                        <x-holiday.badge :theme="$holiday" :size="24" class="holiday-logo-badge" />
                    @endif
                    @if ($logo)
                        @php $logoDims = \App\Support\ImageDimensions::of($s['logo'] ?? null); @endphp
                        <img src="{{ $logo }}" alt="{{ $s['brand_short'] ?? __('layout.brand_short') }}" @if ($logoDims) width="{{ $logoDims['width'] }}" height="{{ $logoDims['height'] }}" @endif decoding="async" class="h-10 w-auto shrink-0 sm:h-12 lg:h-16">
                    @else
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-700 to-brand-900 text-white shadow-sm sm:h-12 sm:w-12">
                            <x-ico name="academic-cap" class="h-7 w-7" />
                        </span>
                    @endif
                    </span>
                    <span class="min-w-0 leading-tight">
                        <span class="font-display block truncate text-base sm:text-lg font-extrabold tracking-tight text-brand-900">{{ $s['brand_short'] ?? __('layout.brand_short') }}</span>
                        <span class="hidden truncate text-xs text-slate-500 sm:block">{{ $s['brand_name'] ?? __('layout.brand_name') }}</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
                    <div class="hidden sm:block"><x-language-switcher /></div>
                    {{-- Пошук з миттєвими підказками (десктоп) --}}
                    <div x-data="liveSearch(@js(\App\Support\LocalizedUrl::route('search.suggest')), @js(\App\Support\LocalizedUrl::route('search')))"
                         @click.outside="open = false" @keydown.escape.window="open = false"
                         class="relative hidden lg:block">
                        <form action="{{ \App\Support\LocalizedUrl::route('search') }}" method="GET" class="relative">
                            <x-ico name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" name="q" maxlength="{{ \App\Support\SearchQuery::MAX_LENGTH }}" placeholder="{{ __('layout.search_placeholder') }}" autocomplete="off"
                                   x-model="q" @input.debounce.250ms="suggest()" @focus="items.length && (open = true)"
                                   class="w-48 rounded-full border-0 bg-slate-100 py-2 pl-9 pr-4 text-sm text-slate-700 ring-1 ring-transparent transition focus:w-64 focus:bg-white focus:ring-2 focus:ring-brand-500" />
                        </form>
                        <div x-show="open" x-cloak
                             class="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                            <template x-for="it in items" :key="it.url + it.title">
                                <a :href="it.url" class="flex items-center gap-2.5 px-3.5 py-2.5 transition hover:bg-brand-50">
                                    <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500" x-text="it.group"></span>
                                    <span class="min-w-0 truncate text-sm text-slate-700" x-text="it.title"></span>
                                </a>
                            </template>
                            <p x-show="!items.length && !busy" class="px-3.5 py-3 text-sm text-slate-400">{{ __('layout.no_results') }}</p>
                            <a x-show="items.length" :href="allUrl()"
                               class="block border-t border-slate-100 px-3.5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                                {{ __('layout.all_results') }}
                            </a>
                        </div>
                    </div>
                    {{-- CTA --}}
                    <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="btn-accent group h-11 whitespace-nowrap px-2.5 text-xs sm:h-auto sm:px-5 sm:text-sm">{{ __('layout.applicants') }}</a>
                    {{-- Мобільні дії --}}
                    <a href="{{ \App\Support\LocalizedUrl::route('search') }}" class="grid h-10 w-10 place-items-center rounded-xl text-brand-900 transition hover:bg-slate-100 lg:hidden" aria-label="{{ __('layout.search') }}"><x-ico name="magnifying-glass" class="h-5 w-5" /></a>
                    <button type="button" @click="mobile = true" aria-controls="mobile-menu" :aria-expanded="mobile ? 'true' : 'false'" class="grid h-10 w-10 place-items-center rounded-xl bg-brand-900 text-white shadow-sm transition hover:bg-brand-800 xl:hidden" aria-label="{{ __('layout.menu') }}"><x-ico name="bars-3" class="h-6 w-6" /></button>
                </div>
                </div>
            </div>

            {{-- Навігаційна стрічка (десктоп): один рядок; пункти, що не вміщаються, переходять у «Ще» --}}
            <nav class="hidden bg-brand-900 xl:block" aria-label="{{ __('layout.menu') }}">
                <div x-data="navOverflow({{ $menu->count() }})" x-ref="bar"
                     class="mx-auto flex w-full max-w-[1600px] items-stretch px-4 sm:px-6 lg:px-8"
                     :class="ready ? 'flex-nowrap' : 'flex-wrap'">
                    @foreach ($menu as $item)
                        @php
                            $itemActive = $item->children->isNotEmpty()
                                ? $item->children->contains(fn ($child) => $navCurrent($child->href))
                                : $navCurrent($item->href);
                        @endphp
                        @if ($item->children->isNotEmpty())
                            <div data-nav-item x-data="{ open: false, right: false }" :class="{{ $loop->index }} >= visible && '!hidden'"
                                 @mouseenter="right = $el.getBoundingClientRect().left + 300 > window.innerWidth; open = true"
                                 @mouseleave="open = false" @keydown.escape="open = false"
                                 class="relative shrink-0">
                                <button type="button" @click="open = ! open" :aria-expanded="open"
                                        @class([
                                            'flex h-full items-center gap-1 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition hover:bg-white/5 hover:text-white',
                                            'border-transparent text-white/85' => ! $itemActive,
                                            'border-gold-400 text-white' => $itemActive,
                                        ])
                                        :class="open ? '!border-gold-400 bg-white/5 text-white' : ''">
                                    {{ $item->localized_label }}
                                    <x-ico name="chevron-down" class="h-3.5 w-3.5 opacity-60 transition" x-bind:class="open && 'rotate-180'" />
                                </button>
                                <div x-show="open" x-cloak x-transition.opacity :class="right ? 'right-0' : 'left-0'"
                                     class="absolute top-full z-50 max-h-[75vh] w-72 overflow-y-auto rounded-b-xl border border-slate-200 bg-white p-2 shadow-2xl">
                                    @foreach ($item->children as $child)
                                        <a href="{{ $child->href }}" @if ($child->open_new_tab) target="_blank" @endif
                                           @if ($navCurrent($child->href)) aria-current="page" @endif
                                           @class([
                                               'block rounded-lg px-3 py-2 text-sm transition hover:bg-brand-50 hover:text-brand-800',
                                               'text-slate-600' => ! $navCurrent($child->href),
                                               'bg-brand-50 font-semibold text-brand-800' => $navCurrent($child->href),
                                           ])>{{ $child->localized_label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a data-nav-item href="{{ $item->href }}" @if ($item->open_new_tab) target="_blank" @endif
                               @if ($itemActive) aria-current="page" @endif
                               x-bind:class="{{ $loop->index }} >= visible && '!hidden'"
                               @class([
                                   'flex shrink-0 items-center whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition hover:bg-white/5',
                                   'border-transparent text-white/85 hover:border-gold-400/60 hover:text-white' => ! $itemActive,
                                   'border-gold-400 text-white' => $itemActive,
                               ])>{{ $item->localized_label }}</a>
                        @endif
                    @endforeach

                    {{-- «Ще»: пункти, що не вмістилися в рядок --}}
                    <div data-nav-more x-data="{ open: false }" x-cloak :class="ready && visible >= total && '!hidden'"
                         @mouseenter="open = true" @mouseleave="open = false" @keydown.escape="open = false"
                         class="relative ml-auto shrink-0">
                        <button type="button" @click="open = ! open" :aria-expanded="open"
                                class="flex h-full items-center gap-1.5 whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm font-semibold text-gold-300 transition hover:bg-white/5 hover:text-gold-200"
                                :class="open ? '!border-gold-400 bg-white/5' : ''">
                            <x-ico name="ellipsis-horizontal-circle" class="h-4 w-4" />
                            {{ __('layout.nav_more') }}
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity
                             class="absolute right-0 top-full z-50 max-h-[75vh] w-80 overflow-y-auto rounded-b-xl border border-slate-200 bg-white p-2 shadow-2xl">
                            @foreach ($menu as $item)
                                <div :class="{{ $loop->index }} < visible && 'hidden'">
                                    @if ($item->children->isNotEmpty())
                                        <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $item->localized_label }}</p>
                                        @foreach ($item->children as $child)
                                            <a href="{{ $child->href }}" @if ($child->open_new_tab) target="_blank" @endif
                                               @class([
                                                   'block rounded-lg px-3 py-2 text-sm transition hover:bg-brand-50 hover:text-brand-800',
                                                   'text-slate-600' => ! $navCurrent($child->href),
                                                   'bg-brand-50 font-semibold text-brand-800' => $navCurrent($child->href),
                                               ])>{{ $child->localized_label }}</a>
                                        @endforeach
                                    @else
                                        <a href="{{ $item->href }}" @if ($item->open_new_tab) target="_blank" @endif
                                           @class([
                                               'block rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-brand-50 hover:text-brand-800',
                                               'text-slate-700' => ! $navCurrent($item->href),
                                               'bg-brand-50 font-semibold text-brand-800' => $navCurrent($item->href),
                                           ])>{{ $item->localized_label }}</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </nav>

            {{-- Святкова гірлянда звисає під шапкою; ховається під час прокручування --}}
            @if ($holiday)
                <x-holiday.garland :theme="$holiday" class="holiday-garland-hang" x-show="!scrolled"
                                   x-transition.opacity.duration.300ms />
            @endif
        </div>

        {{-- Мобільне меню (off-canvas) --}}
        <div id="mobile-menu" x-show="mobile" x-cloak x-effect="document.documentElement.classList.toggle('overflow-hidden', mobile); document.body.classList.toggle('overflow-hidden', mobile)"
             @keydown.escape.window="mobile = false" class="fixed inset-0 z-50 xl:hidden">
            <div @click="mobile = false" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
            <div class="absolute right-0 top-0 flex h-full w-80 max-w-[88%] flex-col bg-white shadow-2xl"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
                <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 pl-5 pr-3">
                    <span class="font-display font-extrabold text-brand-900">{{ __('layout.menu') }}</span>
                    <div class="flex items-center gap-2">
                        <x-language-switcher :label="__('layout.language_mobile')" />
                        <button type="button" @click="mobile = false" aria-label="{{ __('layout.close_menu') }}"
                                class="grid h-10 w-10 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100"><x-ico name="x-mark" class="h-6 w-6" /></button>
                    </div>
                </div>
                <div class="border-b border-slate-100 p-4">
                    <div x-data="liveSearch(@js(\App\Support\LocalizedUrl::route('search.suggest')), @js(\App\Support\LocalizedUrl::route('search')))" class="relative">
                        <form action="{{ \App\Support\LocalizedUrl::route('search') }}" method="GET" class="relative">
                            <x-ico name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" name="q" maxlength="{{ \App\Support\SearchQuery::MAX_LENGTH }}" placeholder="{{ __('layout.site_search_placeholder') }}" autocomplete="off"
                                   x-model="q" @input.debounce.250ms="suggest()" class="input w-full pl-9" />
                        </form>
                        <div x-show="open && items.length" x-cloak
                             class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                            <template x-for="it in items" :key="it.url + it.title">
                                <a :href="it.url" class="flex items-center gap-2.5 px-3.5 py-2.5 transition hover:bg-brand-50">
                                    <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500" x-text="it.group"></span>
                                    <span class="min-w-0 truncate text-sm text-slate-700" x-text="it.title"></span>
                                </a>
                            </template>
                            <a :href="allUrl()" class="block border-t border-slate-100 px-3.5 py-2.5 text-sm font-semibold text-brand-700">{{ __('layout.all_results') }}</a>
                        </div>
                    </div>
                    <a href="{{ \App\Support\LocalizedUrl::to('/abituriyentu') }}" class="btn-accent mt-3 w-full">{{ __('layout.applicants') }}</a>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <a href="{{ \App\Support\LocalizedUrl::route('bells') }}" class="btn-outline px-2 text-xs">{{ __('public.bells') }}</a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline px-2 text-xs">{{ __('public.contacts') }}</a>
                    </div>
                    <div class="mt-3 space-y-2 text-sm text-slate-600">
                        @if (! empty($s['contact_phone']))
                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="block hover:text-brand-700">{{ $s['contact_phone'] }}</a>
                        @endif
                        @if (! empty($s['contact_email']))
                            <a href="mailto:{{ $s['contact_email'] }}" class="block break-all hover:text-brand-700">{{ $s['contact_email'] }}</a>
                        @endif
                    </div>
                </div>
                <nav class="flex-1 overflow-y-auto p-3">
                    @foreach ($menu as $item)
                        @if ($item->children->isNotEmpty())
                            <div x-data="{ sub: false }" class="border-b border-slate-100">
                                <button @click="sub = !sub" class="flex w-full items-center justify-between px-3 py-3 text-sm font-semibold text-slate-800">
                                    {{ $item->localized_label }}
                                    <x-ico name="chevron-down" class="h-4 w-4 transition" ::class="sub && 'rotate-180'" />
                                </button>
                                <div x-show="sub" x-cloak class="pb-2">
                                    @foreach ($item->children as $child)
                                        <a href="{{ $child->href }}" @if ($child->open_new_tab) target="_blank" @endif
                                           @if ($navCurrent($child->href)) aria-current="page" @endif
                                           @class([
                                               'block rounded-lg px-5 py-2 text-sm hover:bg-brand-50 hover:text-brand-800',
                                               'text-slate-600' => ! $navCurrent($child->href),
                                               'bg-brand-50 font-semibold text-brand-800' => $navCurrent($child->href),
                                           ])>{{ $child->localized_label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <a href="{{ $item->href }}" @if ($item->open_new_tab) target="_blank" @endif
                               @if ($navCurrent($item->href)) aria-current="page" @endif
                               @class([
                                   'block border-b border-slate-100 px-3 py-3 text-sm font-semibold hover:text-brand-800',
                                   'text-slate-800' => ! $navCurrent($item->href),
                                   'text-brand-700' => $navCurrent($item->href),
                               ])>{{ $item->localized_label }}</a>
                        @endif
                    @endforeach
                </nav>
            </div>
        </div>
    </header>

    {{-- Вміст --}}
    <main id="main-content" class="flex-1">
        {{ $slot }}
    </main>

    {{-- Підвал --}}
    <footer class="border-t border-white/15 bg-brand-950 text-brand-100">
        @if ($holiday)
            {{-- Святкове вітання над підвалом --}}
            <x-holiday.greeting :theme="$holiday" :holiday-key="$holidayKey" />
        @endif
        <div class="container-site grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <div class="flex items-center gap-3">
                    @if ($logo)
                        <span class="grid h-11 place-items-center rounded-xl bg-white px-2 ring-1 ring-white/15">
                            <img src="{{ $logo }}" alt="{{ $s['brand_short'] ?? __('layout.brand_short') }}" @if ($logoDims ?? null) width="{{ $logoDims['width'] }}" height="{{ $logoDims['height'] }}" @endif loading="lazy" decoding="async" class="h-8 w-auto">
                        </span>
                    @else
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-white ring-1 ring-white/15">
                            <x-ico name="academic-cap" class="h-6 w-6" />
                        </span>
                    @endif
                    <span class="font-display font-extrabold text-white">{{ $s['brand_short'] ?? __('layout.brand_short') }}</span>
                </div>
                {{-- Опис у підвалі — лише якщо заповнений у налаштуваннях (у підвалі оригіналу його немає) --}}
                @if (filled($s['footer_about'] ?? null))
                    <p class="mt-4 text-sm leading-relaxed text-brand-200">{{ $s['footer_about'] }}</p>
                @endif
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-white">{{ __('layout.sections') }}</h3>
                <ul class="mt-4 space-y-2 text-sm text-brand-200">
                    <li><a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="hover:text-white">{{ __('layout.home') }}</a></li>
                    <li><a href="{{ \App\Support\LocalizedUrl::route('news.index') }}" class="hover:text-white">{{ __('layout.news') }}</a></li>
                    <li><a href="{{ \App\Support\LocalizedUrl::route('events') }}" class="hover:text-white">{{ __('layout.events') }}</a></li>
                    <li><a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="hover:text-white">{{ __('layout.specialties') }}</a></li>
                    <li><a href="{{ \App\Support\LocalizedUrl::route('galleries.index') }}" class="hover:text-white">{{ __('layout.gallery') }}</a></li>
                    <li><a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="hover:text-white">{{ __('layout.contacts') }}</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-white">{{ __('layout.contacts') }}</h3>
                <ul class="mt-4 space-y-3 text-sm text-brand-200">
                    @if (! empty($s['contact_address']))
                        <li class="flex gap-2"><x-ico name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-gold-300" /> {{ $s['contact_address'] }}</li>
                    @endif
                    @if (! empty($s['contact_phone']))
                        <li class="flex gap-2"><x-ico name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-gold-300" /> {{ $s['contact_phone'] }}</li>
                    @endif
                    @if (! empty($s['contact_email']))
                        <li class="flex gap-2"><x-ico name="envelope" class="mt-0.5 h-4 w-4 shrink-0 text-gold-300" /> {{ $s['contact_email'] }}</li>
                    @endif
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-white">{{ __('layout.partners') }}</h3>
                <ul class="mt-4 space-y-2 text-sm text-brand-200">
                    @forelse ($partners as $partner)
                        <li><a href="{{ \App\Support\LocalizedUrl::to($partner->url) }}" @if ($partner->open_new_tab) target="_blank" rel="noopener" @endif class="hover:text-white">{{ $partner->localized('title') }}</a></li>
                    @empty
                        <li><a href="https://onaft.edu.ua" target="_blank" rel="noopener" class="hover:text-white">{{ __('layout.university') }}</a></li>
                        <li><a href="https://mon.gov.ua" target="_blank" rel="noopener" class="hover:text-white">{{ __('layout.ministry') }}</a></li>
                    @endforelse
                </ul>
                <div class="mt-5 flex gap-3">
                    @if (! empty($s['social_facebook']))
                        <a href="{{ $s['social_facebook'] }}" target="_blank" rel="noopener" aria-label="{{ __('layout.facebook') }}"
                           class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"><x-brand-ico name="facebook" class="h-4 w-4" /></a>
                    @endif
                    @if (! empty($s['social_instagram']))
                        <a href="{{ $s['social_instagram'] }}" target="_blank" rel="noopener" aria-label="{{ __('layout.instagram') }}"
                           class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"><x-brand-ico name="instagram" class="h-4 w-4" /></a>
                    @endif
                </div>
            </div>
        </div>
        <div class="border-t border-white/10 py-5">
            @php
                // Напис версії сайту: текст і колір редагуються в адмінці
                // (Налаштування: site_version_label / site_version_color).
                $versionLabel = trim($s['site_version_label'] ?? __('layout.version_alpha'));
                $versionColors = [
                    'gold' => ['badge' => 'bg-gold-400/15 text-gold-200 ring-gold-400/30', 'dot' => 'bg-gold-400'],
                    'green' => ['badge' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/30', 'dot' => 'bg-emerald-400'],
                    'blue' => ['badge' => 'bg-sky-400/15 text-sky-200 ring-sky-400/30', 'dot' => 'bg-sky-400'],
                    'red' => ['badge' => 'bg-red-400/15 text-red-200 ring-red-400/30', 'dot' => 'bg-red-400'],
                    'gray' => ['badge' => 'bg-slate-400/15 text-slate-300 ring-slate-400/30', 'dot' => 'bg-slate-400'],
                ];
                $versionColor = $versionColors[$s['site_version_color'] ?? 'gold'] ?? $versionColors['gold'];
            @endphp
            <div class="container-site flex flex-col items-center justify-center gap-2.5 text-center text-xs text-brand-300 sm:flex-row">
                {{-- Копірайт як у підвалі оригіналу otfk.od.ua; кінцевий рік — поточний за Києвом (app.timezone = UTC) --}}
                <span>© 2014-{{ now('Europe/Kyiv')->year }} {{ __('layout.copyright') }}</span>
                <x-analytics-settings-link />
                @if ($versionLabel !== '')
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium ring-1 {{ $versionColor['badge'] }}"
                          title="{{ __('layout.version_stage') }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $versionColor['dot'] }}"></span> {{ $versionLabel }}
                    </span>
                @endif
            </div>
        </div>
    </footer>

    {{-- Банер згоди на GA4 (лише з Measurement ID, основний домен, гість) --}}
    <x-analytics-consent />

    {{-- Логіка «зараз йде пара» (розклад дзвінків): спільна для плашки в шапці та сторінки розкладу --}}
    <script>
        (function () {
            const ORD = { 1: '1-ша', 2: '2-га', 3: '3-тя', 4: '4-та', 5: '5-та', 6: '6-та', 7: '7-ма', 8: '8-ма' };
            const bellMessages = @js(['shift' => __('feature.bell_shift'), 'class' => __('feature.bell_class'), 'remaining' => __('feature.bell_remaining'), 'next' => __('feature.bell_next'), 'break' => __('feature.bell_break'), 'done' => __('feature.bell_done')]);
            const bellMessage = (key, values) => Object.entries(values).reduce((text, [name, value]) => text.replace(':' + name, value), bellMessages[key]);
            const ordinal = n => @js(app()->getLocale()) === 'en' ? n : (ORD[n] ?? n);
            const toMin = t => +t.slice(0, 2) * 60 + +t.slice(3, 5);

            // ID запису відрізняє однакові номери; зміни можуть тривати одночасно.
            window.bellState = function (periods) {
                const empty = { current: [], gaps: [], status: '', short: '', left: {}, pct: {} };
                const d = new Date();
                if (d.getDay() === 0 || !periods.length) return empty;
                const cur = d.getHours() * 60 + d.getMinutes();
                const multi = new Set(periods.map(p => p.sh)).size > 1;
                const key = p => String(p.id);
                const name = p => bellMessage('class', { number: ordinal(p.n) }) + (multi ? ' (' + bellMessage('shift', { number: p.sh }) + ')' : '');
                const gaps = [];
                for (const shift of new Set(periods.map(p => p.sh))) {
                    const rows = periods.filter(p => p.sh === shift).sort((a, b) => toMin(a.s) - toMin(b.s));
                    let latestEnd = 0;
                    rows.forEach((p, i) => {
                        latestEnd = Math.max(latestEnd, toMin(p.e));
                        const next = rows[i + 1];
                        if (next && cur >= latestEnd && cur < toMin(next.s)) gaps.push(key(p));
                    });
                }
                const running = periods.filter(p => cur >= toMin(p.s) && cur < toMin(p.e));
                if (running.length) {
                    const left = {}, pct = {};
                    running.forEach(p => {
                        const start = toMin(p.s), end = toMin(p.e);
                        left[key(p)] = end - cur;
                        pct[key(p)] = Math.round((cur - start) / (end - start) * 100);
                    });
                    const text = bellMessage('remaining', { names: running.map(name).join(' · '), minutes: Math.min(...Object.values(left)) });
                    return { current: running.map(key), gaps, status: text, short: text, left, pct };
                }
                const next = periods.filter(p => toMin(p.s) > cur).sort((a, b) => toMin(a.s) - toMin(b.s))[0];
                if (next) {
                    const started = cur >= Math.min(...periods.map(p => toMin(p.s)));
                    const text = bellMessage(started ? 'break' : 'next', { name: name(next), time: next.s });
                    return started || toMin(next.s) - cur <= 60 ? { ...empty, gaps, status: text, short: text } : empty;
                }
                return { ...empty, status: bellMessages.done };
            };
            window.bellChip = periods => ({ label: '', tick() { this.label = window.bellState(periods).short; } });
            window.bellSchedule = periods => ({
                current: [], gaps: [], status: '', left: {}, pct: {},
                isNow(key) { return this.current.includes(key); },
                isGapNow(key) { return this.gaps.includes(key); },
                tick() { Object.assign(this, window.bellState(periods)); },
            });

            // Прелоад сторінок при наведенні: клік відчувається миттєвим.
            // Пропускаємо зовнішні лінки, файли, адмінку та режим економії трафіку.
            (function () {
                const conn = navigator.connection;
                if (conn && (conn.saveData || /2g/.test(conn.effectiveType || ''))) return;
                const seen = new Set();
                let timer = null;
                document.addEventListener('mouseover', e => {
                    const a = e.target.closest('a[href]');
                    if (!a || a.origin !== location.origin || a.target === '_blank') return;
                    const url = a.href.split('#')[0];
                    if (seen.has(url) || url === location.href.split('#')[0]) return;
                    if (/^\/(admin|storage|build|livewire)\b/.test(a.pathname) || /\/ics$/.test(a.pathname)) return;
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        seen.add(url);
                        const l = document.createElement('link');
                        l.rel = 'prefetch'; l.href = url; l.as = 'document';
                        document.head.appendChild(l);
                    }, 65); // невелика затримка — реагуємо лише на «намір», а не на проліт курсора
                }, { passive: true });
                document.addEventListener('mouseout', () => clearTimeout(timer), { passive: true });
            })();

            // Десктоп-меню в один рядок: ширини пунктів вимірюємо (після шрифтів) лише коли
            // стрічка видима; при зміні ширини перераховуємо, скільки вміщається; решта — у «Ще».
            window.navOverflow = total => ({
                total, visible: total, ready: false, measuring: false, widths: [], moreWidth: 0,
                init() {
                    (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => this.fit());
                    if ('ResizeObserver' in window) new ResizeObserver(() => this.fit()).observe(this.$refs.bar);
                },
                measure() {
                    if (this.measuring) return;
                    this.measuring = true;
                    this.visible = this.total;
                    this.$nextTick(() => {
                        this.widths = [...this.$refs.bar.querySelectorAll(':scope > [data-nav-item]')].map(el => el.offsetWidth);
                        this.moreWidth = this.$refs.bar.querySelector(':scope > [data-nav-more]').offsetWidth;
                        this.ready = true;
                        this.measuring = false;
                        this.fit();
                    });
                },
                fit() {
                    const bar = this.$refs.bar;
                    if (!bar.clientWidth) return; // стрічка прихована (мобільна ширина)
                    if (!this.ready) { this.measure(); return; }
                    const cs = getComputedStyle(bar);
                    const avail = bar.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                    if (this.widths.reduce((a, b) => a + b, 0) <= avail) { this.visible = this.total; return; }
                    let used = this.moreWidth, n = 0;
                    while (n < this.widths.length && used + this.widths[n] <= avail) used += this.widths[n++];
                    this.visible = n;
                },
            });

            // Миттєві підказки пошуку (шапка + мобільне меню)
            window.liveSearch = (suggestUrl, searchUrl) => ({
                q: '', items: [], open: false, busy: false,
                suggest() {
                    const q = this.q.trim();
                    if (q.length < 2) { this.items = []; this.open = false; return; }
                    this.busy = true;
                    fetch(suggestUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                        .then(r => r.json())
                        .then(d => { this.items = d.results || []; this.open = true; })
                        .catch(() => {})
                        .finally(() => this.busy = false);
                },
                allUrl() { return searchUrl + '?q=' + encodeURIComponent(this.q.trim()); },
            });

            // Поява секцій при прокручуванні. Вимикається, якщо немає підтримки
            // або користувач у системі обрав «зменшити рух» — тоді все видно одразу.
            (function () {
                const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reduce || !('IntersectionObserver' in window)) {
                    document.querySelectorAll('[data-reveal]').forEach(el => el.classList.add('is-visible'));
                    return;
                }
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(e => {
                        if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
                    });
                }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
            })();
        })();
    </script>
</body>
</html>
