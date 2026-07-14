<?php

namespace App\Domain\Security;

use App\Domain\Settings\Groups\RecaptchaGroup;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google reCaptcha v3 (M24).
 *
 * Two rules, both non-negotiable for a product that must install with zero
 * third-party keys:
 *
 * 1. **Inert unless configured.** No site key or no secret key means every
 *    check passes — a fresh install must never refuse a signup because a
 *    service the operator has not signed up for did not answer.
 * 2. **Fail open, not closed.** If Google is unreachable or slow, the visitor
 *    gets through and the failure is logged. A CAPTCHA outage must not become a
 *    registration outage (the same doctrine as a dead Reverb in M07 and a dead
 *    SMS gateway in M23).
 */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** Below this, v3 says the request smells like a bot. */
    private const MIN_SCORE = 0.5;

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function isConfigured(): bool
    {
        return $this->siteKey() !== '' && $this->settings->string('recaptcha.secret_key') !== '';
    }

    public function siteKey(): string
    {
        return $this->settings->string('recaptcha.site_key');
    }

    /** Is the box ticked for this form *and* are the keys in place? */
    public function enabledFor(string $form): bool
    {
        if (! in_array($form, RecaptchaGroup::FORMS, true)) {
            return false;
        }

        return $this->isConfigured() && $this->settings->boolean('recaptcha.on_'.$form);
    }

    /**
     * The site keys the browser may use, per form — safe to share, they are
     * public by design.
     *
     * @return array{site_key: string, forms: array<string, bool>}|null
     */
    public function share(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $forms = [];

        foreach (RecaptchaGroup::FORMS as $form) {
            $forms[$form] = $this->enabledFor($form);
        }

        if (! in_array(true, $forms, true)) {
            return null;
        }

        return ['site_key' => $this->siteKey(), 'forms' => $forms];
    }

    public function verify(?string $token, string $form): bool
    {
        if (! $this->enabledFor($form)) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::timeout(10)->asForm()->post(self::VERIFY_URL, [
                'secret' => $this->settings->string('recaptcha.secret_key'),
                'response' => $token,
            ]);
        } catch (Throwable $e) {
            Log::warning('reCaptcha verification could not be reached; letting the request through.', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        if ($response->failed()) {
            Log::warning('reCaptcha verification failed to answer; letting the request through.', [
                'status' => $response->status(),
            ]);

            return true;
        }

        if ($response->json('success') !== true) {
            return false;
        }

        // v3 always "succeeds"; the score is the verdict. A response with no
        // score at all (v2 keys pasted into a v3 field) is treated as a pass.
        $score = $response->json('score');

        return ! is_numeric($score) || (float) $score >= self::MIN_SCORE;
    }
}
