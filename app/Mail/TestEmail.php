<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The "does my SMTP work" email (M23).
 *
 * A real Mailable rather than `Mail::html()` on purpose: this is the one email
 * with no notification behind it, and a Mailable is what the test suite (and a
 * future admin preview) can intercept and assert on.
 */
class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyHtml,
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)->html($this->bodyHtml);
    }
}
