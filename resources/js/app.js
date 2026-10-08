import './bootstrap';

import Alpine from 'alpinejs';
import { initAnchorHold } from './anchor-hold';

window.Alpine = Alpine;
Alpine.start();

// Перехід до якоря не «недолітає», поки вище догружаються зображення (resources/js/anchor-hold.js)
initAnchorHold();

// Святкові частинки вантажаться окремим чанком лише коли тема активна
if (document.body?.dataset.holiday) {
    import('./holiday').then(({ startHolidayParticles }) => startHolidayParticles());
}

// Банер згоди та GA4 — окремий чанк лише коли сервер увімкнув аналітику (App\Support\Analytics)
const analyticsRoot = document.getElementById('analytics-consent');
if (analyticsRoot) {
    import('./analytics').then(({ initAnalytics }) => initAnalytics(analyticsRoot));
}
