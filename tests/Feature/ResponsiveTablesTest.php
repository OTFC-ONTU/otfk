<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Support\ResponsiveTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_tables_keep_their_markup_and_get_separate_scroll_containers(): void
    {
        $description = '<p>Освітні програми</p><table style="width: 450px"><thead><tr><th>Галузь</th><th>Інформаційні технології</th></tr></thead><tbody><tr><td>Спеціальність</td><td>Комп’ютерна інженерія</td></tr></tbody></table>';
        $department = Department::create(['title' => 'Тестове відділення', 'slug' => 'table-test', 'type' => 'viddilennya', 'description' => $description, 'is_published' => true]);

        $this->get('/struktura/table-test')->assertOk()
            ->assertSee('<div class="content-table-scroll" tabindex="0"><table style="width: 450px">', false)
            ->assertSee('</tbody></table></div>', false);
        $this->assertSame($description, $department->fresh()->description);
    }

    public function test_nested_tables_and_non_content_table_strings_are_preserved(): void
    {
        $html = '<!-- <table> --><script>const example = "<table></table>";</script><p>Текст</p><table><tr><td><table><tr><td>Вкладена</td></tr></table></td></tr></table>';
        $result = ResponsiveTables::render($html);

        $this->assertSame(2, substr_count($result, 'class="content-table-scroll"'));
        $this->assertStringStartsWith('<!-- <table> --><script>const example = "<table></table>";</script><p>Текст</p>', $result);
        $this->assertStringContainsString('</table></div></td></tr></table></div>', $result);
        $this->assertSame('', ResponsiveTables::render(null));
    }
}
