1. Запускаем контейнеры командой `docker-compose up`
1. Заходим в контейнер `php` командой `docker exec -it php sh`, далее все команды выполняются в контейнере
1. Создаём класс `App\Service\GreeterService`
    ```php
    <?php
    
    namespace App\Service;
    
    class GreeterService
    {
        private string $greet;
    
        public function __construct(string $greet)
        {
            $this->greet = $greet;
        }
    
        public function greet(string $name): string
        {
            return $this->greet.', '.$name.'!';
        }
    }
    ```
1. Исправляем класс `App\Controller\WorldController`
    ```
    <?php
    
    namespace App\Controller;
    
    use App\Service\GreeterService;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController
    {
        private GreeterService $greeterService;
    
        public function __construct(GreeterService $greeterService)
        {
            $this->greeterService = $greeterService;
        }
    
        public function hello(): Response
        {
            return new Response("<html><body>{$this->greeterService->greet('world')}</body></html>");
        }
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим ошибку
1. Добавляем в файле `config/services.yaml` новую службу
    ```yaml
    App\Service\GreeterService:
        arguments:
            $greet: 'Hello'
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим сообщение
1. Создаём класс `App\Service\FormatService`
    ```php
    <?php
    
    namespace App\Service;
    
    class FormatService
    {
        private ?string $tag;
    
        public function __construct()
        {
            $this->tag = null;
        }
    
        /**
         * @param string $tag
         */
        public function setTag(string $tag): self
        {
            $this->tag = $tag;
    
            return $this;
        }
    
        public function format(string $contents): string
        {
            return ($this->tag === null) ? $contents : "<{$this->tag}>$contents</{$this->tag}>";
        }
    }
    ```
1. Создаём класс `App\Service\FormatServiceFactory`
    ```php
    <?php
    
    namespace App\Service;
    
    class FormatServiceFactory
    {
        public static function strongFormatService(): FormatService
        {
            return (new FormatService())->setTag('strong');
        }
    
        public function citeFormatService(): FormatService
        {
            return (new FormatService())->setTag('cite');
        }
    
        public function headerFormatService(int $level): FormatService
        {
            return (new FormatService())->setTag("h$level");
        }
    }
    ```
1. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Service\FormatService;
    use App\Service\GreeterService;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController
    {
        private GreeterService $greeterService;
        private FormatService $formatService;
    
        public function __construct(FormatService $formatService, GreeterService $greeterService)
        {
            $this->greeterService = $greeterService;
            $this->formatService = $formatService;
        }
    
        public function hello(): Response
        {
            $result = $this->formatService->format($this->greeterService->greet('world'));
    
            return new Response("<html><body>$result</body></html>");
        }
    }
    ```
1. Добавляем новые сервисы форматтеров в файле `config/services.yaml`
    ```yaml
    strong_formatter:
      class: App\Service\FormatService
      factory: ['App\Service\FormatServiceFactory', 'strongFormatService']
    
    cite_formatter:
      class: App\Service\FormatService
      factory: ['@App\Service\FormatServiceFactory', 'citeFormatService']
    
    main_header_formatter:
      class: App\Service\FormatService
      factory: ['@App\Service\FormatServiceFactory', 'headerFormatService']
      arguments: [1]    
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что никакого форматирования не произошло
1. В файле `config/services.yaml` добавляем инъекцию конкретного форматтера в контроллер
    ```yaml
    App\Controller\WorldController:
      arguments:
        $formatService: '@cite_formatter'
      tags: ['controller.service_arguments']
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим, что применилось форматирование 
1. Создаём класс `App\Service\MessageService`
    ```php
    <?php
    
    namespace App\Service;
    
    class MessageService
    {
        /** @var GreeterService[] */
        private array $greeterServices;
        /** @var FormatService[] */
        private array $formatServices;
    
        public function __construct()
        {
            $this->greeterServices = [];
            $this->formatServices = [];
        }
    
        public function addGreeter(GreeterService $greeterService)
        {
            $this->greeterServices[] = $greeterService;
        }
    
        public function addFormatter(FormatService $formatService)
        {
            $this->formatServices[] = $formatService;
        }
    
        public function printMessages(string $name): string
        {
            $result = '';
            foreach ($this->greeterServices as $greeterService) {
                $current = $greeterService->greet($name);
                foreach ($this->formatServices as $formatService) {
                    $current = $formatService->format($current);
                }
                $result .= $current;
            }
    
            return $result;
        }
    }
    ```
1. Исправляем класс `App\Controller\WorldController`
    ```php
    <?php
    
    namespace App\Controller;
    
    use App\Service\FormatService;
    use App\Service\MessageService;
    use Symfony\Component\HttpFoundation\Response;
    
    class WorldController
    {
        private FormatService $formatService;
        private MessageService $messageService;
    
        public function __construct(FormatService $formatService, MessageService $messageService)
        {
            $this->formatService = $formatService;
            $this->messageService = $messageService;
        }
    
        public function hello(): Response
        {
            $result = $this->formatService->format($this->messageService->printMessages('world'));

            return new Response("<html><body>$result</body></html>");
        }
    }
    ```
1. В файл `config/services.yaml`
   1. Убираем сервис `App\Service\GreeterService`
   1. Добавляем новые сервисы
        ```yaml
        hello_greeter:
          class: App\Service\GreeterService
          arguments:
            $greet: 'Hello'
          tags: ['app.greeter_service']
        
        greetings_greeter:
          class: App\Service\GreeterService
          arguments:
            $greet: 'Greetings'
          tags: ['app.greeter_service']
        
        hi_greeter:
          class: App\Service\GreeterService
          arguments:
            $greet: 'Hi'
          tags: ['app.greeter_service']
        
        list_formatter:
          class: App\Service\FormatService
          calls:
            - [setTag, ['ol']]
        
        list_item_formatter:
          class: App\Service\FormatService
          calls:
            - [setTag, ['li']]
          tags: ['app.formatter_service']
        ```
   1. Добавляем тэг `app.formatter_service` для сервисов `cite_formatter` и `strong_formatter`
   1. Исправляем описание сервиса `App\Controller\WorldController`
        ```yaml
        App\Controller\WorldController:
          arguments:
            $formatService: '@list_formatter'
          tags: ['controller.service_arguments']
        ```
1. Создаём класс `App\Symfony\GreeterPass`
    ```php
    <?php
    
    namespace App\Symfony;
    
    use App\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class GreeterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container)
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $greeterServices = $container->findTaggedServiceIds('app.greeter_service');
            foreach ($greeterServices as $id => $tags) {
                $messageService->addMethodCall('addGreeter', [new Reference($id)]);
            }
        }
    }
    ```
1. Создаём класс `App\Symfony\FormatterPass`
    ```php
    <?php
    
    namespace App\Symfony;
    
    use App\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class FormatterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container)
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $formatterServices = $container->findTaggedServiceIds('app.formatter_service');
            foreach ($formatterServices as $id => $tags) {
                $messageService->addMethodCall('addFormatter', [new Reference($id)]);
            }
        }
    }
    ```
1. В класс `App\Kernel` добавляем новый метод `build`
    ```php
    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new FormatterPass());
        $container->addCompilerPass(new GreeterPass());
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим ненумерованный список из трёх приветствий с
   форматированием
1. В файле `config/services.yaml` изменяем описание тэгов для сервисов приветствий
    ```yaml
    hello_greeter:
      class: App\Service\GreeterService
      arguments:
        $greet: 'Hello'
      tags:
        - { name: 'app.greeter_service', priority: 3 }
    
    greetings_greeter:
      class: App\Service\GreeterService
      arguments:
        $greet: 'Greetings'
      tags:
        - { name: 'app.greeter_service', priority: 2 }
    
    hi_greeter:
      class: App\Service\GreeterService
      arguments:
        $greet: 'Hi'
      tags:
        - { name: 'app.greeter_service', priority: 1 }
    ```
1. Исправляем класс `App\Symfony\GreeterPass`
    ```php
    <?php
    
    namespace App\Symfony;
    
    use App\Service\MessageService;
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;
    
    class GreeterPass implements CompilerPassInterface
    {
        public function process(ContainerBuilder $container)
        {
            if (!$container->has(MessageService::class)) {
                return;
            }
            $messageService = $container->findDefinition(MessageService::class);
            $greeterServices = $container->findTaggedServiceIds('app.greeter_service');
            uasort($greeterServices, static fn(array $tag1, array $tag2) => $tag1[0]['priority'] - $tag2[0]['priority']);
            foreach ($greeterServices as $id => $tags) {
                $messageService->addMethodCall('addGreeter', [new Reference($id)]);
            }
        }
    }
    ```
1. Заходим по адресу `http://localhost:7777/world/hello`, видим пересортированный список