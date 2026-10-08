<?php

// Українські рядки, яких бракує в перекладі Filament 4 (Laravel обʼєднує їх із перекладом пакета).

return [
    'checkbox_list' => ['required_description' => 'Виберіть хоча б один варіант.'],
    'color_picker' => ['panel_label' => 'Вибір кольору'],
    'date_time_picker' => [
        'month_select' => ['label' => 'Місяць'],
        'year_input' => ['label' => 'Рік'],
        'hour_input' => ['label' => 'Година'],
        'minute_input' => ['label' => 'Хвилина'],
        'second_input' => ['label' => 'Секунда'],
    ],
    'file_upload' => [
        'actions' => [
            'download' => ['label' => 'Завантажити'],
            'open' => ['label' => 'Відкрити в новій вкладці'],
        ],
        'editor' => ['label' => 'Редактор зображення'],
    ],
    'key_value' => ['columns' => ['actions' => ['label' => 'Дії'], 'reorder' => ['label' => 'Порядок']]],
    'repeater' => ['columns' => ['actions' => ['label' => 'Дії'], 'reorder' => ['label' => 'Порядок']]],
    'rich_editor' => [
        'actions' => ['close_panel' => ['label' => 'Закрити панель']],
        'toolbar' => ['label' => 'Панель інструментів редактора'],
        'tools' => [
            'h2' => 'Заголовок H2',
            'h3' => 'Заголовок H3',
            'h4' => 'Заголовок H4',
            'h5' => 'Заголовок H5',
            'h6' => 'Заголовок H6',
            'paragraph' => 'Абзац',
            'details' => 'Розгортний блок',
            'table_toggle_header_cell' => 'Перемкнути клітинку заголовка',
        ],
    ],
    'select' => [
        'actions' => [
            'clear' => ['label' => 'Очистити вибір'],
            'remove_option' => ['label' => 'Прибрати :label'],
        ],
        'no_options_message' => 'Немає варіантів.',
        'search_label' => 'Пошук',
    ],
    'tags_input' => [
        'actions' => ['delete' => ['label' => 'Видалити']],
        'tag_added' => 'Додано: :tag',
        'tag_removed' => 'Видалено: :tag',
    ],
];
