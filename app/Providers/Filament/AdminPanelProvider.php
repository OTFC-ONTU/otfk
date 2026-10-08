<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use App\Filament\Auth\Login;
use App\Filament\Auth\TwoFactorChallenge;
use App\Filament\Auth\TwoFactorSetup;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile()
            ->brandName('ОТФК - Адмінпанель')
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Blue,
            ])
            // Порядок груп меню: щоденна робота зверху, налаштування — внизу
            // (без цього Filament ставить групи в порядку виявлення класів).
            ->navigationGroups([
                'Контент',
                'Структура сайту',
                'Абітурієнту',
                'Структура та персонал',
                'Публічна інформація',
                'SEO',
                'Налаштування',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                // Сторінки другого фактора (поза auto-discovery: без навігації, лише за маршрутом).
                TwoFactorChallenge::class,
                TwoFactorSetup::class,
            ])
            // Редактор режиму «HTML» (HtmlRichEditor): форматування + лінивий чанк CodeMirror
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn (): string => Blade::render("@vite('resources/js/admin/html-editor.js')"))
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Маршрути панелі не входять до групи web, тому захисні заголовки додаємо явно.
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactor::class,
            ])
            // Другий фактор перевіряється і на Livewire-викликах компонентів панелі.
            ->persistentMiddleware([
                RequireTwoFactor::class,
            ]);
    }

    /**
     * Поведінка Filament 3, яку Filament 4 змінив типово (upgrade guide, «silent changes»):
     * фільтри таблиць застосовуються одразу, секції/сітки на всю ширину форми.
     */
    public function boot(): void
    {
        Table::configureUsing(fn (Table $table) => $table->deferFilters(false));
        Section::configureUsing(fn (Section $section) => $section->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid) => $grid->columnSpanFull());
        Fieldset::configureUsing(fn (Fieldset $fieldset) => $fieldset->columnSpanFull());
    }
}
