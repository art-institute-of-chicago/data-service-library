<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var boolean
     */
    public $incrementing = false;

    /**
     * The attributes that aren't mass assignable. Generally,
     * we want all attributes to be mass assignable. Laravel
     * defaults to guarding all attributes.
     *
     * @var array
     */
    protected $guarded = [];

}
