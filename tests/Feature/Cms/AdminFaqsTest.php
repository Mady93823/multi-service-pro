<?php

use App\Models\Faq;
use App\Models\User;

function faqAdmin(): User
{
    return User::factory()->admin()->create();
}

it('shows active faqs on the storefront home, sorted, hiding inactive ones', function () {
    $first = Faq::factory()->create(['question' => 'Zero-order question?', 'sort_order' => 0]);
    $hidden = Faq::factory()->inactive()->create();

    // The FAQ section is a block on the home page since M20.
    /** @var array{props: array{blocks: list<array<string, mixed>>}} $page */
    $page = $this->get('/')->assertOk()->viewData('page');

    $questions = collect(collect($page['props']['blocks'])->firstWhere('type', 'faq')['props']['faqs'])->pluck('question');

    expect($questions->first())->toBe($first->question)
        ->and($questions)->not->toContain($hidden->question);
});

it('creates a faq', function () {
    $this->actingAs(faqAdmin())->post(route('admin.faqs.store'), [
        'question' => 'Do you serve my area?',
        'answer' => 'Enter your address at checkout to see availability.',
        'is_active' => true,
        'sort_order' => 10,
    ])->assertRedirect(route('admin.faqs.index'));

    expect(Faq::query()->where('question', 'Do you serve my area?')->exists())->toBeTrue();
});

it('updates and deletes a faq', function () {
    $faq = Faq::factory()->create();
    $admin = faqAdmin();

    $this->actingAs($admin)->put(route('admin.faqs.update', $faq), [
        'question' => $faq->question,
        'answer' => 'Updated answer.',
        'is_active' => false,
        'sort_order' => 3,
    ])->assertRedirect(route('admin.faqs.index'));

    expect($faq->refresh())
        ->answer->toBe('Updated answer.')
        ->is_active->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.faqs.destroy', $faq));

    expect(Faq::query()->whereKey($faq->id)->exists())->toBeFalse();
});

it('validates the faq payload', function () {
    $this->actingAs(faqAdmin())
        ->from(route('admin.faqs.create'))
        ->post(route('admin.faqs.store'), ['question' => '', 'answer' => ''])
        ->assertSessionHasErrors(['question', 'answer']);
});

it('blocks non-admins from managing faqs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.faqs.index'))
        ->assertForbidden();
});
