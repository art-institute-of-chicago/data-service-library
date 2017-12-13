<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    abstract public function handle();

    /**
     * Here, we've extended the inherited execute method, which allows us to log times
     * for each command call. You can use `handle` in child classes as normal.
     *
     * @link http://api.symfony.com/3.3/Symfony/Component/Console/Command/Command.html
     * @link https://github.com/laravel/framework/blob/5.4/src/Illuminate/Console/Command.php
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $start = microtime(TRUE);

        // Call Illuminate\Console\Command::execute
        $result = parent::execute( $input, $output );

        $finish = microtime(TRUE);
        $totaltime = $finish - $start;
        $this->warn("Execution Time: {$totaltime} sec");

        return $result;

    }

}


