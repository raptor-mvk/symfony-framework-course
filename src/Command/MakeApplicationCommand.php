<?php

namespace App\Command;

use App\Service\DTOClassGenerator;
use PhpParser\BuilderFactory;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\ORMDependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MakeApplicationCommand extends AbstractMaker implements InputAwareMakerInterface
{
    private FileManager $fileManager;

    private BuilderFactory $builderFactory;

    public function __construct(FileManager $fileManager, BuilderFactory $builderFactory)
    {
        $this->fileManager = $fileManager;
        $this->builderFactory = $builderFactory;
    }

    public static function getCommandName(): string
    {
        return 'make:application';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates or updates a Doctrine entity class, and optionally an API Platform resource';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $inputConfig->setArgumentAsNonInteractive('name');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $dtos = [
            'teacher' => ['name' => 'string', 'surname' => 'string', 'patronymic' => 'string'],
            'student' => ['name' => 'string', 'surname' => 'string', 'patronymic' => 'string', 'teacherId' => 'field'],
        ];

        foreach ($dtos as $name => $properties) {
            (new DTOClassGenerator($generator, $this->builderFactory, $this->fileManager))->generateDTOClass($name, $properties, $io);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null): void
    {
        ORMDependencyBuilder::buildDependencies($dependencies);
    }
}
