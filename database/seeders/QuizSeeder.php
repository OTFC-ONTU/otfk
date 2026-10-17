<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Профорієнтаційний квіз «Яка спеціальність тобі підходить?».
 *
 * Кожен варіант додає бали кільком освітньо-професійним програмам (ОПП) з різною вагою,
 * тож результат складається з усіх відповідей, а не з одного «очевидного» вибору.
 * Програми знаходяться за кодом спеціальності й назвою; якщо програми немає — її бали
 * пропускаються (варіант лишається, але не зараховує їх).
 */
class QuizSeeder extends Seeder
{
    /** Ключ => [код спеціальності, назва ОПП]. */
    public const PROGRAMS = [
        'sd' => ['F2', 'Розробка програмного забезпечення'],
        'net' => ['F7', 'Обслуговування комп’ютерних систем і мереж'],
        'web' => ['F7', 'Комп’ютерна графіка і Web-дизайн'],
        'sec' => ['F7', 'Безпека комп’ютерних систем і мереж'],
        'bak' => ['G13', 'Виробництво хліба, кондитерських, макаронних виробів та харчоконцентратів'],
        'cat' => ['G13', 'Інноваційні технології та організація громадського харчування'],
        'fin' => ['D2', 'Фінанси, банківська справа та інтернет-банкінг'],
        'mgt' => ['D3', 'Менеджмент та організація бізнесу'],
        'mkt' => ['D5', 'ІТ-маркетинг та бізнес комунікації'],
        'cus' => ['D7', 'Товарознавство в митній справі'],
        'log' => ['D7', 'Міжнародна торгівля та логістична діяльність'],
        'ref' => ['G4', 'Монтаж і обслуговування холодильно-компресорних машин та установок'],
        'hva' => ['G4', 'Монтаж і обслуговування систем кондиціювання та вентиляції повітря'],
        'fas' => ['G15', 'Індустрія моди'],
        'con' => ['G15', 'Моделювання та конструювання промислових виробів'],
    ];

    /**
     * Питання: [укр, англ, варіанти]; варіант: [укр, англ, [ключ ОПП => бали]].
     *
     * @return list<array{0: string, 1: string, 2: list<array{0: string, 1: string, 2: array<string, int>}>}>
     */
    public static function questions(): array
    {
        return [
            ['Вільна субота, жодних планів. Що найімовірніше затягне тебе на кілька годин?', 'A free Saturday with no plans. What is most likely to keep you busy for hours?', [
                ['Розібратися, чому гальмує ноутбук чи роутер, і таки це виправити', 'Figuring out why a laptop or router is slow and actually fixing it', ['net' => 2, 'sec' => 1]],
                ['Спекти щось за новим рецептом і пригостити друзів', 'Baking something from a new recipe and treating friends', ['bak' => 2, 'cat' => 1]],
                ['Перешити або прикрасити річ, щоб вона була не як у всіх', 'Altering or decorating a piece of clothing so it stands out', ['fas' => 2, 'con' => 1]],
                ['Змонтувати відео чи намалювати щось у графічному редакторі', 'Editing a video or drawing something in a graphics editor', ['web' => 2]],
                ['Порівняти ціни в кількох країнах і знайти, звідки вигідніше замовити', 'Comparing prices across countries to find the best place to order from', ['log' => 2, 'cus' => 1]],
                ['Спланувати поїздку для всієї компанії: маршрут, бюджет, хто що бере', 'Planning a trip for the whole group: route, budget, who brings what', ['mgt' => 2, 'fin' => 1]],
                ['Написати маленький скрипт або бота, щоб рутина робилася сама', 'Writing a small script or bot so that a routine task does itself', ['sd' => 2, 'sec' => 1]],
            ]],
            ['Груповий проєкт. Яку роль ти береш без вагань?', 'A group project. Which role do you take without hesitation?', [
                ['Розподіляю завдання і стежу, щоб усі встигли до дедлайну', 'I split the tasks and make sure everyone meets the deadline', ['mgt' => 2, 'log' => 1]],
                ['Продумую, як подати результат, щоб його запам’ятали', 'I think through how to present the result so people remember it', ['mkt' => 2, 'web' => 2]],
                ['Рахую бюджет і перевіряю, чи все сходиться', 'I work out the budget and check that the numbers add up', ['fin' => 2]],
                ['Беру частину, де треба розібратися в складній логіці', 'I take the part that needs complex logic worked out', ['sd' => 2, 'sec' => 1]],
                ['Збираю макет чи модель власноруч', 'I build the mock-up or model with my own hands', ['con' => 2, 'hva' => 1, 'ref' => 1]],
                ['Перевіряю, чи все відповідає вимогам, і ловлю помилки', 'I check that everything meets the requirements and catch mistakes', ['cus' => 2, 'sec' => 1]],
            ]],
            ['Що тебе дратує найбільше?', 'What annoys you the most?', [
                ['Коли в приміщенні задушливо чи холодно, а ніхто не знає чому', 'When a room is stuffy or cold and nobody knows why', ['hva' => 2, 'ref' => 1]],
                ['Незручний і некрасивий сайт', 'A clumsy and ugly website', ['web' => 2, 'mkt' => 1]],
                ['Коли гроші зникають невідомо куди', 'When money disappears and nobody knows where', ['fin' => 2]],
                ['Коли посилка тиждень стоїть на одному місці', 'When a parcel has been stuck in one place for a week', ['log' => 2, 'cus' => 1]],
                ['Коли в кафе пів години чекаєш на замовлення', 'When you wait half an hour for your order in a café', ['cat' => 2]],
                ['Коли на нараді всі говорять, а рішення так ніхто й не ухвалює', 'When everyone talks at a meeting but nobody makes a decision', ['mgt' => 2]],
                ['Коли пароль від акаунта виявляється в чужих руках', 'When the password to your account ends up in someone else’s hands', ['sec' => 2]],
            ]],
            ['Що з цього тобі найцікавіше розібрати й зрозуміти зсередини?', 'Which of these would you most like to take apart and understand from the inside?', [
                ['Як холодильник чи морозильна камера «роблять» холод', 'How a fridge or freezer “makes” cold', ['ref' => 2, 'hva' => 1]],
                ['Як улаштований застосунок, яким ти користуєшся щодня', 'How the app you use every day works', ['sd' => 2, 'web' => 1]],
                ['З яких деталей складається улюблена куртка і як їх розкроїли', 'What pieces your favourite jacket is made of and how they were cut', ['con' => 2, 'fas' => 1]],
                ['Що відбувається з тістом, перш ніж воно стане круасаном', 'What happens to dough before it becomes a croissant', ['bak' => 2, 'cat' => 1]],
                ['Який шлях проходить посилка з іншого континенту до твоїх дверей', 'The journey a parcel takes from another continent to your door', ['log' => 2, 'cus' => 1]],
                ['Куди йде сигнал домашнього Wi-Fi і хто ще може його перехопити', 'Where your home Wi-Fi signal goes and who else could intercept it', ['net' => 2, 'sec' => 2]],
            ]],
            ['Який шкільний урок минає для тебе найшвидше?', 'Which school lesson flies by fastest for you?', [
                ['Математика', 'Maths', ['fin' => 1, 'sd' => 1, 'con' => 1]],
                ['Фізика', 'Physics', ['ref' => 2, 'hva' => 1]],
                ['Хімія або біологія', 'Chemistry or biology', ['bak' => 2, 'cat' => 1, 'cus' => 1]],
                ['Інформатика', 'Computer science', ['sd' => 1, 'sec' => 1, 'net' => 1]],
                ['Мистецтво чи трудове навчання', 'Art or crafts', ['fas' => 1, 'con' => 1, 'web' => 1]],
                ['Географія, економіка або іноземна мова', 'Geography, economics or a foreign language', ['log' => 1, 'mkt' => 1, 'mgt' => 1]],
            ]],
            ['Уяви свій ідеальний робочий день. Де ти?', 'Imagine your ideal working day. Where are you?', [
                ['В офісі за двома моніторами, з навушниками', 'In an office at two monitors, with headphones on', ['sd' => 2, 'web' => 1]],
                ['У серверній, де від тебе залежить, чи працює вся мережа', 'In a server room where the whole network depends on you', ['net' => 2, 'sec' => 1]],
                ['На об’єкті: монтуєш вентиляцію й кондиціонери в новій будівлі', 'On site, installing ventilation and air conditioning in a new building', ['hva' => 2, 'ref' => 1]],
                ['На кухні чи у виробничому цеху, де все крутиться навколо їжі', 'In a kitchen or production workshop where everything revolves around food', ['bak' => 2, 'cat' => 2]],
                ['У студії серед тканин, ескізів і манекенів', 'In a studio among fabrics, sketches and mannequins', ['fas' => 2, 'con' => 1]],
                ['У порту чи на кордоні, де щодня проходять вантажі', 'At a port or border crossing where cargo moves every day', ['cus' => 2, 'log' => 1]],
                ['На зустрічах із клієнтами та партнерами', 'In meetings with clients and partners', ['mgt' => 2, 'mkt' => 1]],
            ]],
            ['Друг просить про допомогу. З яким проханням ти впораєшся найкраще?', 'A friend asks for help. Which request would you handle best?', [
                ['«Налаштуй мені комп’ютер і домашню мережу»', '“Set up my computer and home network”', ['net' => 2]],
                ['«Здається, мою сторінку зламали — допоможи»', '“I think my account has been hacked — help!”', ['sec' => 2, 'net' => 1]],
                ['«Допоможи розкрутити мою сторінку в соцмережах»', '“Help me grow my social media page”', ['mkt' => 2, 'web' => 1]],
                ['«Підкажи, яку картку чи вклад обрати»', '“Tell me which bank card or deposit to choose”', ['fin' => 2]],
                ['«Порадь, що вдягнути на важливу подію»', '“Tell me what to wear to an important event”', ['fas' => 2]],
                ['«Глянь, це оригінал чи підробка?»', '“Have a look — is this genuine or a fake?”', ['cus' => 2]],
                ['«У мене кондиціонер дме теплим і капає — що з ним?»', '“My air conditioner blows warm air and drips — what’s wrong?”', ['hva' => 2, 'ref' => 1]],
            ]],
            ['Від чого ти відчуваєш найбільше задоволення?', 'What gives you the most satisfaction?', [
                ['Коли потужна установка, яку ти налаштуєш, працює цілодобово без збоїв', 'When a powerful unit you have set up runs round the clock without failures', ['ref' => 2, 'hva' => 1, 'net' => 1]],
                ['Коли твоя команда разом досягає мети', 'When your team reaches its goal together', ['mgt' => 2]],
                ['Коли хтось із задоволенням їсть те, що ти приготуєш', 'When someone enjoys eating what you have cooked', ['cat' => 2, 'bak' => 1]],
                ['Коли люди обговорюють те, що ти придумаєш і покажеш', 'When people talk about something you came up with and showed them', ['mkt' => 2, 'web' => 1]],
                ['Коли з креслення чи шматка тканини виходить готова річ', 'When a drawing or a piece of fabric turns into a finished item', ['con' => 2, 'fas' => 1]],
                ['Коли все перевірено й сходиться до копійки', 'When everything is checked and adds up to the last penny', ['fin' => 2, 'cus' => 1]],
                ['Коли знаходиш слабке місце, про яке ніхто не здогадувався', 'When you find a weak spot nobody suspected', ['sec' => 2]],
            ]],
            ['Які новини ти відкриєш першими?', 'Which news would you open first?', [
                ['Про нові гаджети, програми та штучний інтелект', 'New gadgets, software and artificial intelligence', ['net' => 2, 'sd' => 1]],
                ['Про курс валют, банки та інвестиції', 'Exchange rates, banks and investments', ['fin' => 2]],
                ['Про хакерські атаки та витоки даних', 'Hacker attacks and data leaks', ['sec' => 2]],
                ['Про модні тренди та нові колекції', 'Fashion trends and new collections', ['fas' => 2, 'con' => 1]],
                ['Про нові заклади та гастрономічні тренди', 'New restaurants and food trends', ['cat' => 2, 'bak' => 1]],
                ['Про міжнародну торгівлю, кордони й постачання', 'International trade, borders and supply chains', ['log' => 2, 'cus' => 1]],
                ['Про успішні стартапи та гучні рекламні кампанії', 'Successful start-ups and high-profile ad campaigns', ['mkt' => 2]],
            ]],
            ['Тобі дали стартовий капітал на власну справу. Що відкриєш?', 'You have been given start-up capital for your own business. What do you open?', [
                ['Пекарню чи кондитерську', 'A bakery or patisserie', ['bak' => 2]],
                ['Кав’ярню з власним меню', 'A café with its own menu', ['cat' => 2]],
                ['Онлайн-магазин із доставкою з-за кордону', 'An online shop with delivery from abroad', ['log' => 2, 'cus' => 1, 'mkt' => 1]],
                ['Сервіс, що встановлює й ремонтує холодильне обладнання для магазинів', 'A service that installs and repairs refrigeration equipment for shops', ['ref' => 2, 'hva' => 1]],
                ['Бренд одягу з власними лекалами', 'A clothing brand with its own patterns', ['con' => 2, 'fas' => 1]],
                ['Нічого — вкладу гроші так, щоб вони працювали', 'Nothing — I would invest the money so it works for me', ['fin' => 2]],
                ['Застосунок чи сайт, який вирішує чиюсь проблему', 'An app or website that solves someone’s problem', ['sd' => 2, 'web' => 1]],
            ]],
            ['Як ти зазвичай розбираєшся з новою річчю?', 'How do you usually get to grips with something new?', [
                ['Уважно читаю інструкцію і роблю крок за кроком', 'I read the instructions carefully and follow them step by step', ['cus' => 1, 'ref' => 1, 'bak' => 1]],
                ['Одразу пробую руками, а розбираюся по ходу', 'I try it hands-on straight away and figure it out as I go', ['hva' => 1, 'ref' => 1, 'cat' => 1]],
                ['Дивлюся, як це роблять інші, і роблю по-своєму', 'I watch how others do it and then do it my own way', ['web' => 1, 'mkt' => 1]],
                ['Малюю схему, як усе між собою пов’язано', 'I draw a diagram of how everything is connected', ['con' => 1, 'sd' => 1]],
                ['Знаходжу того, хто вже знає, і домовляюся про допомогу', 'I find someone who already knows and arrange for their help', ['mgt' => 2, 'log' => 1]],
            ]],
            ['Яку похвалу тобі найприємніше почути?', 'Which compliment would you most like to hear?', [
                ['«Як гарно це придумано!»', '“What a beautiful idea!”', ['web' => 2, 'fas' => 1]],
                ['«Без тебе все б зупинилося»', '“Without you, everything would have stopped”', ['net' => 2, 'hva' => 1]],
                ['«Смакота — дай рецепт!»', '“Delicious — give me the recipe!”', ['bak' => 2, 'cat' => 1]],
                ['«Усе організовано ідеально — дякуємо тобі!»', '“Everything was organised perfectly — thank you!”', ['mgt' => 2, 'log' => 1]],
                ['«Тобі можна довірити будь-які гроші»', '“You could be trusted with any amount of money”', ['fin' => 2, 'cus' => 1]],
                ['«Твоя програма спрацювала з першого разу»', '“Your program worked first time”', ['sd' => 2, 'sec' => 1]],
                ['«Ця річ сидить так, ніби її шили саме на мене»', '“It fits as if it were made just for me”', ['con' => 2, 'fas' => 1]],
            ]],
            ['Якби одне вміння можна було опанувати за день, яким воно було б?', 'If you could master one skill in a day, which would it be?', [
                ['Налаштувати клімат у цілому будинку: тепло взимку, прохолодно влітку', 'Controlling the climate of a whole building: warm in winter, cool in summer', ['hva' => 2]],
                ['Запустити холодильну камеру для складу чи супермаркету', 'Starting up a cold room for a warehouse or supermarket', ['ref' => 2]],
                ['Придумати рекламу, яку друзі пересилатимуть одне одному', 'Creating an ad that friends will forward to each other', ['mkt' => 2]],
                ['Зібрати з нуля мережу для цілого офісу', 'Building a network for a whole office from scratch', ['net' => 2]],
                ['Створити власну капсульну колекцію одягу', 'Creating your own capsule clothing collection', ['fas' => 2]],
            ]],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('quiz_option_scores')) {
            return; // старі міграції викликають сідер до появи таблиці балів
        }

        $programs = self::programIds();
        if ($programs === []) {
            return; // без жодної ОПП квіз не матиме з чим зіставляти відповіді
        }

        foreach (self::questions() as $i => [$text, $textEn, $options]) {
            $question = QuizQuestion::firstOrCreate(
                ['question' => $text],
                ['question_en' => $textEn, 'translation_published' => true, 'sort_order' => $i + 1, 'is_active' => true]
            );

            if ($question->options()->exists()) {
                continue; // питання вже наповнене — не дублюємо варіанти
            }

            foreach ($options as $j => [$label, $labelEn, $weights]) {
                $option = QuizOption::create([
                    'quiz_question_id' => $question->id,
                    'label' => $label,
                    'label_en' => $labelEn,
                    'translation_published' => true,
                    'sort_order' => $j + 1,
                ]);

                foreach ($weights as $key => $points) {
                    if (isset($programs[$key])) {
                        $option->scores()->create(['program_id' => $programs[$key], 'points' => $points]);
                    }
                }
            }
        }
    }

    /** @return array<string, int> ключ => id наявної ОПП */
    public static function programIds(): array
    {
        $normalize = fn (?string $text) => mb_strtolower(str_replace(['’', 'ʼ', '`', '‘'], "'", trim((string) $text)));

        $existing = Program::query()->with('specialty:id,code')->get(['id', 'specialty_id', 'title']);

        $ids = [];
        foreach (self::PROGRAMS as $key => [$code, $title]) {
            $match = $existing->first(fn (Program $p) => $p->specialty?->code === $code && $normalize($p->title) === $normalize($title));
            if ($match) {
                $ids[$key] = $match->id;
            }
        }

        return $ids;
    }
}
