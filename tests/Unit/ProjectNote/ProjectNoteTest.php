<?php

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Carbon\Carbon;

describe('ProjectNote Model', function () {

    describe('Basic Functionality', function () {

        it('can create note with all attributes', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            $note = ProjectNote::factory()->create([
                'project_id' => $project->id,
                'created_by' => $user->id,
                'title' => 'Important Note',
                'content' => 'This is the content of the note',
                'note_date' => '2024-01-15',
            ]);

            expect($note->project_id)->toBe($project->id);
            expect($note->created_by)->toBe($user->id);
            expect($note->title)->toBe('Important Note');
            expect($note->content)->toBe('This is the content of the note');
            expect($note->note_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('can update note', function () {
            $note = ProjectNote::factory()->create([
                'title' => 'Original Title',
                'content' => 'Original Content',
            ]);

            $note->update([
                'title' => 'Updated Title',
                'content' => 'Updated Content',
            ]);

            expect($note->fresh()->title)->toBe('Updated Title');
            expect($note->fresh()->content)->toBe('Updated Content');
        });

        it('can delete note', function () {
            $note = ProjectNote::factory()->create();
            $noteId = $note->id;

            $note->delete();

            expect(ProjectNote::find($noteId))->toBeNull();
        });

        it('can create multiple notes for same project', function () {
            $project = Project::factory()->create();

            ProjectNote::factory()->count(5)->forProject($project)->create();

            expect($project->notes)->toHaveCount(5);
        });
    });

    describe('Date Formatting', function () {

        it('formatted_note_date returns correct format', function () {
            $note = ProjectNote::factory()->create([
                'note_date' => '2024-01-15',
            ]);

            expect($note->formatted_note_date)->toBe('Jan 15, 2024');
        });

        it('formats different dates correctly', function () {
            $testCases = [
                ['2024-01-01', 'Jan 01, 2024'],
                ['2024-12-31', 'Dec 31, 2024'],
                ['2024-06-15', 'Jun 15, 2024'],
            ];

            foreach ($testCases as [$date, $expected]) {
                $note = ProjectNote::factory()->create(['note_date' => $date]);
                expect($note->formatted_note_date)->toBe($expected);
            }
        });

        it('casts note_date to date', function () {
            $note = ProjectNote::factory()->create([
                'note_date' => '2024-01-15',
            ]);

            expect($note->note_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('can create note with today date', function () {
            $note = ProjectNote::factory()->today()->create();

            expect($note->note_date->isToday())->toBeTrue();
        });

        it('handles different date formats', function () {
            $note = ProjectNote::factory()->withDate('2024-03-20')->create();

            expect($note->note_date->format('Y-m-d'))->toBe('2024-03-20');
        });
    });

    describe('Query Scopes', function () {

        it('forProject scope filters notes by project', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            ProjectNote::factory()->count(3)->forProject($project1)->create();
            ProjectNote::factory()->count(2)->forProject($project2)->create();

            $project1Notes = ProjectNote::forProject($project1->id)->get();

            expect($project1Notes)->toHaveCount(3);
            foreach ($project1Notes as $note) {
                expect($note->project_id)->toBe($project1->id);
            }
        });

        it('forProject scope with multiple projects', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();
            $project3 = Project::factory()->create();

            ProjectNote::factory()->count(2)->forProject($project1)->create();
            ProjectNote::factory()->count(3)->forProject($project2)->create();
            ProjectNote::factory()->count(1)->forProject($project3)->create();

            $project2Notes = ProjectNote::forProject($project2->id)->get();

            expect($project2Notes)->toHaveCount(3);
        });

        it('forProject scope returns empty when no notes', function () {
            $project = Project::factory()->create();

            $notes = ProjectNote::forProject($project->id)->get();

            expect($notes)->toBeEmpty();
        });

        it('can combine forProject scope with other queries', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            ProjectNote::factory()->count(2)->forProject($project)->createdBy($user)->create();
            ProjectNote::factory()->count(1)->forProject($project)->create();

            $userNotes = ProjectNote::forProject($project->id)
                ->where('created_by', $user->id)
                ->get();

            expect($userNotes)->toHaveCount(2);
        });
    });

    describe('Relationships', function () {

        it('belongs to project', function () {
            $project = Project::factory()->create();
            $note = ProjectNote::factory()->forProject($project)->create();

            expect($note->project)->toBeInstanceOf(Project::class);
            expect($note->project->id)->toBe($project->id);
        });

        it('belongs to creator', function () {
            $user = User::factory()->create();
            $note = ProjectNote::factory()->createdBy($user)->create();

            expect($note->creator)->toBeInstanceOf(User::class);
            expect($note->creator->id)->toBe($user->id);
        });

        it('project has many notes', function () {
            $project = Project::factory()->create();

            ProjectNote::factory()->count(4)->forProject($project)->create();

            expect($project->notes)->toHaveCount(4);
        });

        it('creator can have multiple notes', function () {
            $user = User::factory()->create();

            ProjectNote::factory()->count(3)->createdBy($user)->create();

            $notes = ProjectNote::where('created_by', $user->id)->get();
            expect($notes)->toHaveCount(3);
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $note = new ProjectNote();

            expect($note->getFillable())->toContain(
                'project_id',
                'created_by',
                'title',
                'content',
                'note_date'
            );
        });

        it('can mass assign fillable attributes', function () {
            $project = Project::factory()->create();
            $user = User::factory()->create();

            $note = ProjectNote::factory()->create([
                'project_id' => $project->id,
                'created_by' => $user->id,
                'title' => 'Test Title',
                'content' => 'Test Content',
                'note_date' => '2024-01-15',
            ]);

            expect($note->project_id)->toBe($project->id);
            expect($note->created_by)->toBe($user->id);
            expect($note->title)->toBe('Test Title');
            expect($note->content)->toBe('Test Content');
        });

        it('has timestamps', function () {
            $note = ProjectNote::factory()->create();

            expect($note->created_at)->not->toBeNull();
            expect($note->updated_at)->not->toBeNull();
            expect($note->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($note->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Note Ordering', function () {

        it('can order notes by date', function () {
            $project = Project::factory()->create();

            $note1 = ProjectNote::factory()->forProject($project)->create(['note_date' => '2024-01-15']);
            $note2 = ProjectNote::factory()->forProject($project)->create(['note_date' => '2024-01-10']);
            $note3 = ProjectNote::factory()->forProject($project)->create(['note_date' => '2024-01-20']);

            $orderedNotes = ProjectNote::forProject($project->id)
                ->orderBy('note_date', 'asc')
                ->get();

            expect($orderedNotes->first()->id)->toBe($note2->id);
            expect($orderedNotes->last()->id)->toBe($note3->id);
        });

        it('can order notes by creation time', function () {
            $project = Project::factory()->create();

            $note1 = ProjectNote::factory()->forProject($project)->create();
            sleep(1);
            $note2 = ProjectNote::factory()->forProject($project)->create();
            sleep(1);
            $note3 = ProjectNote::factory()->forProject($project)->create();

            $orderedNotes = ProjectNote::forProject($project->id)
                ->orderBy('created_at', 'desc')
                ->get();

            expect($orderedNotes->first()->id)->toBe($note3->id);
            expect($orderedNotes->last()->id)->toBe($note1->id);
        });
    });

    describe('Multiple Users Scenarios', function () {

        it('multiple users can create notes for same project', function () {
            $project = Project::factory()->create();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $user3 = User::factory()->create();

            ProjectNote::factory()->forProject($project)->createdBy($user1)->create();
            ProjectNote::factory()->forProject($project)->createdBy($user2)->create();
            ProjectNote::factory()->forProject($project)->createdBy($user3)->create();

            expect($project->notes)->toHaveCount(3);
            
            $creatorIds = $project->notes->pluck('created_by')->toArray();
            expect($creatorIds)->toContain($user1->id, $user2->id, $user3->id);
        });

        it('same user can create multiple notes for different projects', function () {
            $user = User::factory()->create();
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            ProjectNote::factory()->forProject($project1)->createdBy($user)->create();
            ProjectNote::factory()->forProject($project2)->createdBy($user)->create();

            $userNotes = ProjectNote::where('created_by', $user->id)->get();
            
            expect($userNotes)->toHaveCount(2);
            expect($userNotes->pluck('project_id')->toArray())->toContain($project1->id, $project2->id);
        });
    });
});

