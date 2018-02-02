<?php

namespace Spatie\QueryBuilder\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Tests\Models\TestModel;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;

class IncludeTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp()
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();

        $this->models->each(function (TestModel $model) {
            $model
                ->relatedModels()->create(['name' => 'Test'])
                ->nestedRelatedModels()->create(['name' => 'Test']);
        });
    }

    /** @test */
    public function it_does_not_require_includes()
    {
        $models = QueryBuilder::for(TestModel::class, new Request())
            ->allowedIncludes('related-models')
            ->get();

        $this->assertCount(TestModel::count(), $models);
    }

    /** @test */
    public function it_can_include_model_relations()
    {
        $models = $this
            ->createQueryFromIncludeRequest('related-models')
            ->allowedIncludes('related-models')
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_nested_model_relations()
    {
        $models = $this
            ->createQueryFromIncludeRequest('related-models.nested-related-models')
            ->allowedIncludes('related-models.nested-related-models')
            ->get();

        $models->each(function (Model $model) {
            $this->assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
        });
    }

    /** @test */
    public function it_can_include_case_insensitive()
    {
        $models = $this
            ->createQueryFromIncludeRequest('RelaTed-Models')
            ->allowedIncludes('related-models')
            ->get();

        $this->assertRelationLoaded($models, 'relatedModels');
    }

    /** @test */
    public function it_can_include_models_on_an_empty_collection()
    {
        TestModel::query()->delete();

        $models = $this
            ->createQueryFromIncludeRequest('related-models')
            ->allowedIncludes('related-models')
            ->get();

        $this->assertCount(0, $models);
    }

    /** @test */
    public function it_guards_against_invalid_includes()
    {
        $this->expectException(InvalidQuery::class);

        $this
            ->createQueryFromIncludeRequest('random-model')
            ->allowedIncludes('related-models');
    }

    protected function createQueryFromIncludeRequest(string $includes): QueryBuilder
    {
        $request = new Request([
            'include' => $includes,
        ]);

        return QueryBuilder::for(TestModel::class, $request);
    }

    protected function assertRelationLoaded(Collection $collection, string $relation)
    {
        $hasModelWithoutRelationLoaded = $collection
            ->contains(function (Model $model) use ($relation) {
                return ! $model->relationLoaded($relation);
            });

        $this->assertFalse($hasModelWithoutRelationLoaded, "The `{$relation}` relation was expected but not loaded.");
    }
}
