<?php

namespace App\Http\Transformers;

use App\Material;
use Aic\Hub\Foundation\AbstractTransformer;

class MaterialTransformer extends AbstractTransformer
{

    protected $availableIncludes = ['creators', 'subjects'];

    protected $defaultIncludes = ['creators', 'subjects'];

    public function transform(Material $material)
    {

        return [
            'id' => $material->id,
            'title' => $material->title,
            'date' => $material->date,
        ];

    }

    public function includeSubjects(Material $material)
    {
        return $this->collection( $material->subjects()->getResults(), new TermTransformer, false );
    }


    public function includeCreators(Material $material)
    {
        return $this->collection( $material->creators()->getResults(), new TermTransformer, false );
    }

}
