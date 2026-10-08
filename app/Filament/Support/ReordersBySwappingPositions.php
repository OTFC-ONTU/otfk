<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Перетягування рядків таблиці (Table::reorderable('sort_order')) для сторінок-списків.
 *
 * Стандартний Filament нумерує показані записи 1…n прямим UPDATE. Тут показані записи лише
 * обмінюються своїми наявними номерами: при пошуку чи фільтрі (розділ, підрозділ, категорія)
 * переставлені записи не «перестрибують» через приховані, а збереження йде через модель
 * (без зміни updated_at) — спрацьовують скидання кешів меню, sitemap тощо.
 */
trait ReordersBySwappingPositions
{
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        $table = $this->getTable();
        if (! $table->isReorderable()) {
            return;
        }

        $table->callBeforeReordering($order);

        $column = (string) str($table->getReorderColumn())->afterLast('.');
        $order = array_values($order);
        $records = $table->getQuery()->whereKey($order)->get()->keyBy(fn (Model $record) => (string) $record->getKey());

        $positions = $records->map(fn (Model $record) => (int) $record->getAttribute($column))->sort()->values()->all();
        // Однакові номери (старі дані) — послідовні, починаючи з найменшого
        if (count(array_unique($positions)) !== count($positions)) {
            $positions = range($positions[0] ?? 1, ($positions[0] ?? 1) + count($positions) - 1);
        }

        DB::transaction(function () use ($order, $records, $positions, $column): void {
            foreach ($order as $index => $key) {
                $record = $records->get((string) $key);
                if (! $record || ! isset($positions[$index]) || (int) $record->getAttribute($column) === $positions[$index]) {
                    continue;
                }
                $record->timestamps = false;
                $record->forceFill([$column => $positions[$index]])->save();
            }
        });

        $table->callAfterReordering($order);
    }
}
