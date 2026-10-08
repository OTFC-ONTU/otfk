<?php

// Українські рядки, яких бракує в перекладі Filament 4 (Laravel обʼєднує їх із перекладом пакета).

return [
    'column_manager' => ['actions' => ['reorder' => ['label' => 'Змінити порядок стовпця']]],
    'columns' => [
        'icon' => ['boolean' => ['true' => 'Так', 'false' => 'Ні']],
        'select' => ['no_options_message' => 'Немає варіантів.'],
    ],
    'actions' => [
        'reorder_record' => ['label' => 'Перемістити запис :key'],
        'toggle_record_content' => ['label' => 'Розгорнути/згорнути запис :key'],
    ],
    'loading' => 'Завантаження…',
    'result_count' => '{0} Немає результатів|[1,*] Результатів: :count',
];
