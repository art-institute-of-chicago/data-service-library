<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;

use Aic\Hub\Foundation\AbstractCommand;

class LibraryDownload extends AbstractCommand
{

    protected $signature = 'library:download';

    protected $description = "Download Special Collections from the R&BL";

    protected $max_attempts = 2;

    protected $context;

    protected $bearer;

    protected $total;

    protected $limit = 50;


    public function handle()
    {

        $this->bearer = env( 'PRIMO_API_BEARER' ) ?? $this->setBearer();

        $this->total = $this->getTotal();

        $this->info('Total records: ' . $this->total);

        for( $offset = 0; $offset < $this->total; $offset += $this->limit )
        {

            $file = $offset . '.json';

            if( Storage::exists( $file ) )
            {
                $this->warn('Found ' . $file);
                continue;
            }

            $response = $this->query( $offset, $this->limit );

            Storage::put( $file, $response->body );

            $this->info('Saved ' . $file);

            // Sleep for 3-5 seconds => 6.9 to 11.5 min delay total for ~6850 docs
            usleep(rand(3000000,5000000));

        }

    }

    /**
     * Extract total number of records expected from our query.
     *
     * @return integer
     */
    private function getTotal()
    {

        // Minimum limit is 1
        $response = $this->query( 0, 1, true );

        return (int) $response->body->info->total;

    }

    /**
     * Run a paginated query against Primo's PNX API.
     *
     * @param integer $offset   Zero-based offset for paginating.
     * @param integer $limit    Default is 50. Minimum is 1. Library uses 10.
     * @param boolean $decode   (optional) Decode the JSON response body?
     * @param integer $attempt  (optional) Tracks repeated attempts.
     * @return object
     */
    private function query( $offset, $limit, $decode = false, $attempt = 1 )
    {

        $url = env('PRIMO_API_URL') . '/primo-explore/v1/pnxs?' . http_build_query([
            'inst' => env('PRIMO_API_INST'),
            'vid' => env('PRIMO_API_INST'),
            'scope' => 'specialcollections',
            'sort' => 'rank',
            'offset' => $offset,
            'limit' => $limit,
            'q' => env('PRIMO_API_QUERY'),
        ]);

        if( $attempt > $this->max_attempts )
        {

            $this->warn('This query was attempted ' . $this->max_attempts . ' times unsuccessfully:' . PHP_EOL);

            $this->info( $url . PHP_EOL );

            $this->warn('Renewing authentication did not solve the problem. Exiting.');

            exit(1);

        }

        // Open the file using the HTTP headers set in context
        // Error control operator: https://stackoverflow.com/a/272377/1943591
        $file = @file_get_contents( $url, false, $this->getContext() );

        if( !isset( $http_response_header ) )
        {

            $this->warn('No response from server. Check your internet connection.');
            exit(1);

        }

        $response = (object) [
            'headers' => $http_response_header,
            'code' => (int) substr($http_response_header[0], 9, 3),
            'body' => $decode ? json_decode( $file ) : $file,
        ];

        // Renew auth and rerun the query if this query fails
        if( in_array( $response->code, [401, 403, 500] ) )
        {

            $this->info('Invalid authentication. Retrieving new credentials.');

            $this->setBearer();

            return $this->query( $offset, $limit, $decode, $attempt++ );

        }

        return $response;

    }

    /**
     * Helper to retrieve stream context containing correct auth headers.
     * It will create the context if it doesn't already exist.
     *
     * @return resource
     */
    private function getContext()
    {

        if( $this->context )
        {
            return $this->context;
        }

        $opts = array(
            'http'=>array(
                'method'=>"GET",
                'header'=>"Authorization: Bearer " . $this->bearer . "\r\n"
            )
        );

        return $this->context = stream_context_create($opts);

    }

    /**
     * Get a guest token for Primo's PNX API.
     *
     * @return string
     */
    private function setBearer()
    {

        $url = env('PRIMO_API_URL') . '/v1/guestJwt/' . env('PRIMO_API_INST') . '?' . http_build_query([
            'viewId' => env('PRIMO_API_INST'),
            'isGuest' => true,
            'lang' => 'en_US',
        ]);

        $file = file_get_contents( $url );

        $bearer = json_decode( $file );

        $this->info( 'New auth token received! Copy this into your .env file:' . PHP_EOL );

        $this->warn( 'PRIMO_API_BEARER=' . $bearer . PHP_EOL );

        $this->info( 'Press any key to continue when ready...' );

        // https://unix.stackexchange.com/a/293941/190753
        passthru('read -n 1 -s -r');

        return $this->bearer = $bearer;

    }

}
