<?php

namespace App\Http\Controllers;

class TermController extends Controller
{

    protected $model = \App\Term::class;

    protected $transformer = \App\Http\Transformers\TermTransformer::class;

    /**
     * Ensure that the id is a valid Library of Congress control number (LCCN).
     *
     * @param string $id
     * @return boolean
     */
    protected function validateId( $id )
    {

        return preg_match('/[a-z]+[0-9]+/', $id);

    }

}
