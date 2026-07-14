<?php

namespace App\Models;

use App\Domain\Comms\Enums\NotificationEvent;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin's override of one notification's email (M23, ADR D25).
 *
 * The row is an *optional layer*: the shipped default sits underneath and is
 * used whenever this row is missing, disabled, or fails to render. Body is
 * markdown source, rendered through MarkdownRenderer (D20) — never HTML.
 *
 * @property string $event_key
 * @property string $subject
 * @property string $body
 * @property bool $is_enabled
 *
 * @method static Builder<static> query()
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'event_key',
        'subject',
        'body',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function event(): ?NotificationEvent
    {
        return NotificationEvent::tryFrom($this->event_key);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
