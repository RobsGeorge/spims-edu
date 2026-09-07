<?php

namespace Tests\Feature\Live;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionPost;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\StudentApiFixtures;
use Tests\TestCase;

class DiscussionAttachmentTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    /**
     * @return array{instructor: User, author: User, peer: User, offering: CourseOffering}
     */
    private function enrolledBoard(): array
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $author = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'ATT1',
            'title' => 'Attachment Course',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);

        $this->staffOffering($instructor, $offering);

        foreach ([$author, $peer] as $student) {
            Enrollment::query()->create([
                'student_id' => $student->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        return compact('instructor', 'author', 'peer', 'offering');
    }

    private function fakePdf(string $name = 'handout.pdf'): UploadedFile
    {
        return UploadedFile::fake()
            ->createWithContent($name, "%PDF-1.4\ndiscussion-upload\n")
            ->mimeType('application/pdf');
    }

    #[Test]
    public function student_can_upload_a_reply_attachment_and_peer_can_download(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        ['instructor' => $instructor, 'author' => $author, 'peer' => $peer, 'offering' => $offering] = $this->enrolledBoard();
        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        $thread = app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Open thread',
            'body' => 'Please read the handout',
            'visibility' => ThreadVisibility::Open->value,
        ]);

        $this->actingAs($author)
            ->post(route('discussions.posts.store', $thread), [
                'body' => 'Here is the file',
                'attachments' => [$this->fakePdf('handout.pdf')],
            ])
            ->assertRedirect();

        $post = DiscussionPost::query()
            ->where('thread_id', $thread->id)
            ->where('body', 'Here is the file')
            ->sole();

        $this->assertIsArray($post->attachments);
        $this->assertCount(1, $post->attachments);
        $path = $post->attachments[0]['path'];
        $this->assertStringStartsWith('discussion-attachments/'.$author->id.'/', $path);
        $this->assertSame('handout.pdf', $post->attachments[0]['name']);

        $storage = app(ObjectStorageService::class);
        $this->assertTrue($storage->exists($path));

        $download = route('discussions.posts.attachment', [$post, 0]);
        $html = $this->actingAs($peer)
            ->get(route('discussions.thread', $thread))
            ->assertOk()
            ->assertSee('handout.pdf')
            ->assertSee($download, false)
            ->getContent();

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('name="attachments[]"', $html);
        $this->assertStringNotContainsString('name="file_url"', $html);

        $this->actingAs($peer)
            ->get($download)
            ->assertOk()
            ->assertSee('discussion-upload', false);

        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $this->actingAs($stranger)
            ->get($download)
            ->assertForbidden();
    }

    #[Test]
    public function pasted_file_url_is_rejected_with_422(): void
    {
        ['instructor' => $instructor, 'author' => $author, 'offering' => $offering] = $this->enrolledBoard();
        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        $thread = app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Open thread',
            'body' => 'Hello',
        ]);

        $this->actingAs($author)
            ->postJson(route('discussions.posts.store', $thread), [
                'body' => 'draft',
                'file_url' => 'https://evil.example/notes.pdf',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file_url');

        $this->assertSame(1, DiscussionPost::query()->where('thread_id', $thread->id)->count());
    }

    #[Test]
    public function foreign_storage_path_is_rejected(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        ['instructor' => $instructor, 'author' => $author, 'offering' => $offering] = $this->enrolledBoard();
        $board = app(DiscussionService::class)->provisionBoard($instructor, $offering);
        $thread = app(DiscussionService::class)->createThread($author, $board, [
            'title' => 'Open thread',
            'body' => 'Hello',
        ]);

        $this->expectException(ValidationException::class);

        app(DiscussionService::class)->post(
            $author,
            $thread,
            'stolen',
            null,
            [['path' => 'submissions/'.$author->id.'/01hzzzzzzzzzzzzzzzzzzzzzzz.pdf', 'name' => 'stolen.pdf', 'size' => 12]],
        );
    }

    #[Test]
    public function api_multipart_post_returns_download_url_and_json_body_only_still_works(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        ['author' => $author, 'offering' => $offering] = $this->enrolledBoard();
        $token = $this->apiToken($author);

        $threadId = $this->withToken($token)
            ->postJson(route('api.v1.offerings.discussions.threads.store', $offering), [
                'title' => 'API thread',
                'body' => 'Opening',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson(route('api.v1.discussions.threads.posts', $threadId), [
                'body' => 'A reply',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'A reply')
            ->assertJsonPath('data.attachments', []);

        $created = $this->withToken($token)
            ->post(route('api.v1.discussions.threads.posts', $threadId), [
                'body' => 'File reply',
                'attachment' => $this->fakePdf('notes.pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $created->assertJsonPath('data.body', 'File reply');
        $this->assertSame('notes.pdf', $created->json('data.attachments.0.name'));
        $this->assertNotEmpty($created->json('data.attachments.0.download_url'));
        $this->assertStringNotContainsString('submissions/', (string) $created->json('data.attachments.0.download_url'));

        $post = DiscussionPost::query()->findOrFail($created->json('data.id'));
        $this->assertStringStartsWith('discussion-attachments/'.$author->id.'/', $post->attachments[0]['path']);
        $this->assertTrue(app(ObjectStorageService::class)->exists($post->attachments[0]['path']));
    }

    #[Test]
    public function new_thread_form_accepts_an_opening_attachment(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        ['instructor' => $instructor, 'author' => $author, 'offering' => $offering] = $this->enrolledBoard();
        app(DiscussionService::class)->provisionBoard($instructor, $offering);

        $this->actingAs($author)
            ->from(route('discussions.board', $offering))
            ->post(route('discussions.threads.store', $offering), [
                'title' => 'With file',
                'body' => 'Opening with attachment',
                'attachments' => [$this->fakePdf('syllabus.pdf')],
            ])
            ->assertRedirect();

        $post = DiscussionPost::query()->where('body', 'Opening with attachment')->sole();
        $this->assertSame('syllabus.pdf', $post->attachments[0]['name']);
        $this->assertStringStartsWith('discussion-attachments/'.$author->id.'/', $post->attachments[0]['path']);
        $this->assertTrue(app(ObjectStorageService::class)->exists($post->attachments[0]['path']));

        $this->actingAs($author)
            ->get(route('discussions.thread', $post->thread_id))
            ->assertOk()
            ->assertSee('syllabus.pdf');
    }
}
