<?php

namespace App\Console\Commands;

use GrahamCampbell\Flysystem\Facades\Flysystem;

use Illuminate\Console\Command;

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
        $paths = $paths->slice(0,2);

        // Turn the full paths to relative for Flysystem
        $files = $paths->map( function( $path ) use ( $directory ) {
            // +1 to remove the starting forwardslash
            return substr( $path, strlen( $directory ) + 1 );
        });

        // Process each matching file
        $out = $paths->map( [$this, 'processFile'] );

        // Collapses the array of arrays
        $out = array_merge( ... $out->all() );

        // TODO: Make this write to database
        dd( json_encode( $out, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE  ) );

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
        $docs = $docs->slice(0,5);

        return $docs->map( [ $this, 'processDoc' ] )->all();

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

        return [

            'id' => $this->unwrap( $doc->pnx->control->recordid ),
            'title' => $this->unwrap( $doc->pnx->display->title ),
            'date' => $this->unwrap( $doc->pnx->search->creationdate ),
            'creators' => $this->filterLinks( $links, 'creatorcontrib' ),
            'subjects' => $this->filterLinks( $links, 'subject' ),

            // 'language' => $doc->pnx->display->language,

        ];

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
            return $link->except(['key']);
        });

        // Without calling values, integer keys can remain:
        // https://laravel.com/docs/5.4/collections#method-values
        return $cleaned->values()->all();

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
        $uris = collect( $doc->pnx->links->uri );

        // $uri is an array of delimited strings
        $links = $uris->map( function( $uri ) {

            // Explode by the delimiter
            $parts = explode( '$$', $uri );

            // These strings start with $$, so the first element should be discarded
            array_shift( $parts );

            // Turn it into a collection
            $parts = collect( $parts );

            return collect([
                'key' => $this->getLinkPart( $parts, 'A' ),
                'name' => $this->getLinkPart( $parts, 'V' ),
                'id' => $this->getLcId( $parts ),
            ]);

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
    private function getLcId( $parts )
    {

        return basename( $this->getLinkPart( $parts, 'U(uri) http://id.loc.gov' ) );

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
