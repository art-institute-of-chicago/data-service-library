<?php

namespace App\Http\Transformers;

use App\Term;
use League\Fractal\TransformerAbstract;

class TermTransformer extends TransformerAbstract
{

    protected $availableIncludes = ['creator_of', 'subject_of'];

    public function transform(Term $term)
    {

        return [
            'id' => $term->id,
            'uri' => $term->uri,
            'title' => $term->title,
        ];

    }

    public function includeSubjectOf(Term $term)
    {
        return $this->collection( $term->subjectOf()->getResults(), new MaterialTransformer, false );
    }


    public function includeCreatorOf(Term $term)
    {
        return $this->collection( $term->creatorOf()->getResults(), new MaterialTransformer, false );
    }

}
