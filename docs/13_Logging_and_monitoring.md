Запускаем контейнеры командой `docker-compose up -d`

# 1. Логирование с помощью Monolog

## 1. Добавляем monolog-bundle и логируем сообщения 

1. Устанавливаем пакет `symfony/monolog-bundle`
1. В файле `config/packages/security.yaml` в секцию `firewalls.main` добавляем параметр `security: false`   
1. В классе `App\Controller\SaveUser\v4\Controller`
    1. Добавляем в конструктор параметр типа `LoggerInterface`
    1. В начало метода `saveUserAction` добавляем
        ```php
        $this->logger->debug('This is debug message');
        $this->logger->info('This is info message');
        $this->logger->notice('This is notice message');
        $this->logger->warning('This is warning message');
        $this->logger->error('This is error message');
        $this->logger->critical('This is critical message');
        $this->logger->alert('This is alert message');
        $this->logger->emergency('This is emergency message');
        ```
1. Выполняем запрос Add user v4 из Postman-коллекции v4 и проверяем, что логи попадают в файл `var/log/dev.log`

## 2. Настраиваем уровень логирования

1. Заменяем в `config/packages/dev/monolog.yaml` значение в секции `handlers.main.level` на `critical`
1. Выполняем запрос Add user v4 из Postman-коллекции v4 и проверяем, что в файл `var/log/dev.log` попадают только
   сообщения с уровнями `critical`, `alert` и `emergency`
   
## 3. Настраиваем режим fingers crossed

1. В файле `config/packages/dev/monolog.yaml`
    1. Заменяем содержимое секции `handlers.main`
        ```yaml
        type: fingers_crossed
        action_level: error
        handler: nested
        buffer_size: 3
        ```
    1. Добавляем в секцию `handlers`
        ```yaml
        nested:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
        ```
1. Выполняем запрос Add user v4 из Postman-коллекции v4 и проверяем, что в файл `var/log/dev.log` дополнительно попадают
   сообщение с уровнем `error` и два предыдущих сообщения с уровнем ниже

## 4. Добавляем форматирование

1. Добавляем в `config/services.yaml`
    ```yaml
    monolog.formatter.app_formatter:
        class: Monolog\Formatter\LineFormatter
        arguments:
            - "[%%level_name%%]: [%%datetime%%] %%message%%\n"
    ```
1. Добавляем в `config/packages/dev/monolog.yaml` в секцию `handlers.main` форматтер
    ```yaml
    formatter: monolog.formatter.app_formatter
    ```
1. Выполняем запрос Add user v4 из Postman-коллекции v4 и проверяем, что в файл `var/log/dev.log` новые сообщения
   попадают с новом формате

# 2. Интеграция с Sentry   

## 1. Установка Sentry и бандла для интеграции с ним

1. Устанавливаем пакеты `nyholm/psr7`, `symfony/psr-http-message-bridge`, `sentry/sentry-symfony`
1. Добавляем сервисы Sentry в `docker-compose.yml` (не забудьте прописать описание volume `sentry-pgdb`)
    ```yaml
    redis:
        container_name: 'redis'
        image: redis
    
    sentry-postgres:
        image: postgres
        container_name: 'sentry-postgres'
        environment:
          POSTGRES_USER: sentry
          POSTGRES_PASSWORD: sentry
          POSTGRES_DB: sentry
        volumes:
         - sentry-pgdb:/var/lib/postgresql/data
    
    sentry:
        image: sentry
        container_name: 'sentry'
        links:
         - redis
         - sentry-postgres
        ports:
         - 10000:9000
        environment:
          SENTRY_SECRET_KEY: '&1k8n7lr_p9q5fd_5*kde9*p)&scu%pqi*3*rflw+b%mprdob)'
          SENTRY_POSTGRES_HOST: sentry-postgres
          SENTRY_DB_USER: sentry
          SENTRY_DB_PASSWORD: sentry
          SENTRY_REDIS_HOST: redis
    
    cron:
        image: sentry
        container_name: 'sentry-cron'
        links:
         - redis
         - sentry-postgres
        command: "sentry run cron"
        environment:
          SENTRY_SECRET_KEY: '&1k8n7lr_p9q5fd_5*kde9*p)&scu%pqi*3*rflw+b%mprdob)'
          SENTRY_POSTGRES_HOST: sentry-postgres
          SENTRY_DB_USER: sentry
          SENTRY_DB_PASSWORD: sentry
          SENTRY_REDIS_HOST: redis
    
    worker:
        image: sentry
        container_name: 'sentry-worker'
        links:
         - redis
         - sentry-postgres
        command: "sentry run worker"
        environment:
          SENTRY_SECRET_KEY: '&1k8n7lr_p9q5fd_5*kde9*p)&scu%pqi*3*rflw+b%mprdob)'
          SENTRY_POSTGRES_HOST: sentry-postgres
          SENTRY_DB_USER: sentry
          SENTRY_DB_PASSWORD: sentry
          SENTRY_REDIS_HOST: redis
    ```
1. Перезапускаем контейнеры и инициализируем Sentry
    1. Перезапускаем контейнеры
        ```shell
        docker-compose stop
        docker-compose up -d
        ```
    1. Инициализируем Sentry командой `docker exec -it sentry sentry upgrade`
    1. В процессе инициализации создаём суперпользователя `user@mail.com` / `password`
    1. Перезапускаем Sentry командой `docker-compose restart sentry`
1. Логинимся на `localhost:10000` с созданными реквизитами суперпользователя
1. Создаём новый проект, получаем DSN и прописываем его в файл `.env`
    ```
    http://DSN@sentry:9000/2
    ```
1. Выполняем запрос Add user v3 из Postman-коллекции v4 и проверяем, что в Sentry появляется issue

## 2. Игнорирование ошибок

1. Делаем POST-запрос на несуществующий endpoint `/api/v4/users`, проверяем, что в Sentry появляется issue
1. В файле `config/packages/sentry.yaml`
    1. Добавляем в секцию `sentry`
        ```yaml
        options:
            integrations:
                - 'Sentry\Integration\IgnoreErrorsIntegration'
        ```
    1. Добавляем сервис
        ```yaml
        services:
            Sentry\Integration\IgnoreErrorsIntegration:
               arguments:
                   $options:
                       ignore_exceptions:
                           - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
        ```
1. Ещё раз делаем POST-запрос на несуществующий endpoint `/api/v4/users`, проверяем, что issue в Sentry больше не
   появляется

## 3. Интеграция Monolog и Sentry

1. В `config/packages/sentry.yaml` добавляем сервис
    ```yaml
    Sentry\Monolog\Handler:
        arguments:
            $hub: '@Sentry\State\HubInterface'
            $level: !php/const Monolog\Logger::ERROR
    ```
1. В `config/packages/dev/monolog.yaml` добавляем в секцию `handlers`
    ```yaml
    sentry:
        type: service
        id: Sentry\Monolog\Handler
    ```
1. Выполняем запрос Add user v4 из Postman-коллекции v4 и проверяем, что в Sentry появляются ошибки уровней `error`,
   `critical`, `alert` и `emergency`
   
# 3. Grafana для сбора метрик, интеграция с Graphite

1. Устанавливаем пакет `domnikl/statsd`
1. Добавляем сервисы Graphite и Grafana в `docker-compose.yml`
    ```yaml
    graphite:
        image: graphiteapp/graphite-statsd
        container_name: 'graphite'
        restart: always
        ports:
          - 8000:80
          - 2003:2003
          - 2004:2004
          - 2023:2023
          - 2024:2024
          - 8125:8125/udp
          - 8126:8126

    grafana:
        image: grafana/grafana
        container_name: 'grafana'
        restart: always
        ports:
          - 3000:3000
    ```
1. Перезапускаем контейнеры
    ```shell
    docker-compose stop
    docker-compose up -d
    ```
1. Проверяем, что можем зайти в интерфейс Graphite по адресу `localhost:8000`
1. Проверяем, что можем зайти в интерфейс Grafana по адресу `localhost:3000`, логин / пароль - `admin` / `admin`
1. Добавляем класс `StatsdBundle\Client\StatsdAPIClient`
    ```php
    <?php
    
    namespace App\Client;
    
    use Domnikl\Statsd\Client;
    use Domnikl\Statsd\Connection\UdpSocket;
    
    class StatsdAPIClient
    {
        private const DEFAULT_SAMPLE_RATE = 1.0;
        
        private Client $client;
    
        public function __construct(string $host, int $port, string $namespace)
        {
            $connection = new UdpSocket($host, $port);
            $this->client = new Client($connection, $namespace);
        }
    
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void
        {
            $this->client->increment($key, $sampleRate ?? self::DEFAULT_SAMPLE_RATE, $tags ?? []);
        }
    }
    ```
1. Добавляем в `config/services.yaml` описание сервиса statsd API-клиента
    ```yaml
    StatsdBundle\Client\StatsdAPIClient:
        arguments: 
            - graphite
            - 8125
            - my_app
    ```
1. Добавляем в `App\Controller\Api\SaveUser\v4\Controller`
    1. В конструктор параметр типа `StatsdAPIClient`
    1. В начале метода `saveUserAction` инкрементируем счётчик
        ```php
        $this->statsdAPIClient->increment('save_user_v4_attempt');
        ```
1. Выполняем несколько раз запрос Add user v4 из Postman-коллекции v4 и проверяем, что в Graphite появляются события
1. Настраиваем график в Grafana
    1. добавляем в Data source с типом Graphite и адресом graphite:80
    1. добавляем новый Dashboard 
    1. на дашборде добавляем панель с запросом в Graphite счётчика `stats_counts.my_app.save_user_v4_attempt`
    1. видим график с запросами
1. Выполняем ещё несколько раз запрос Add user v4 из Postman-коллекции v4 и проверяем, что в Grafana обновились данные
    