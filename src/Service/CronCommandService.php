<?php

namespace App\Service;

use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Style\SymfonyStyle;

class CronCommandService
{
    /** @var SymfonyStyle */
    protected $io;

    /** @var Output */
    protected $o;

    public function __construct()
    { }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function reminder($days)
    {
        /*$process = new Process(
            'php bin/console abo:crons ' . $commandName . ' --thread=' . $thread . ' --limit=' . $limit . ' --offset=' . $offset
        );
        $process->setWorkingDirectory(getcwd() . "../");
        $process->disableOutput();
        $process->start();*/ }

    public function gift($days, $credits)
    { }
}
