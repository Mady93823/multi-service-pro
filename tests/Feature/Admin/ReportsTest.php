<?php

use App\Domain\Reports\Actions\ExportReportCsv;
use App\Domain\Reports\ReportFilters;
use App\Jobs\GenerateReportExport;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\ReportExportReady;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EarningsFixtures;
use Tests\Support\FakeHugeReport;

function reportsAdmin(): User
{
    return User::factory()->admin()->create();
}

it('renders each report with columns and rows', function (string $slug) {
    EarningsFixtures::complete(EarningsFixtures::booking());

    $this->actingAs(reportsAdmin())
        ->get(route('admin.reports.show', $slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/reports/show')
            ->where('report.slug', $slug)
            ->has('report.columns')
            ->has('rows.data'));
})->with(['bookings', 'earnings', 'services', 'providers']);

it('404s on an unknown report slug', function () {
    $this->actingAs(reportsAdmin())->get('/admin/reports/nonsense')->assertNotFound();
});

it('blocks non-admins from reports', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.reports.show', 'bookings'))
        ->assertForbidden();
});

it('narrows the bookings report by status and date range', function () {
    $admin = reportsAdmin();

    $completed = EarningsFixtures::complete(EarningsFixtures::booking());
    $old = Booking::factory()->create(['created_at' => now()->subYears(2)]);

    $response = $this->actingAs($admin)->get(route('admin.reports.show', [
        'report' => 'bookings',
        'status' => 'completed',
        'from' => now()->subDay()->toDateString(),
    ]));

    $response->assertOk()->assertInertia(function (AssertableInertia $page) use ($completed, $old) {
        $codes = array_column($page->toArray()['props']['rows']['data'], 'code');

        expect($codes)->toContain($completed->code)
            ->and($codes)->not->toContain($old->code);

        return $page->component('admin/reports/show');
    });
});

it('streams a small export inline as CSV', function () {
    EarningsFixtures::complete(EarningsFixtures::booking());

    $response = $this->actingAs(reportsAdmin())->get(route('admin.reports.export', 'bookings'));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Code')->and($csv)->toContain('Total');
});

it('queues the export when the range is big and a real queue runs', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $admin = reportsAdmin();

    $response = app(ExportReportCsv::class)->handle($admin, new FakeHugeReport, new ReportFilters);

    expect($response)->toBeNull();

    Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job) => $job->adminId === $admin->id);
});

it('stays inline on a sync queue no matter the row count', function () {
    // QUEUE_CONNECTION=sync in phpunit.xml — the M06-style degrade guard.
    $response = app(ExportReportCsv::class)->handle(reportsAdmin(), new FakeHugeReport, new ReportFilters);

    expect($response)->not->toBeNull();
});

it('writes the file and notifies the admin from the queued job', function () {
    Notification::fake();

    $admin = reportsAdmin();
    EarningsFixtures::complete(EarningsFixtures::booking());

    $filename = 'bookings-20260711-120000-test01.csv';

    app()->call([new GenerateReportExport($admin->id, 'bookings', ['from' => null, 'to' => null, 'status' => null], $filename), 'handle']);

    $path = storage_path('app/exports/'.$filename);

    expect(is_file($path))->toBeTrue()
        ->and((string) file_get_contents($path))->toContain('Code');

    Notification::assertSentTo($admin, ReportExportReady::class, fn (ReportExportReady $n) => $n->filename === $filename);

    File::delete($path);
});

it('serves a finished export to admins and rejects bad filenames', function () {
    $dir = storage_path('app/exports');
    File::ensureDirectoryExists($dir);

    $filename = 'bookings-20260711-120000-down01.csv';
    File::put($dir.DIRECTORY_SEPARATOR.$filename, "Code\n");

    $admin = reportsAdmin();

    $this->actingAs($admin)->get(route('admin.exports.download', $filename))->assertOk();

    $this->actingAs($admin)
        ->get('/admin/exports/..%2F..%2F.env')
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.exports.download', $filename))
        ->assertForbidden();

    File::delete($dir.DIRECTORY_SEPARATOR.$filename);
});
