<?php

namespace Tests\Unit;

use App\Models\CustomerRequestItem;
use PHPUnit\Framework\TestCase;

class CustomerRequestItemTransitionsTest extends TestCase
{
    public function test_every_status_has_a_transition_entry_and_a_label(): void
    {
        foreach (CustomerRequestItem::STATUSES as $status) {
            $this->assertArrayHasKey($status, CustomerRequestItem::ALLOWED_TRANSITIONS);
            $this->assertArrayHasKey($status, CustomerRequestItem::LABELS);
        }

        foreach (CustomerRequestItem::ALLOWED_TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $this->assertContains($to, CustomerRequestItem::STATUSES, "{$from} -> {$to} points at an unknown status");
                $this->assertNotSame($from, $to);
            }
        }
    }

    public function test_happy_path_and_undo_moves(): void
    {
        $item = new CustomerRequestItem(['status' => 'pending']);

        $this->assertTrue($item->canTransitionTo('ordered'));
        $this->assertTrue($item->canTransitionTo('put_aside'));
        $this->assertFalse($item->canTransitionTo('collected'), 'cannot collect what was never put aside');

        $item->status = 'put_aside';
        $this->assertTrue($item->canTransitionTo('collected'));
        $this->assertFalse($item->canTransitionTo('not_available'));

        $item->status = 'collected';
        $this->assertSame(['put_aside'], $item->nextStatuses(), 'collected can only be undone');

        $item->status = 'cancelled';
        $this->assertTrue($item->canTransitionTo('pending'));
    }

    public function test_every_status_is_reachable_from_pending(): void
    {
        $seen = ['pending' => true];
        $queue = ['pending'];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach (CustomerRequestItem::ALLOWED_TRANSITIONS[$current] as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        $this->assertEqualsCanonicalizing(CustomerRequestItem::STATUSES, array_keys($seen));
    }

    public function test_open_status_sets_are_consistent(): void
    {
        foreach (CustomerRequestItem::AWAITING_ARRIVAL as $status) {
            $this->assertContains($status, CustomerRequestItem::OPEN_STATUSES);
        }

        $item = new CustomerRequestItem(['status' => 'put_aside']);
        $this->assertTrue($item->isOpen());

        $item->status = 'collected';
        $this->assertFalse($item->isOpen());
    }

    public function test_done_is_the_short_road_for_a_line_that_is_not_held(): void
    {
        $item = new CustomerRequestItem(['status' => 'pending']);

        // Allowed from any open line that has not been put aside, held or not.
        $this->assertTrue($item->canTransitionTo('done'));
        $item->status = 'ordered';
        $this->assertTrue($item->canTransitionTo('done'));
        $item->status = 'put_aside';
        $this->assertFalse($item->canTransitionTo('done'), 'something put aside is collected, not just done');

        $item->status = 'done';
        $this->assertFalse($item->isOpen());
        $this->assertContains('done', CustomerRequestItem::DONE_STATUSES);
        $this->assertSame('Done', $item->statusLabel());
        $this->assertEqualsCanonicalizing(['ordered', 'pending'], $item->nextStatuses(), 'done can only be undone');
    }

    public function test_a_line_is_held_for_collection_unless_told_otherwise(): void
    {
        $item = new CustomerRequestItem(['status' => 'pending']);

        // Lines taken before the choice existed have no value and keep the old lifecycle.
        $this->assertTrue($item->isHeldForCollection());
        $this->assertSame(['ordered', 'put_aside', 'collected'], array_keys($item->lifecycleSteps()));
        $this->assertSame('Put aside', $item->handlingLabel());

        $item->hold_for_collection = false;
        $this->assertFalse($item->isHeldForCollection());
        $this->assertSame(['ordered', 'done'], array_keys($item->lifecycleSteps()));
        $this->assertSame('Just get it in', $item->handlingLabel());

        // A held line that was marked done anyway shows the short strip, finished.
        $item->hold_for_collection = true;
        $item->status = 'done';
        $this->assertSame(['ordered', 'done'], array_keys($item->lifecycleSteps()));
    }
}
