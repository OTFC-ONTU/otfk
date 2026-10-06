<?php

use App\Models\Faq;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Оновлюємо лише незмінені демонстраційні відповіді про вилучені форми.
        $fixtures = [
            [
                'question' => 'Як подати заявку на вступ онлайн?',
                'answer' => 'Заповніть форму «Залишити заявку» на сторінці /zayavka — вкажіть імʼя, телефон і спеціальність, що цікавить. Приймальна комісія зателефонує вам найближчим часом.',
                'replacement' => [
                    'question' => 'Де отримати інформацію про вступ?',
                    'answer' => 'Інформація про вступ є в розділі «Абітурієнту». Звʼяжіться з приймальною комісією за телефоном або електронною поштою зі сторінки «Контакти».',
                    'question_en' => 'Where can I find admission information?',
                    'answer_en' => 'Admission information is available in the Applicants section. Contact the admissions office using the phone number or email address on the Contacts page.',
                    'translation_published' => true,
                ],
            ],
            [
                'question' => 'Як звʼязатися з приймальною комісією?',
                'answer' => 'Телефон і адреса вказані на сторінці «Контакти» та у підвалі сайту. Також можна написати через форму зворотного звʼязку або залишити заявку — ми передзвонимо.',
                'replacement' => [
                    'answer' => 'Телефон, електронна пошта й адреса вказані на сторінці «Контакти» та у підвалі сайту.',
                    'question_en' => 'How can I contact the admissions office?',
                    'answer_en' => 'The phone number, email address and postal address are listed on the Contacts page and in the website footer.',
                    'translation_published' => true,
                ],
            ],
        ];

        foreach ($fixtures as $fixture) {
            Faq::where('question', $fixture['question'])->where('answer', $fixture['answer'])
                ->get()->each(function (Faq $faq) use ($fixture) {
                    $faq->fill($fixture['replacement']);
                    $faq->translation_source_hash = $faq->currentTranslationSourceHash();
                    $faq->saveQuietly();
                });
        }
    }

    public function down(): void
    {
        // Не повертаємо застарілі інструкції про форми, яких більше немає.
    }
};
