<?php declare(strict_types=1);

namespace Movary\Command;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseMigrationMigrate extends Command
{
    protected static $defaultName = 'database:migration:migrate';

    public function __construct(
        private readonly PhinxApplication $phinxApplication,
        private readonly string $phinxConfigurationFile
    ) {
        parent::__construct();
    }

    protected function configure() : void
    {
        $this->setDescription('Execute missing database migrations.');
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $command = $this->phinxApplication->find('migrate');

        $arguments = [
            'command' => $command,
            '--configuration' => $this->phinxConfigurationFile,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
