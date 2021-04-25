1. Устанавливаем пакет `nelmio/api-doc-bundle`
1. Заходим по адресу `http://localhost:7777/api/doc.json`, видим JSON-описание нашего API
1. Заходим по адресу `http://localhost:7777/api/doc`, видим ошибку
1. Добавляем в файл `config/routes.yaml`
    ```yaml
    app.swagger_ui:
      path: /api/doc
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
1. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим другую ошибку
1. Устанавливаем пакет `symfony/asset`
1. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим описание API
1. В файле `config/packages/security.yaml` в секцию `access_control` добавляем строку
    ```
    - { path: ^/api/doc, roles: ROLE_ADMIN }
    ```
1. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим требование авторизоваться
1. Исправляем в файле `config/packages/nelmio_api_doc.yaml` секцию `areas.path_patterns`
    ```yaml
    path_patterns:
        - ^/api(?!/doc(.json)?$)
    ```
1. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим, что лишние endpoint'ы ушли
1. Исправляем в файле `config/packages/nelmio_api_doc.yaml` секцию `areas`
    ```yaml
        feed:
            path_patterns:
                - ^/api/v1/feed
        default:
            path_patterns:
                - ^/api(?!/doc(.json)?$)
    ```
1. В файл `config/routes.yaml` добавляем
    ```yaml
    app.swagger_ui_areas:
      path: /api/doc/{area}
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
1. Заходим по адресу `http://localhost:7777/api/doc/feed`, видим выделенные endpoint'ы
1. В классе `App\Controller\Api\v1\FeedController`
    1. Добавляем импорт
        ```php
        use OpenApi\Annotations as OA;
        ```
    1. Добавляем аннотации к методу `getFeedAction`
        ```php
        /**
         * @Annotations\Get("")
         *
         * @OA\Tag(name="Лента")
         * @OA\Parameter(name="userId", in="query", description="ID пользователя", example="135")
         * @OA\Parameter(name="count", in="query", description="Количество твитов в ленте", example="5")
         * @Annotations\QueryParam(name="userId", requirements="\d+")
         * @Annotations\QueryParam(name="count", requirements="\d+", nullable=true)
         */
        ```
1. Заходим по адресу `http://localhost:7777/api/doc`, видим, что endpoint выделен в отдельный тэг и обновлённое описание
параметров
1. Заходим в контейнер командой `docker exec -it php sh` и выполняем команду
`php bin/console nelmio:apidoc:dump >apidoc.json`, получаем в корне проекта файл `apidoc.json` с описанием API
1. Устанавливаем пакет `cebe/php-openapi` в dev-режиме
1. Выполняем команду `vendor/bin/php-openapi convert --write-yaml apidoc.json apidoc.yaml`, получаем файл `apidoc.yaml` 
в корне проекта
1. Выходим из контейнера и выполняем команду 
`docker run --rm -v "${PWD}:/local" openapitools/openapi-generator-cli generate -i /local/apidoc.yaml -g php -o /local/api-client`,
видим ошибки
1. В классе `App\Controller\Api\v1\FeedController` добавляем аннотации к методу `saveUserAction`
    ```php
    /**
     * @Annotations\Get("")
     *
     * @OA\Get(
     *     operationId="getFeed",
     *     tags={"Лента"},
     *     @OA\Parameter(name="userId", in="query", description="ID пользователя", example="135"),
     *     @OA\Parameter(name="count", in="query", description="Количество твитов в ленте", example="5")
     * )
     * @Annotations\QueryParam(name="userId", requirements="\d+")
     * @Annotations\QueryParam(name="count", requirements="\d+", nullable=true)
     */
    ```
1. В классе `App\Controller\Api\v1\SubscriptionController`
    1. Добавляем импорт
        ```php
        use OpenApi\Annotations as OA;
        ```
    1. добавляем аннотации к методу `listSubscriptionByAuthorAction`
        ```php
        /**
         * @Annotations\Get("/list-by-author")
         *
         * @OA\Get(
         *     operationId="listByAuthor",
         *     tags={"Подписки"}
         * )
         *
         * @QueryParam(name="authorId", requirements="\d+")
         */
        ```
    1. добавляем аннотации к методу `listSubscriptionByFollowerAction`
        ```php
        /**
         * @Annotations\Get("/list-by-follower")
         *
         * @OA\Get(
         *     operationId="listByFollower",
         *     tags={"Подписки"}
         * )
         *
         * @QueryParam(name="followerId", requirements="\d+")
         */
        ```
    1. добавляем аннотации к методу `subscribeAction`
        ```php
        /**
         * @Annotations\Post("")
         *
         * @OA\Post(
         *     operationId="subscribe",
         *     tags={"Подписки"}
         * )
         *
         * @RequestParam(name="authorId", requirements="\d+")
         * @RequestParam(name="followerId", requirements="\d+")
         */
        ```
    1. добавляем аннотации к методу `addFollowersAction`
       ```php
       /**
        * @Annotations\Post("/followers")
        *
        * @OA\Post(
        *     operationId="addFollowers",
        *     tags={"Подписки"}
        * )
        *
        * @RequestParam(name="userId", requirements="\d+")
        * @RequestParam(name="followerLogin")
        * @RequestParam(name="count", requirements="\d+")
        * @RequestParam(name="async", requirements="0|1")
        */
       ```
1. В классе `App\Controller\Api\v1\TweetController`
    1. Добавляем импорт
        ```php
        use OpenApi\Annotations as OA;
        ```
    1. добавляем аннотации к методу `postTweetAction`
        ```php
        /**
         * @Annotations\Post("")
         *
         * @OA\Post(
         *     operationId="postTweet",
         *     tags={"Твиты"}
         * )
         *
         * @RequestParam(name="authorId", requirements="\d+")
         * @RequestParam(name="text")
         * @RequestParam(name="async", requirements="0|1", nullable=true)
         */
         ```
    1. добавляем аннотации к методу `getFeedAction`
        ```php
        /**
         * @Annotations\Get("/feed")
         *
         * @OA\Post(
         *     operationId="getFeedFromTweets",
         *     tags={"Твиты"}
         * )
         *
         * @QueryParam(name="userId", requirements="\d+")
         * @QueryParam(name="count", requirements="\d+", nullable=true)
         */
        ```
1. В классе `App\Controller\Api\v1\UserController`
    1. Добавляем импорт
        ```php
        use OpenApi\Annotations as OA;
        ```
    1. добавляем аннотации к методу `addUserAction`
        ```php
        /**
         * @Annotations\Post("")
         *
         * @OA\Post(
         *     operationId="addUser",
         *     tags={"Пользователи"}
         * )
         * @RequestParam(name="login")
         * @RequestParam(name="phone")
         * @RequestParam(name="email")
         * @RequestParam(name="preferEmail", requirements="0|1")
         */
        ```
1. В файле `config/routes.yaml` убираем секцию с area:
    ```yaml
    app.swagger_ui_areas:
      path: /api/doc/{area}
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
1. Опять заходим в контейнер командой `docker exec -it php sh` и выполняем команды
    ```shell script
    php bin/console nelmio:apidoc:dump >apidoc.json
    vendor/bin/php-openapi convert --write-yaml apidoc.json apidoc.yaml
    ```
1. Выходим из контейнера и выполняем команду 
`docker run --rm -v "${PWD}:/local" openapitools/openapi-generator-cli generate -i /local/apidoc.yaml -g php -o /local/api-client`,
видим ошибки
1. Заходим по адресу `http://localhost:7777/api/doc`, пробуем отправить запрос по endpoint'у POST `/api/v1/user`, видим
ошибку
1. В классе `App\Controller\Api\v1\UserController` добавляем аннотацию к методу `addUserAction`
    ```php
    /**
     * @Annotations\Post("")
     *
     * @OA\Post(
     *     operationId="addUser",
     *     tags={"Пользователи"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="login",type="string"),
     *                 @OA\Property(property="phone",type="string"),
     *                 @OA\Property(property="email",type="string"),
     *                 @OA\Property(property="preferEmail",type="integer",pattern="0|1")
     *             )
     *         )
     *     )
     * )
     * @RequestParam(name="login")
     * @RequestParam(name="phone")
     * @RequestParam(name="email")
     * @RequestParam(name="preferEmail", requirements="0|1")
     */
    ```
1. Ещё раз пробуем отправить запрос по endpoint'у POST `/api/v1/user`, видим ответ сервера, проверяем, что в БД
появилась запись
1. Добавляем класс `App\DTO\UserAddedDTO`
    ```php
    <?php
    
    namespace App\DTO;
    
    use OpenApi\Annotations as OA;
    
    class UserAddedDTO
    {
        /**
         * @OA\Property(property="success", type="boolean")
         */
        private $success;
    
        /**
         * @OA\Property(property="userId", type="integer")
         */
        private $userId;
    }
    ```
1. В классе `App\Controller\Api\v1\UserController` исправляем аннотацию к методу `addUserAction`
    ```php
    /**
     * @Annotations\Post("")
     *
     * @OA\Post(
     *     operationId="addUser",
     *     tags={"Пользователи"},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="login", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="preferEmail", type="integer", pattern="0|1")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @Model(type=UserAddedDTO::class)
     *     )
     * )
     * @RequestParam(name="login")
     * @RequestParam(name="phone")
     * @RequestParam(name="email")
     * @RequestParam(name="preferEmail", requirements="0|1")
     */
    ```
1. Заходим по адресу `http://localhost:7777/api/doc`, видим схему и описание ответа в endpoint'е POST /api/v1/user
   