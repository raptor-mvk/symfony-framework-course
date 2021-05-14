1. Регистрируемся на https://amazon.com
1. Логинимся и заходим в службу IAM
1. Создаём нового пользователя только для программного доступа
1. Создаём группу с доступом AWSElasticBeanstalkFullAccess и добавляем в неё пользователя
1. Скачиваем файл с ключами
1. Клонируем репозиторий https://github.com/aws/aws-elastic-beanstalk-cli-setup
1. Внимательно читаем readme и выполняем необходимые действия для вашей ОС
    1. Потенциальные проблемы описаны в readme
    1. После установки нужно не забыть экспортировать переменные с путями
1. Проверяем, что консольный интерфейс установлен командой `eb --version`
1. Выполняем в корневой директории проекта команду `eb init`
    1. Выбираем регион
    1. Указываем реквизиты доступа из файла с ключами (Access key ID (не User name!) / Secret access key)
    1. Указываем название приложения
    1. Выбираем платформу PHP 7.4
    1. Разрешаем доступ по SSH
1. Выполняем в корневой директории проекта команду `eb create`
    1. Указываем имя окружения и DNS-имя
    1. Выбираем тип балансировщика (application)
    1. Отказываемся от использования Spot Fleet
1. Добавляем файл `.ebextensions/01-main.config`
    ```yaml
    commands:
        01-composer-update:
            command: "export COMPOSER_HOME=/root && /usr/bin/composer.phar self-update 1.10.19"
    
    container_commands:
        02-get-composer:
            command: "/usr/bin/composer.phar install --no-interaction --optimize-autoloader"
        03-clear-cache:
            command: "sudo -u webapp php bin/console cache:clear --env=dev"
    
    option_settings:
      - namespace: aws:elasticbeanstalk:application:environment
        option_name: COMPOSER_HOME
        value: /root
    ```
1. Добавляем файл `.platform/nginx/conf.d/elasticbeanstalk/symfony.conf`
    ```
    location / {
      try_files $uri $uri/ /index.php?$query_string;
    }
    ```
1. Выполняем команду `eb deploy`
1. Выполняем команду `eb open`, видим ошибку 403
1. Добавляем класс `App\Controller\Api\v1\HealthController`
    ```php
    <?php
    
    namespace App\Controller\Api\v1;
    
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    
    /**
     * @Annotations\Route("/api/v1/health")
     */
    class HealthController extends AbstractFOSRestController
    {
        /**
         * @Annotations\Get("/check")
         */
        public function checkAction(): Response
        {
            return new JsonResponse(['success' => true]);
        }
    }
    ```
1. В файле `config/packages/security.yaml` в секцию `firewalls` добавляем новый элемент
    ```yaml
    health:
        pattern: ^/api/v1/health
        security: false
    ```
1. Выполняем команду `eb deploy`
1. Заходим в консоль Elastic Beanstalk и исправляем параметры проекта:
    1. В разделе Configuration -> Software устанавливаем Document root = /public
    1. В разделе Configuration -> Load balancer -> Processes устанавливаем Health check path = /api/v1/health/check 
1. Проверяем, что Health сменился на Ok, и что теперь ошибка при входе в приложение внятная
1. Выполняем запрос Get token из Postman-коллекции v4, заменив хост, получаем ошибку доступа к БД
1. Заходим в консоль Elastic Beanstalk
    1. В разделе Configuration -> Database добавляем БД с параметрами
        - Engine = postgres
        - Engine version = 11.9
        - Username = twitterUser
        - Password = 0ZRa4pVHdT0mRAMeLEIU
1. Заходим в консоль RDS
    1. Выбираем наш инстанс в разделе Databases
    1. В блоке полей Security group rules выбираем верхнюю группу и в Inbound rules добавляем правило с параметрами
        - Type = PostgreSQL
        - Source 0.0.0.0/0
1. Исправляем параметры доступа в файле `.env` (HOST - Endpoint RDS в AWS)
   ```shell
   DATABASE_URL="postgresql://twitterUser:0ZRa4pVHdT0mRAMeLEIU@HOST:5432/ebdb?serverVersion=11&charset=utf8"
   ```
1. Исправляем файл `.ebextensions/01-main.config`
    ```yaml
    commands:
        01-composer-update:
            command: "export COMPOSER_HOME=/root && /usr/bin/composer.phar self-update 1.10.16"
    
    container_commands:
        02-get-composer:
            command: "sudo -u webapp /usr/bin/composer.phar install --no-interaction --optimize-autoloader"
        03-migrate:
            command: "sudo -u webapp php bin/console doctrine:migration:migrate --env=dev"
        04-clear-cache:
            command: "sudo -u webapp php bin/console cache:clear --env=dev"
    
    option_settings:
      - namespace: aws:elasticbeanstalk:application:environment
        option_name: COMPOSER_HOME
        value: /root
    ```
1. Выполняем запрос Get token из Postman-коллекции v4, заменив хост, получаем ошибку реквизитов
