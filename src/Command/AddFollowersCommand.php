<?php
declare(strict_types=1);

namespace App\Command;

use App\DTO\SaveUserDTO;
use App\Service\SubscriptionService;
use App\Service\UserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Throwable;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 */
final class AddFollowersCommand extends Command
{
    use LockableTrait;

    /** @var int */
    public const OK = 0;
    /** @var int */
    public const GENERAL_ERROR = 1;
    private const DEFAULT_FOLLOWERS = 20;

    /** @var UserService */
    private $userService;
    /** @var SubscriptionService */
    private $subscriptionService;

    public function __construct(UserService $userService, SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->userService = $userService;
        $this->subscriptionService = $subscriptionService;
    }

    protected function configure(): void
    {
        $this->setName('followers:add')
            ->setHidden(true)
            ->setDescription('Adds followers to author')
            ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userService->findById($authorId);
        if ($user === null) {
            $output->write("<error>User with ID $authorId doesn't exist</error>\n");
            return self::GENERAL_ERROR;
        }
        $helper = $this->getHelper('question');
        $question = new Question('How many followers you want to add?', self::DEFAULT_FOLLOWERS);
        $count = (int)$helper->ask($input, $output, $question);
        if ($count < 0) {
            $output->write("<error>Count should be positive integer</error>\n");
            return self::GENERAL_ERROR;
        }
        $createdFollowers = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $saveUserDTO = new SaveUserDTO("Reader #$authorId.$i", '+1111111111', 'no@mail.com', true);
                $userId = $this->userService->saveUser($saveUserDTO);
                if ($userId !== null) {
                    $this->subscriptionService->subscribe($authorId, $userId);
                    $createdFollowers++;
                }
            } catch (Throwable $e) {
                $output->write("<error>User #$i couldn't be created</error>\n");
            }
        }
        $output->write("<info>$createdFollowers followers were created</info>\n");

        return self::OK;
    }
}
