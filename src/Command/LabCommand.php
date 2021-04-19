<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\LabCommandService;


class LabCommand extends Command
{
    private $labService;

    public function __construct(LabCommandService $labService)
    {
        $this->labService = $labService;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('lab:command')
            ->setDescription('Lab frikiradar')
            ->addArgument('process', InputArgument::OPTIONAL, 'type proccess')
            ->addOption('option', null, InputOption::VALUE_OPTIONAL, 'process options', null)
            ->addOption('fromuser', null, InputOption::VALUE_OPTIONAL, 'fromuser', null)
            ->addOption('touser', null, InputOption::VALUE_OPTIONAL, 'touser', null);
    }

    protected function execute(InputInterface $i, OutputInterface $o)
    {
        // Ejemplo: php bin/console lab:command notification --fromuser=1 --touser=2
        $this->labService->setIo($i, $o);
        $o->writeln('<info>Start Process ' . date('m-d H:i:s', time()) . '</info>');
        switch ($i->getArgument('process')) {
            case 'geolocation':
                $this->labService->geolocation();
                break;
            case 'notification':
                $this->labService->notification($i->getOption('fromuser'), $i->getOption('touser'));
                break;
            case 'email':
                $this->labService->email(2);
                break;
            case 'thumbnails':
                $this->labService->thumbnails();
                break;
            case 'remove-account':
                $this->labService->removeAccount($i->getOption('fromuser'));
                break;
            case 'test':
                $this->labService->testLab();
                break;
            default:
                $o->writeln("<bg=yellow;fg=black>Undefined process, use help to see list </> <fg=red;options=bold>Exiting</>");
        }

        // if (isset($response)) $o->writeln('<comment>' . $response . '</comment>');
        $o->writeln('<info>End Process ' . date('m-d H:i:s', time()) . '</info>');
    }
}
