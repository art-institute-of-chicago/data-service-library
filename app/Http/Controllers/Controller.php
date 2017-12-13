<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Illuminate\Support\Facades\Input;
use Closure;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Manager;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{

    protected $model;

    protected $transformer;

    private $fractal;

    public function __construct(Manager $fractal)
    {
        $this->fractal = $fractal;
    }

    const LIMIT_MAX = 1000;

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  mixed $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {

        return $this->select( $request, function( $id ) {

            return $this->find($id);

        });

    }


    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        return $this->collect( $request, function( $limit ) {

            return $this->paginate( $limit );

        });

    }


    /**
     * Call to find specific id(s). Override this method when logic to get
     * a model is more complex than a simple `$model::find($id)` call.
     *
     * @param mixed $ids
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function find($ids)
    {

        return ($this->model)::find($ids);

    }


    /**
     * Call to get a model list. Override this method when logic to get
     * models is more complex than a simple `$model::paginate($limit)` call.
     *
     * @param int $limit
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function paginate($limit)
    {

        return ($this->model)::paginate($limit);

    }


    /**
     * Return a single resource. Not meant to be called directly in routes.
     * `$callback` should return an Eloquent Model.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $callback
     * @return \Illuminate\Http\Response
     */
    protected function select( Request $request, Closure $callback )
    {

        // Technically this will never be called, b/c we only bind Route.get
        if ($request->method() != 'GET')
        {
            return $this->respondMethodNotAllowed();
        }

        // https://github.com/laravel/lumen-framework/issues/119
        $id = $request->route()[2]['id'] ?? null;

        if (!$this->validateId( $id ))
        {
            return $this->respondInvalidSyntax();
        }

        // TODO: Improve exception handling via Handler
        $item = $callback( $id );

        if (!$item)
        {
            return $this->respondNotFound();
        }

        return $this->item($item, new $this->transformer);

    }


    /**
     * Return a list of resources. Not meant to be called directly in routes.
     * `$callback` should return an Eloquent Collection.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $callback
     * @return \Illuminate\Http\Response
     */
    protected function collect( Request $request, Closure $callback )
    {

        // Technically this will never be called, b/c we only bind Route.get
        if ($request->method() != 'GET')
        {
            return $this->respondMethodNotAllowed();
        }

        // Process ?ids= query param
        $ids = $request->input('ids');

        if ($ids)
        {
            return $this->showMutliple($ids);
        }

        // Check if the ?limit= is too big
        $limit = $request->input('limit') ?: 12;

        if ($limit > static::LIMIT_MAX)
        {
            return $this->respondBigLimit();
        }

        // This would happen for subresources
        // https://github.com/laravel/lumen-framework/issues/119
        $id = $request->route()[2]['id'] ?? null;

        // Assumes the inheriting class set model and transformer
        $all = $callback( $limit, $id );

        return $this->collection($all, new $this->transformer);

    }


    /**
     * Display multiple resources.
     *
     * @param string $ids
     * @return \Illuminate\Http\Response
     */
    private function showMutliple($ids = '')
    {

        $ids = explode(',',$ids);

        if (count($ids) > static::LIMIT_MAX)
        {
            return $this->respondTooManyIds();
        }

        // Validate the syntax for each $id
        foreach( $ids as $id )
        {

            if (!$this->validateId( $id ))
            {
                return $this->respondInvalidSyntax();
            }

        }

        $all = $this->find($ids);

        return $this->collection($all, new $this->transformer);

    }


    /**
     * Validate `id` route or query string param format. By default, only
     * numeric ids greater than zero are accepted. Override this method in
     * child classes to implement different validation rules (e.g. UUID).
     *
     * @TODO Move this logic to the base model classes?
     *
     * @param mixed $id
     * @return boolean
     */
    protected function validateId( $id )
    {

        // By default, only allow numeric ids greater than 0
        return is_numeric($id) && intval($id) > 0;

    }


    /**
     * Helper method that transforms a paginated collection of model instances using Fractal and returns it as a response.
     * In Laravel, we would define this as a response macro.
     *
     * @param array $model \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @param array $transformer \League\Fractal\TransformerAbstract
     *
     * @return \Illuminate\Http\Response
     */
    private function collection($all, $transformer)
    {

        $collection = new Collection($all, $transformer);

        $data = $this->fractal->createData($collection)->toArray();

        if ($all instanceof LengthAwarePaginator)
        {

            $paginator = [
                'total' => $all->total(),
                'limit' => (int) $all->perPage(),
                'offset' => (int) $all->perPage() * ( $all->currentPage() - 1 ),
                'total_pages' => $all->lastPage(),
                'current_page' => $all->currentPage(),
            ];

            if ($all->previousPageUrl()) {
                $paginator['prev_url'] = $all->previousPageUrl() .'&limit=' .$all->perPage();
            }

            if ($all->hasMorePages()) {
                $paginator['next_url'] = $all->nextPageUrl() .'&limit=' .$all->perPage();
            }

            $data = array_merge(['pagination' => $paginator], $data);

        }

        return $this->respond($data);

    }


    /**
     * Helper method that transforms a model instance using Fractal and returns it as a response.
     * In Laravel, we would define this as a response macro.
     *
     * @param array $model \Illuminate\Database\Eloquent\Model
     * @param array $transformer \League\Fractal\TransformerAbstract
     *
     * @return \Illuminate\Http\Response
     */
    private function item($model, $transformer)
    {

        $item = new Item($model, $transformer);

        $data = $this->fractal->createData($item)->toArray();

        return $this->respond($data);

    }


    /**
     * Helper method to return successful JSON response.
     * Use this instead of Laravel's response() helper.
     *
     * @param array $data
     * @param array $headers (optional)
     *
     * @return \Illuminate\Http\Response
     */
    private function respond($data, $headers = [])
    {
        return response()->json($data, Response::HTTP_OK, $headers);
    }


    /**
     * Helper method for returning errors in JSON.
     *
     * @param string $message
     * @param string $detail
     * @param int $status (optional)
     *
     * @return \Illuminate\Http\Response
     */
    private function error($message, $detail, $status = 500)
    {

        return response()->json([
            'status' => $status,
            'error' => $message,
            'detail' => $detail,
        ], $status);

    }

    // TODO: These should likely be moved to Exceptions
    // https://stackoverflow.com/questions/28944097/laravel-5-handle-exceptions-when-request-wants-json
    private function respondNotFound($message = 'Not found', $detail = 'The item you requested cannot be found.')
    {
        return $this->error($message, $detail, Response::HTTP_NOT_FOUND);
    }

    private function respondInvalidSyntax($message = 'Invalid syntax', $detail = 'The identifier is invalid.')
    {
        return $this->error($message, $detail, Response::HTTP_BAD_REQUEST);
    }

    private function respondFailure($message = 'Failed request', $detail = 'The request failed.')
    {
        return $this->error($message, $detail, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function respondForbidden($message = 'Forbidden', $detail = 'This request is forbidden.')
    {
        return $this->error($message, $detail, Response::HTTP_FORBIDDEN);
    }

    private function respondTooManyIds($message = 'Invalid number of ids', $detail = 'You have requested too many ids. Please send a smaller amount.')
    {
        return $this->error($message, $detail, Response::HTTP_FORBIDDEN);
    }

    private function respondBigLimit($message = 'Invalid limit', $detail = 'You have requested too many resources. Please set a smaller limit.')
    {
        return $this->error($message, $detail, Response::HTTP_FORBIDDEN);
    }

    private function respondMethodNotAllowed($message = 'Method not allowed', $detail = 'Method not allowed.')
    {
        return $this->error($message, $detail, Response::HTTP_METHOD_NOT_ALLOWED);
    }

}
