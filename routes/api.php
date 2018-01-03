<?php

use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect('/api/v1');
});

Route::group(['prefix' => 'v1'], function()
{

    Route::get('materials', 'MaterialController@index');
    Route::get('materials/{id}', 'MaterialController@show');

    Route::get('terms', 'TermController@index');
    Route::get('terms/{id}', 'TermController@show');

});
