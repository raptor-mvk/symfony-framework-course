<?php

namespace App\Service;

use Exception;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\Expression;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;

class DTOClassGenerator extends AbstractClassManipulatorAndGenerator
{
    public function generateDTOClass(string $name, array $properties, ConsoleStyle $io): void
    {
        $dtoClassDetails = $this->getDTOClassDetails($name);
        $dtoPath = $this->generateClass(
            $dtoClassDetails,
            'DTO.tpl.php'
        );
        $this->updateDTOClass($dtoPath, $properties, $io);
    }

    /**
     * @throws Exception
     */
    private function updateDTOClass(string $dtoPath, array $properties, ConsoleStyle $io): void
    {
        $dtoManipulator = $this->createClassManipulator($dtoPath, $io);
        $this->addConstructor($dtoManipulator, $properties);
        foreach ($properties as $fieldName => $type) {
            $this->addDTOField($dtoManipulator, $fieldName, ($type === 'string') ? 'string' : 'int');
        }
        $this->dumpFile($dtoPath, $dtoManipulator->getSourceCode());
    }

    private function addConstructor(ClassSourceManipulator $manipulator, array $fields): void
    {
        $factory = $this->getBuilderFactory();
        $methodBuilder = $factory->method('__construct')
            ->makePublic()
            ->addParam($factory->param('entity'));
        foreach ($fields as $fieldName => $type) {
            if ($type === 'field') {
                $getterName = 'get'. ucfirst(substr($fieldName, 0, -2));
                $getterCall = $factory->methodCall($factory->var('entity'), $getterName);
                $idGetterCall = $factory->methodCall($getterCall, 'getId');
                $fillFieldStatement = new Expression(new Assign(
                    $factory->propertyFetch($factory->var('this'), $fieldName),
                    $idGetterCall
                ));
                $methodBuilder->addStmt($fillFieldStatement);
            } else {
                $getterCall = $factory->methodCall($factory->var('entity'), 'get'.ucfirst($fieldName));
                $fillFieldStatement = new Expression(new Assign(
                    $factory->propertyFetch($factory->var('this'), $fieldName),
                    $getterCall
                ));
                $methodBuilder->addStmt($fillFieldStatement);
            }
        }
        $manipulator->addMethodBuilder($methodBuilder);
    }

    private function addDTOField(ClassSourceManipulator $manipulator, string $fieldName, string $typeHint, array $comments = []): void
    {
        $comments = array_merge(
            $comments,
            [
                "@var $typeHint",
                "@JMS\Type(\"$typeHint\")",
            ]
        );
        $manipulator->addProperty($fieldName, $comments);

        $manipulator->addGetter($fieldName, $typeHint, false);
    }
}
