<?php

namespace App\Http\Controllers;

use Aic\Hub\Foundation\AbstractController as BaseController;

class MaterialController extends BaseController
{

    protected $model = \App\Material::class;

    protected $transformer = \App\Http\Transformers\MaterialTransformer::class;

    /**
     * Ensure that the id is a valid Primo doc id.
     *
     * @param string $id
     * @return boolean
     */
    protected function validateId($id)
    {
        $length = strlen(env('PRIMO_API_SOURCE'));

        return substr($id, 0, $length) === env('PRIMO_API_SOURCE') && is_numeric(substr($id, $length));
    }

}
