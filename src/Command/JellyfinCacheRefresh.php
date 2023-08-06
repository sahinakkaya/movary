<?php declare(strict_types=1);

namespace Movary\Command;

use Movary\Api\Jellyfin\Cache;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class JellyfinCacheRefresh extends Command
{
    private const OPTION_NAME_USER_ID = 'userId';

    protected static $defaultName = 'jellyfin:cache:refresh';

    public function __construct(
        private readonly Cache\JellyfinCache $jellyfinCache,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure() : void
    {
        $this->setDescription('Refresh the local cache of Jellyfin movies.')
            ->addArgument(self::OPTION_NAME_USER_ID, InputArgument::REQUIRED, 'Id of user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $userId = (int)$input->getArgument(self::OPTION_NAME_USER_ID);

        try {
            $this->jellyfinCache->loadFromJellyfin($userId);
        } catch (Throwable $t) {
            $this->generateOutput($output, 'ERROR: Could not complete Jellyfin cache refresh.');
            $this->logger->error('Could not complete Jellyfin cache refresh.', ['exception' => $t]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
