<?php

namespace App;

use App\BaseModel;

class Material extends BaseModel
{

    public function creators()
    {

        return $this->belongsToMany('App\Term', 'material_creator');

    }

    public function subjects()
    {

        return $this->belongsToMany('App\Term', 'material_subject');

    }

}
