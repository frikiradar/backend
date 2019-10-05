<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\CronCommandService;


class CronCommand extends Command
{
    private $cronService;

    public function __construct(CronCommandService $cronService)
    {
        $this->cronService = $cronService;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('cron:command')
            ->setDescription('Cron frikiradar')
            ->addArgument('process', InputArgument::OPTIONAL, 'type proccess')
            ->addOption('option', null, InputOption::VALUE_OPTIONAL, 'process options', null)
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'días', null)
            ->addOption('credits', null, InputOption::VALUE_OPTIONAL, 'créditos', null);;
    }

    protected function execute(InputInterface $i, OutputInterface $o)
    {
        $this->cronService->setIo($i, $o);
        $o->writeln('<info>Start Process ' . date('m-d H:i:s', time()) . '</info>');
        switch ($i->getArgument('process')) {
            case 'reminder':
                $this->cronService->reminder($i->getOption('days'));
                break;
            case 'gift':
                $this->cronService->gift($i->getOption('credits'));
                break;
            default:
                $o->writeln("<bg=yellow;fg=black>Undefined process, use help to see list </> <fg=red;options=bold>Exiting</>");
        }

        // if (isset($response)) $o->writeln('<comment>' . $response . '</comment>');
        $o->writeln('<info>End Process ' . date('m-d H:i:s', time()) . '</info>');
    }
}
