<?php

namespace App\Service;

use PhpParser\BuilderFactory;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;

abstract class AbstractClassManipulatorAndGenerator extends AbstractClassGenerator
{
    private FileManager $fileManager;

    public function __construct(Generator $generator, BuilderFactory $factory, FileManager $fileManager, ?string $templatePath = null)
    {
        parent::__construct($generator, $factory, $templatePath);
        $this->fileManager = $fileManager;
    }

    public function dumpFile(string $filename, string $content): void
    {
        $this->fileManager->dumpFile($filename, $content);
    }

    protected function createClassManipulator(string $path, ConsoleStyle $io): ClassSourceManipulator
    {
        $manipulator = new ClassSourceManipulator($this->fileManager->getFileContents($path), false);
        $manipulator->setIo($io);

        return $manipulator;
    }
}
