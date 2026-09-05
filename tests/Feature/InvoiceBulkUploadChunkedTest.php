<?php

namespace Tests\Feature;

use App\Models\InvoiceBulkUpload;
use App\Models\InvoiceUploadFile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the "uploaded 40 invoices, only 20 arrived" class of bug.
 *
 * PHP's max_file_uploads silently discards files past the limit before Laravel
 * ever sees $_FILES, so a large selection has to go up as several requests that
 * all append to one batch. These tests pin the append path: the batch is reused,
 * nothing is lost, and a failed chunk cannot destroy files an earlier chunk stored.
 */
class InvoiceBulkUploadChunkedTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @return array<int, UploadedFile> */
    private function pdfs(int $count, string $prefix = 'invoice'): array
    {
        return collect(range(1, $count))
            ->map(fn ($i) => UploadedFile::fake()->create("{$prefix}-{$i}.pdf", 10, 'application/pdf'))
            ->all();
    }

    public function test_a_first_chunk_creates_a_batch(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->actor())
            ->postJson(route('invoices.bulk-upload.upload'), ['files' => $this->pdfs(3)]);

        $response->assertOk()->assertJson(['success' => true]);

        $batch = InvoiceBulkUpload::firstOrFail();
        $this->assertSame(3, $batch->total_files);
        $this->assertSame(3, $batch->files()->count());
        $this->assertNotNull($batch->started_at);
    }

    public function test_later_chunks_append_to_the_same_batch_instead_of_creating_new_ones(): void
    {
        Storage::fake('local');
        $user = $this->actor();

        $first = $this->actingAs($user)
            ->postJson(route('invoices.bulk-upload.upload'), ['files' => $this->pdfs(3, 'a')])
            ->assertOk()
            ->json();

        $batchId = $first['batch_id'];

        $this->actingAs($user)
            ->postJson(route('invoices.bulk-upload.upload'), [
                'files' => $this->pdfs(4, 'b'),
                'batch_id' => $batchId,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'batch_id' => $batchId]);

        // The whole point: one batch, every file present, nothing silently dropped.
        $this->assertSame(1, InvoiceBulkUpload::count());

        $batch = InvoiceBulkUpload::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame(7, $batch->total_files);
        $this->assertSame(7, $batch->files()->count());
        $this->assertSame(7, InvoiceUploadFile::count());
    }

    public function test_started_at_is_not_reset_by_a_later_chunk(): void
    {
        Storage::fake('local');
        $user = $this->actor();

        $batchId = $this->actingAs($user)
            ->postJson(route('invoices.bulk-upload.upload'), ['files' => $this->pdfs(1, 'a')])
            ->json('batch_id');

        $startedAt = InvoiceBulkUpload::where('batch_id', $batchId)->firstOrFail()->started_at;

        $this->travel(5)->minutes();

        $this->actingAs($user)->postJson(route('invoices.bulk-upload.upload'), [
            'files' => $this->pdfs(1, 'b'),
            'batch_id' => $batchId,
        ])->assertOk();

        $this->assertEquals(
            $startedAt->timestamp,
            InvoiceBulkUpload::where('batch_id', $batchId)->firstOrFail()->started_at->timestamp
        );
    }

    public function test_a_chunk_cannot_push_a_batch_over_the_configured_maximum(): void
    {
        Storage::fake('local');
        config(['invoices.bulk_upload.max_files_per_batch' => 5]);
        $user = $this->actor();

        $batchId = $this->actingAs($user)
            ->postJson(route('invoices.bulk-upload.upload'), ['files' => $this->pdfs(4, 'a')])
            ->json('batch_id');

        $this->actingAs($user)
            ->postJson(route('invoices.bulk-upload.upload'), [
                'files' => $this->pdfs(3, 'b'),
                'batch_id' => $batchId,
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(4, InvoiceBulkUpload::where('batch_id', $batchId)->firstOrFail()->files()->count());
    }

    public function test_a_batch_belonging_to_another_user_is_rejected(): void
    {
        Storage::fake('local');

        $batchId = $this->actingAs($this->actor())
            ->postJson(route('invoices.bulk-upload.upload'), ['files' => $this->pdfs(1, 'a')])
            ->json('batch_id');

        $this->actingAs($this->actor())
            ->postJson(route('invoices.bulk-upload.upload'), [
                'files' => $this->pdfs(1, 'b'),
                'batch_id' => $batchId,
            ])
            ->assertStatus(422);

        $this->assertSame(1, InvoiceUploadFile::count());
    }

    public function test_an_unknown_batch_id_is_rejected_rather_than_silently_starting_a_new_batch(): void
    {
        Storage::fake('local');

        $this->actingAs($this->actor())
            ->postJson(route('invoices.bulk-upload.upload'), [
                'files' => $this->pdfs(1),
                'batch_id' => 'BATCH-NOPE-123',
            ])
            ->assertStatus(422);

        $this->assertSame(0, InvoiceBulkUpload::count());
    }

    public function test_a_folder_sized_selection_uploaded_in_chunks_loses_no_files(): void
    {
        Storage::fake('local');
        $user = $this->actor();

        // 35 files against a chunk ceiling of 8 => 5 requests. Before chunking,
        // PHP's max_file_uploads of 20 would have silently discarded 15 of these.
        $total = 35;
        $chunkSize = 8;
        $batchId = null;

        foreach (array_chunk(range(1, $total), $chunkSize) as $chunkIndex => $chunk) {
            $payload = ['files' => $this->pdfs(count($chunk), "chunk{$chunkIndex}")];

            if ($batchId !== null) {
                $payload['batch_id'] = $batchId;
            }

            $batchId = $this->actingAs($user)
                ->postJson(route('invoices.bulk-upload.upload'), $payload)
                ->assertOk()
                ->json('batch_id');
        }

        $this->assertSame(1, InvoiceBulkUpload::count(), 'Chunks must land in one batch.');

        $batch = InvoiceBulkUpload::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame($total, $batch->total_files);
        $this->assertSame($total, $batch->files()->count());

        // Every file is on disk under the one batch folder, with unique stored names.
        $stored = $batch->files()->pluck('stored_filename');
        $this->assertCount($total, $stored->unique());

        foreach ($batch->files as $file) {
            Storage::disk('local')->assertExists($file->temp_path);
        }
    }

    public function test_the_upload_page_exposes_chunk_ceilings_within_the_php_limits(): void
    {
        $response = $this->actingAs($this->actor())->get(route('invoices.bulk-upload.index'));

        $response->assertOk();

        $maxUploads = (int) ini_get('max_file_uploads');
        $chunkMaxFiles = $response->viewData('chunkMaxFiles');

        $this->assertGreaterThan(0, $chunkMaxFiles);
        $this->assertLessThan($maxUploads, $chunkMaxFiles, 'A chunk must stay under PHP max_file_uploads.');
        $this->assertLessThanOrEqual(config('invoices.bulk_upload.max_files_per_batch'), $chunkMaxFiles);
        $this->assertGreaterThan(0, $response->viewData('chunkMaxBytes'));
    }
}
