<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Бали, які варіант відповіді квізу додає одній освітньо-професійній програмі. */
class QuizOptionScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['quiz_option_id', 'program_id', 'points'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizOption::class, 'quiz_option_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
