<?php

use App\Models\ExternalAccess;
use App\Models\Project;

describe('ExternalAccess Model', function () {

    describe('Token & Password Generation', function () {

        it('generateForProject creates access with random token', function () {
            $project = Project::factory()->create();

            $access = ExternalAccess::generateForProject($project->id);

            expect($access)->toBeInstanceOf(ExternalAccess::class);
            expect($access->project_id)->toBe($project->id);
            expect($access->access_token)->not->toBeNull();
            expect($access->password)->not->toBeNull();
        });

        it('token is 32 characters long', function () {
            $project = Project::factory()->create();

            $access = ExternalAccess::generateForProject($project->id);

            expect(strlen($access->access_token))->toBe(32);
        });

        it('password is 8 characters long', function () {
            $project = Project::factory()->create();

            $access = ExternalAccess::generateForProject($project->id);

            expect(strlen($access->password))->toBe(8);
        });

        it('new access is active by default', function () {
            $project = Project::factory()->create();

            $access = ExternalAccess::generateForProject($project->id);

            expect($access->is_active)->toBeTrue();
        });

        it('multiple generations create unique tokens', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            $access1 = ExternalAccess::generateForProject($project1->id);
            $access2 = ExternalAccess::generateForProject($project2->id);

            expect($access1->access_token)->not->toBe($access2->access_token);
            expect($access1->password)->not->toBe($access2->password);
        });

        it('can create access using factory', function () {
            $access = ExternalAccess::factory()->create();

            expect($access->access_token)->not->toBeNull();
            expect($access->password)->not->toBeNull();
            expect(strlen($access->access_token))->toBe(32);
            expect(strlen($access->password))->toBe(8);
        });

        it('can create access with custom credentials', function () {
            $access = ExternalAccess::factory()->withCredentials('custom-token-123456789012345678', '12345678')->create();

            expect($access->access_token)->toBe('custom-token-123456789012345678');
            expect($access->password)->toBe('12345678');
        });
    });

    describe('Access Tracking', function () {

        it('updateLastAccessed updates timestamp', function () {
            $access = ExternalAccess::factory()->create(['last_accessed_at' => null]);

            expect($access->last_accessed_at)->toBeNull();

            $access->updateLastAccessed();

            expect($access->fresh()->last_accessed_at)->not->toBeNull();
        });

        it('multiple access updates change timestamp', function () {
            $access = ExternalAccess::factory()->create(['last_accessed_at' => null]);

            $access->updateLastAccessed();
            $firstAccess = $access->fresh()->last_accessed_at;

            sleep(1);

            $access->fresh()->updateLastAccessed();
            $secondAccess = $access->fresh()->last_accessed_at;

            expect($firstAccess)->not->toBeNull();
            expect($secondAccess)->not->toBeNull();
            expect($secondAccess->greaterThan($firstAccess))->toBeTrue();
        });

        it('can create access with last accessed time', function () {
            $access = ExternalAccess::factory()->accessed()->create();

            expect($access->last_accessed_at)->not->toBeNull();
        });

        it('last_accessed_at is null by default', function () {
            $access = ExternalAccess::factory()->create();

            expect($access->last_accessed_at)->toBeNull();
        });
    });

    describe('Status Management', function () {

        it('can set access as inactive', function () {
            $access = ExternalAccess::factory()->inactive()->create();

            expect($access->is_active)->toBeFalse();
        });

        it('can toggle active status', function () {
            $access = ExternalAccess::factory()->create(['is_active' => true]);

            expect($access->is_active)->toBeTrue();

            $access->update(['is_active' => false]);

            expect($access->fresh()->is_active)->toBeFalse();
        });

        it('is_active casts to boolean', function () {
            $access = ExternalAccess::factory()->create(['is_active' => 1]);

            expect($access->is_active)->toBeTrue();
            expect($access->is_active)->toBeBool();

            $access2 = ExternalAccess::factory()->create(['is_active' => 0]);

            expect($access2->is_active)->toBeFalse();
            expect($access2->is_active)->toBeBool();
        });

        it('can filter active access', function () {
            ExternalAccess::factory()->count(3)->create(['is_active' => true]);
            ExternalAccess::factory()->count(2)->inactive()->create();

            $activeAccess = ExternalAccess::where('is_active', true)->get();

            expect($activeAccess)->toHaveCount(3);
        });
    });

    describe('Relationships', function () {

        it('belongs to project', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::factory()->forProject($project)->create();

            expect($access->project)->toBeInstanceOf(Project::class);
            expect($access->project->id)->toBe($project->id);
        });

        it('project has one external access', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::factory()->forProject($project)->create();

            expect($project->externalAccess)->toBeInstanceOf(ExternalAccess::class);
            expect($project->externalAccess->id)->toBe($access->id);
        });

        it('project can regenerate external access', function () {
            $project = Project::factory()->create();
            $oldAccess = ExternalAccess::factory()->forProject($project)->create();
            $oldToken = $oldAccess->access_token;

            $newAccess = $project->generateExternalAccess();

            expect($newAccess)->toBeInstanceOf(ExternalAccess::class);
            expect($newAccess->access_token)->not->toBe($oldToken);
            expect(ExternalAccess::find($oldAccess->id))->toBeNull(); // Old access should be deleted
        });
    });

    describe('Model Attributes', function () {

        it('has correct fillable attributes', function () {
            $access = new ExternalAccess();

            expect($access->getFillable())->toContain(
                'project_id',
                'access_token',
                'password',
                'is_active',
                'last_accessed_at',
                'migration_generated'
            );
        });

        it('casts last_accessed_at to datetime', function () {
            $access = ExternalAccess::factory()->accessed()->create();

            expect($access->last_accessed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });

        it('casts is_active to boolean', function () {
            $access = ExternalAccess::factory()->create(['is_active' => true]);

            expect($access->is_active)->toBeBool();
        });

        it('can set migration_generated flag', function () {
            $access = ExternalAccess::factory()->migrationGenerated()->create();

            expect($access->migration_generated)->toBeTrue();
        });

        it('migration_generated is false by default', function () {
            $access = ExternalAccess::factory()->create();

            expect($access->migration_generated)->toBeFalse();
        });

        it('has timestamps', function () {
            $access = ExternalAccess::factory()->create();

            expect($access->created_at)->not->toBeNull();
            expect($access->updated_at)->not->toBeNull();
            expect($access->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
            expect($access->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('Table Name', function () {

        it('uses external_access table', function () {
            $access = new ExternalAccess();

            expect($access->getTable())->toBe('external_access');
        });
    });

    describe('Access Security', function () {

        it('generates different tokens for same project if regenerated', function () {
            $project = Project::factory()->create();

            $access1 = ExternalAccess::generateForProject($project->id);
            $token1 = $access1->access_token;
            $access1->delete();

            $access2 = ExternalAccess::generateForProject($project->id);
            $token2 = $access2->access_token;

            expect($token1)->not->toBe($token2);
        });

        it('token is alphanumeric', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect(ctype_alnum($access->access_token))->toBeTrue();
        });

        it('password is alphanumeric', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::generateForProject($project->id);

            expect(ctype_alnum($access->password))->toBeTrue();
        });
    });

    describe('Multiple Projects', function () {

        it('different projects can have different access tokens', function () {
            $project1 = Project::factory()->create();
            $project2 = Project::factory()->create();

            $access1 = ExternalAccess::factory()->forProject($project1)->create();
            $access2 = ExternalAccess::factory()->forProject($project2)->create();

            expect($access1->access_token)->not->toBe($access2->access_token);
            expect($access1->project_id)->not->toBe($access2->project_id);
        });

        it('can query access by project', function () {
            $project = Project::factory()->create();
            $access = ExternalAccess::factory()->forProject($project)->create();

            $foundAccess = ExternalAccess::where('project_id', $project->id)->first();

            expect($foundAccess)->not->toBeNull();
            expect($foundAccess->id)->toBe($access->id);
        });
    });
});

