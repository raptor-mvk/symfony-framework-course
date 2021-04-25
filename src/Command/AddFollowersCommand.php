<?php

namespace App\Command;

use App\DTO\UserDTO;
use App\Entity\User;
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

final class AddFollowersCommand extends Command
{
    use LockableTrait;

    /** @var int */
    public const OK = 0;
    /** @var int */
    public const GENERAL_ERROR = 1;

    /** @var int */
    private const DEFAULT_FOLLOWERS = 100;
    /** @var string */
    private const DEFAULT_LOGIN_PREFIX = 'Reader #';

    private UserService $userService;

    private SubscriptionService $subscriptionService;

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
            ->addArgument('authorId', InputArgument::REQUIRED, 'ID of author')
            ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'Follower login prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $authorId = (int)$input->getArgument('authorId');
        $user = $this->userService->findUserById($authorId);
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
        $login = $input->getOption('login') ?? self::DEFAULT_LOGIN_PREFIX;
        $result = $this->subscriptionService->addFollowers($user, $login.$authorId, $count);
        $output->write("<info>$result followers were created</info>\n");

        return self::OK;
    }
}
