<?php

namespace Tests\Unit\Support;

use App\Models\Course;
use App\Support\Ui\CourseCoverLibrary;
use App\Support\Ui\HeadingIcon;
use App\Support\Ui\IconVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiPolishSupportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function heading_icon_maps_known_routes(): void
    {
        $this->assertSame('home', HeadingIcon::for('dashboard'));
        $this->assertSame('catalog', HeadingIcon::for('catalog.index'));
        $this->assertSame('teach', HeadingIcon::for('teach.index'));
        $this->assertSame('course', HeadingIcon::for('admin.courses.index'));
        $this->assertSame('finance', HeadingIcon::for('hubs.finance'));
        $this->assertSame('login', HeadingIcon::for('auth.login'));
        $this->assertNull(HeadingIcon::for(null));
    }

    #[Test]
    public function heading_icon_keys_exist_in_vocabulary(): void
    {
        foreach (['dashboard', 'catalog.index', 'teach.show', 'admin.users.index', 'live.index', 'grades.index'] as $route) {
            $key = HeadingIcon::for($route);
            $this->assertNotNull($key, $route);
            $this->assertTrue(IconVocabulary::has($key), $key);
        }
    }

    #[Test]
    public function cover_library_is_deterministic_and_unsplash(): void
    {
        $a = CourseCoverLibrary::urlForSeed('TH101');
        $b = CourseCoverLibrary::urlForSeed('TH101');
        $c = CourseCoverLibrary::urlForSeed('BI102');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertTrue(CourseCoverLibrary::isLibraryUrl($a));
    }

    #[Test]
    public function course_cover_url_falls_back_when_column_empty(): void
    {
        $course = Course::query()->create([
            'code' => 'COV1',
            'title' => 'Cover Fallback',
            'credit_hours' => 2,
        ]);

        $this->assertNull($course->cover_image_url);
        $this->assertTrue(CourseCoverLibrary::isLibraryUrl($course->coverUrl()));
        $this->assertSame(CourseCoverLibrary::urlForSeed('COV1'), $course->coverUrl());
    }

    #[Test]
    public function stored_cover_url_wins_over_library(): void
    {
        $course = Course::query()->create([
            'code' => 'COV2',
            'title' => 'Custom Cover',
            'credit_hours' => 2,
            'cover_image_url' => 'https://example.com/custom.jpg',
        ]);

        $this->assertSame('https://example.com/custom.jpg', $course->coverUrl());
    }
}
