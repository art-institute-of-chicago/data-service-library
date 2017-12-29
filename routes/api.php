<?php

use Illuminate\Http\Request;

Route::get('/', function () {
    return redirect('/api/v1');
});

Route::group(['prefix' => 'v1'], function()
{

    $app->get('materials', 'MaterialController@index');
    $app->get('materials/{id}', 'MaterialController@show');

    $app->get('terms', 'TermController@index');
    $app->get('terms/{id}', 'TermController@show');

});
