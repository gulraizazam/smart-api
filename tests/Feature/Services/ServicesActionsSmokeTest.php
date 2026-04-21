<?php

declare(strict_types=1);

use App\Models\Services;
use App\Models\User;

/**
 * End-to-end smoke for every action button on the Services list page —
 * status toggle, duplicate, edit, delete, category sort, per-parent sort.
 * Skipped when the test DB doesn't have a Services row to operate on.
 */
beforeEach(function () {
    $this->admin = User::orderBy('id')->first();
    $this->row = Services::whereNotNull('parent_id')->where('account_id', $this->admin?->account_id)->first();
    if (! $this->admin || ! $this->row) {
        $this->markTestSkipped('No admin / services row in test DB.');
    }
    $this->actingAs($this->admin);
});

it('toggles status', function () {
    $orig = (int) $this->row->active;
    $r = $this->postJson('/api/services/status', ['id' => $this->row->id, 'status' => $orig ? 0 : 1]);
    expect($r->status())->toBe(200);
    // restore
    $this->postJson('/api/services/status', ['id' => $this->row->id, 'status' => $orig]);
});

it('returns show payload (edit prefill)', function () {
    $r = $this->getJson('/api/services/'.$this->row->id);
    expect($r->status())->toBe(200);
});

it('returns duplicate prefill', function () {
    $r = $this->getJson('/api/services/'.$this->row->id.'/duplicate');
    expect($r->status())->toBe(200);
});

it('returns 4xx on delete of non-existent service (not 500)', function () {
    $r = $this->deleteJson('/api/services/999999999');
    expect($r->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
});

it('saves category sort with item_ids key', function () {
    $ids = Services::whereNull('parent_id')->where('account_id', $this->admin->account_id)->limit(3)->pluck('id')->toArray();
    $r = $this->postJson('/api/services_category_sort_save', ['item_ids' => $ids]);
    expect($r->status())->toBe(200);
});

it('saves per-parent sort', function () {
    $ids = Services::where('parent_id', $this->row->parent_id)->limit(3)->pluck('id')->toArray();
    $r = $this->postJson('/api/services/sort/save', [
        'parent_id' => $this->row->parent_id,
        'item_ids' => $ids,
    ]);
    expect($r->status())->toBe(200);
});
