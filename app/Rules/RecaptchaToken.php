<?php

namespace App\Rules;

use App\Domain\Security\Recaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `'recaptcha_token' => RecaptchaToken::rules('register')` (M24).
 *
 * Use the factory, not the constructor. Laravel does not run a non-implicit
 * rule on a missing key — nor on an empty string — so a bot that simply strips
 * the field would sail past a rule attached with `nullable`. The factory adds
 * `required` **exactly when the form is protected**, which is what makes "no
 * token" fail the same way "a bad token" does.
 *
 * When reCaptcha is not configured, or this form's switch is off, the rules
 * collapse to `nullable|string`: a fresh install never asks a visitor to prove
 * themselves to a service the operator has not signed up for.
 */
class RecaptchaToken implements ValidationRule
{
    public function __construct(private readonly string $form) {}

    /**
     * @return list<mixed>
     */
    public static function rules(string $form): array
    {
        if (! app(Recaptcha::class)->enabledFor($form)) {
            return ['nullable', 'string'];
        }

        return ['required', 'string', new self($form)];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $recaptcha = app(Recaptcha::class);

        if (! $recaptcha->enabledFor($this->form)) {
            return;
        }

        if (! $recaptcha->verify(is_string($value) ? $value : null, $this->form)) {
            $fail(__('We could not verify that you are human. Please try again.'));
        }
    }
}
