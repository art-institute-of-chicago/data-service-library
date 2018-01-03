<?php

namespace App\Console\Commands;

use App\Material;
use App\Term;

use GrahamCampbell\Flysystem\Facades\Flysystem;

use Aic\Hub\Foundation\AbstractCommand;

class LibraryImport extends AbstractCommand
{

    protected $signature = 'library:import';

    protected $description = "Import downloaded Special Collections from the R&BL";

    public function handle()
    {

        // Define the base directory for globbing
        $directory = storage_path('app');

        // List all JSON files in storage/app
        $paths = glob( $directory . '/*.json' );

        // Turn this into a collection
        $paths = collect( $paths );

        // Uncomment for testing
        // $paths = $paths->slice(0,2);

        // Turn the full paths to relative for Flysystem
        $files = $paths->map( function( $path ) use ( $directory ) {
            // +1 to remove the starting forwardslash
            return substr( $path, strlen( $directory ) + 1 );
        });

        // Process each matching file
        $paths->map( [$this, 'processFile'] );

    }

    /**
     * Process a single file containing `docs` from Primo's PNX API.
     *
     * @param string $path  Path to JSON file relative to Flysystem root
     * @return array
     */
    public function processFile( $path )
    {

        $file = basename( $path );

        $contents = Flysystem::read( $file );

        $json = json_decode( $contents );

        $docs = collect( $json->docs );

        // Uncomment for testing
        // $docs = $docs->slice(0,5);

        return $docs->map( [ $this, 'processDoc' ] );

    }

    /**
     * Process a single doc from Primo's PNX API.
     *
     * @param object $doc
     * @return array
     */
    public function processDoc( $doc )
    {

        $links = $this->getLinks( $doc );

        $source = (object) [

            'id' => $this->unwrap( $doc->pnx->control->recordid ),
            'title' => $this->unwrap( $doc->pnx->display->title ),
            'date' => $this->unwrap( $doc->pnx->search->creationdate ?? null ),
            'creators' => $this->filterLinks( $links, 'creatorcontrib' ),
            'subjects' => $this->filterLinks( $links, 'subject' ),

            // 'language' => $doc->pnx->display->language,

        ];

        $creators = $source->creators->map( [$this, 'processTerm'] );
        $subjects = $source->subjects->map( [$this, 'processTerm'] );

        $material = Material::findOrNew( $source->id );
        $material->id = $source->id;
        $material->title = $source->title;
        $material->date = $source->date;
        $material->save();

        $material->creators()->sync( $creators->all() );
        $material->subjects()->sync( $subjects->all() );

        $this->info('Imported Material #' . $material->id .': ' . $material->title );

        return $source;

    }

    public function processTerm( $source )
    {

        $term = Term::findOrNew( $source['id'] );
        $term->id = $source['id'];
        $term->uri = $source['uri'];
        $term->title = $source['title'];
        $term->save();

        $this->info('Imported Term #' . $term->id .': ' . $term->title );

        return $term->id;

    }

    /**
     * Retrieves all links that match a given key. In many cases, multiple links will match.
     * This reflects the reality that a doc may have multiple subjects, creators, etc.
     *
     * @param \Illuminate\Support\Collection $links
     * @param string $key
     * @return array
     */
    private function filterLinks( $links, $key )
    {

        $matches = $links->filter( function( $link ) use ( $key ) {
            return $link['key'] === $key;
        });

        // Remove the 'key' key
        $cleaned = $matches->transform( function( $link ) {
            return collect($link)->except(['key'])->all();
        });

        // Without calling values, integer keys can remain:
        // https://laravel.com/docs/5.4/collections#method-values
        return $cleaned->values();

    }

    /**
     * Parse a single doc's `links.uri` array into something more filterable.
     * Use this in conjunction with `$this->filterLinks( $links, $key )`.
     *
     * @param object $doc
     * @return \Illuminate\Support\Collection
     */
    private function getLinks( $doc )
    {

        // Handle this like a collection
        $uris = collect( $doc->pnx->links->uri ?? [] );

        // $uri is an array of delimited strings
        $links = $uris->map( function( $uri ) {

            // Explode by the delimiter
            $parts = explode( '$$', $uri );

            // These strings start with $$, so the first element should be discarded
            array_shift( $parts );

            // Turn it into a collection
            $parts = collect( $parts );

            // Get Library of Congress data
            $lc = $this->getLcData( $parts );

            return [
                'id' => $lc['id'],
                'uri' => $lc['uri'],
                'title' => $this->getLinkPart( $parts, 'V' ),
                'key' => $this->getLinkPart( $parts, 'A' ),
            ];

        });

        return $links;

    }

    /**
     * Return the Library of Congress control number, given a collection of link parts.
     * These control numbers could be transformed to URIs downstream if desired.
     *
     * @link http://www.loc.gov/marc/lccn_structure.html
     * @link https://stackoverflow.com/questions/7395049/get-last-part-of-url-php
     *
     * @param \Illuminate\Support\Collection $parts
     * @return string
     */
    private function getLcData( $parts )
    {

        $part = $this->getLinkPart( $parts, 'U(uri) http://id.loc.gov' );

        return [
            'id' => basename( $part ),
            'uri' => 'http://id.loc.gov' . $part,
        ];

    }

    /**
     * Given a collection made by splitting single `$$`-delimited string from Primo's `docs.links.uri`
     * array, return the first part of that string that matches a given prefix (i.e. key).
     *
     * @param \Illuminate\Support\Collection $parts
     * @param string $key
     * @return string
     */
    private function getLinkPart( $parts, $key )
    {

        $prefixed = $parts->filter( function( $part ) use ( $key ) {
            return $this->startsWith( $part, $key );
        });

        $cleaned = $prefixed->map( function( $string ) use ( $key ) {
            return substr( $string, strlen( $key ) );
        });

        return $cleaned->first();

    }

    /**
     * Check if one string matches the beginning of another string.
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    private function startsWith( $haystack, $needle )
    {

        return (substr($haystack, 0, strlen($needle)) === $needle);

    }

    /**
     * Unwraps the given value if it's an array.
     *
     * @param array $array
     * @return mixed
     */
    private function unwrap( $value )
    {

        return is_array( $value ) ? $value[0] : $value;

    }

}
