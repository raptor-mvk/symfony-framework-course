<?php

namespace App\Service;

use Exception;
use PhpParser\BuilderFactory;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;

abstract class AbstractClassGenerator
{
    private const TEMPLATES_PATH = __DIR__.'/../../templates/';

    private string $templatePath;

    private Generator $generator;

    private BuilderFactory $factory;

    public function __construct(Generator $generator, BuilderFactory $factory, ?string $templatePath = null)
    {
        $this->generator = $generator;
        $this->templatePath = $templatePath ?? self::TEMPLATES_PATH;
        $this->factory = $factory;
    }

    protected function getGenerator(): Generator
    {
        return $this->generator;
    }

    protected function getBuilderFactory(): BuilderFactory
    {
        return $this->factory;
    }

    protected function createClassNameDetails(string $name, string $namespacePrefix, string $suffix = ''): ClassNameDetails
    {
        $className = rtrim($namespacePrefix, '\\').'\\'.Str::asClassName($name, $suffix);

        return new ClassNameDetails($className, $namespacePrefix, $suffix);
    }

    protected function getDTOClassDetails(string $entityName): ClassNameDetails
    {
        return $this->createClassNameDetails($entityName,'Generated\\DTO\\', 'DTO');
    }

    /**
     * @throws Exception
     */
    protected function generateClass(ClassNameDetails $classNameDetails, string $templateName, ?array $variables = null): string
    {
        $variables = array_merge($variables ?? [], [
            'className' => $classNameDetails->getShortName(),
        ]);
        $result = $this->generator->generateClass(
            $classNameDetails->getFullName(),
            $this->templatePath.$templateName,
            $variables
        );
        $this->generator->writeChanges();

        return $result;
    }

}
