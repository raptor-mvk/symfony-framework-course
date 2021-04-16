<?php

namespace UnitTests\Command;

use App\Service\UserService;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Mockery;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use UnitTests\FixturedTestCase;
use UnitTests\Fixtures\MultipleUsersFixture;

class AddFollowersCommandTest extends FixturedTestCase
{
    private const COMMAND = 'followers:add';

    private static Application $application;

    public function setUp(): void
    {
        parent::setUp();

        self::$application = new Application(self::$kernel);
        $this->addFixture(new MultipleUsersFixture());
        $this->executeFixtures();
    }

    public function executeDataProvider(): array
    {
        return [
            'positive' => [100, 'login', "100 followers were created\n"],
            'zero' => [0, 'other_login', "0 followers were created\n"],
            'default' => [null, 'login3', "100 followers were created\n"],
            'negative' => [-1, 'login_too', "Count should be positive integer\n"],
        ];
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteReturnsResult(?int $followersCount, string $login, string $expected): void
    {
        $command = self::$application->find(self::COMMAND);
        $commandTester = new CommandTester($command);
        /** @var UserPasswordEncoderInterface $encoder */
        $encoder = $this->getContainer()->get('security.password_encoder');
        $userService = new UserService($this->getDoctrineManager(), $encoder, Mockery::mock(PaginatedFinderInterface::class));
        $author = $userService->findUserByLogin(MultipleUsersFixture::PRATCHETT);
        $params = ['authorId' => $author->getId()];
        $options = ['login' => $login];
        if ($followersCount !== null) {
            $params['count'] = $followersCount;
        }
        $commandTester->execute($params, $options);
        $output = $commandTester->getDisplay();
        static::assertSame($expected, $output);
    }
}
