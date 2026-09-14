<?php

namespace Tests\Feature\Teach;

use App\Enums\ContentItemType;
use App\Enums\RoleType;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Completion\CompletionFixtures;
use Tests\TestCase;

class TeachHubTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    #[Test]
    public function teach_index_is_accessible_to_instructor(): void
    {
        $offering = $this->offering('TH1');
        $instructor = $this->instructorOn($offering);

        $this->actingAs($instructor)
            ->get(route('teach.index'))
            ->assertOk()
            ->assertSee('TH1');
    }

    #[Test]
    public function teach_index_shows_enrollment_count(): void
    {
        $offering = $this->offering('TH2');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.index'))
            ->assertOk()
            ->assertSee('1');
    }

    #[Test]
    public function teach_show_content_tab_shows_stat_grid(): void
    {
        $offering = $this->offering('TH3');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week one',
            'order' => 1,
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee(__('teach.stat_enrolled'))
            ->assertSee(__('teach.stat_weeks'))
            ->assertSee(__('teach.stat_items'));
    }

    #[Test]
    public function teach_show_content_tab_shows_week_accordion(): void
    {
        $offering = $this->offering('TH4');
        $instructor = $this->instructorOn($offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Foundations of faith',
            'order' => 1,
        ]);
        ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Video,
            'title' => 'Introduction video',
            'order' => 1,
            'status' => 'PUBLISHED',
        ]);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee('Foundations of faith')
            ->assertSee('Introduction video');
    }

    #[Test]
    public function teach_show_roster_tab_shows_enrolled_students(): void
    {
        $offering = $this->offering('TH5');
        $instructor = $this->instructorOn($offering);
        $studentA = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Maria', 'last_name' => 'Mina']);
        $studentB = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Youssef', 'last_name' => 'Hanna']);
        $this->enroll($studentA, $offering);
        $this->enroll($studentB, $offering);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee('Maria Mina')
            ->assertSee('Youssef Hanna');
    }

    #[Test]
    public function teach_show_default_tab_renders_content_panel(): void
    {
        $offering = $this->offering('TH6');
        $instructor = $this->instructorOn($offering);

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('teach.tab_content'));
    }

    #[Test]
    public function academic_admin_can_access_teach_hub(): void
    {
        $offering = $this->offering('TH7');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('teach.index'))
            ->assertOk();
    }

    #[Test]
    public function teach_show_roster_tab_shows_progress_percentage(): void
    {
        $offering = $this->offering('TH8');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create(['first_name' => 'Kirollos', 'last_name' => 'Fayez']);
        $enrollment = $this->enroll($student, $offering);

        $enrollment->update(['progress_percent' => 75.0]);

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'roster']))
            ->assertOk()
            ->assertSee('Kirollos Fayez')
            ->assertSee('75%');
    }
}
