<?php

namespace Tests\Feature\StaffUi;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Events\EventFixtures;
use Tests\TestCase;

class StaffArabicEventViewportTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;
    use StaffArabicViewportAssertions;

    #[Test]
    public function arabic_event_pages_are_rtl_and_stack_staff_rows(): void
    {
        $admin = $this->arabic($this->admin());

        $this->actingAs($admin)
            ->post(route('admin.events.store'), [
                'title' => 'خلوة الكنيسة',
                'description' => 'ليلة',
                'starts_at' => now()->addDay()->format('Y-m-d\\TH:i'),
                'ends_at' => now()->addDays(2)->format('Y-m-d\\TH:i'),
                'venue' => 'الكنيسة',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'خلوة الكنيسة')->firstOrFail();

        $index = $this->actingAs($admin)->get(route('admin.events.index'));
        $this->assertArabicShell($index);
        $index->assertSee(__('staff.events.title'), false)
            ->assertSee('خلوة الكنيسة', false)
            ->assertSee('spims-staff-row', false);

        $show = $this->actingAs($admin)->get(route('admin.events.show', $event));
        $this->assertArabicShell($show);
        $show->assertSee(__('staff.events.check_in'), false)
            ->assertSee(__('staff.events.publish'), false);
    }
}
