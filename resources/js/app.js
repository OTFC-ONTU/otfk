import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Святкові частинки вантажаться окремим чанком лише коли тема активна
if (document.body?.dataset.holiday) {
    import('./holiday').then(({ startHolidayParticles }) => startHolidayParticles());
}
