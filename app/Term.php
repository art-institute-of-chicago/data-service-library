<?php

namespace App;

use App\BaseModel;

class Term extends BaseModel
{

    public function creatorOf()
    {

        return $this->belongsToMany('App\Material', 'material_creator');

    }

    public function subjectOf()
    {

        return $this->belongsToMany('App\Material', 'material_subject');

    }

}
