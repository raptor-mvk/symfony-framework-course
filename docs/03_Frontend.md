1. Устанавливаем бандл для Twig командой `composer require symfony/twig-bundle`
1. Создаём класс `App\Entity\User`
    ```php
    <?php
    
    namespace App\Entity;
    
    class User
    {
        private string $firstName;
        private string $middleName;
        private string $lastName;
        private string $phone;
    
        public function __construct(string $firstName, string $middleName, string $lastName, string $phone)
        {
            $this->firstName = $firstName;
            $this->middleName = $middleName;
            $this->lastName = $lastName;
            $this->phone = $phone;
        }
    
        public function getFirstName(): string
        {
            return $this->firstName;
        }
    
        public function setFirstName(string $firstName): void
        {
            $this->firstName = $firstName;
        }
    
        public function getMiddleName(): string
        {
            return $this->middleName;
        }
    
        public function setMiddleName(string $middleName): void
        {
            $this->middleName = $middleName;
        }
    
        public function getLastName(): string
        {
            return $this->lastName;
        }
    
        public function setLastName(string $lastName): void
        {
            $this->lastName = $lastName;
        }
    
        public function getPhone(): string
        {
            return $this->phone;
        }
    
        public function setPhone(string $phone): void
        {
            $this->phone = $phone;
        }
    }
    ```
1. Создаём класс `App\Service\UserService`
    ```php
    <?php
   
    namespace App\Service;
   
    use App\Entity\User;
   
    class UserService
    {
        /**
         * @return User[]
         */
        public function getUserList(): array
        {
            return [
                new User('Иван', 'Сергеевич', 'Сапогов', '+71112223344'),
                new User('Фёдор', 'Викторович', 'Лаптев', '+72223334455'),
                new User('Пётр', 'Михайлович', 'Стеклов', '+73334445566'),
                new User('Игнат', 'Глебович', 'Лопухов', '+74445556677'),
            ];
        }
    }
    ```
1. Создаём файл `templates/list.twig`
    ```html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>User list</title>
        </head>
        <body>
            <ul id="user.list">
                {% for user in users %}
                    <li>{{ user.firstName }} {{ user.middleName }} {{ user.lastName }} ({{ user.phone }}) </li>
                {% endfor %}
            </ul>
        </body>
    </html>
    ```
1. Исправляем класс `App\Controller\WorldController`:
    1. Наследуем класс от `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`
    1. Инжектим инстанс сервиса `App\Service\UserService` в конструкторе
        ```php
        private UserService $userService;
      
        public function __construct(UserService $userService)
        {
            $this->userService = $userService;
        }
        ```
   1. Исправляем метод `hello`
        ```php
        public function hello(): Response
        {
            return $this->render('list.twig', ['users' => $this->userService->getUserList()]);
        }
        ```
1. Запускаем контейнеры командой `docker-compose up`
1. Заходим по адресу `http://localhost:777/world/hello`, видим список пользователей
1. Исправляем файл `templates/list.twig`, добавляя пост-обработку значений полей через фильтры
    ```html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>User list</title>
        </head>
        <body>
            <ul id="user.list">
                {% for user in users %}
                    <li>{{ user.firstName|upper }} {{ user.middleName|lower }} {{ user.lastName }} ({{ user.phone }}) </li>
                {% endfor %}
            </ul>
        </body>
    </html>
    ```
1. Обновляем страницу в браузере, видим результат применения фильтров
1. Переименовываем файл `templates/base.html.twig` в `layout.twig`
1. Создаём файл `templates/user-content.twig`
    ```html
    {% extends 'layout.twig' %}
    {% block title %}
    User list
    {% endblock %}
    {% block body %}
    <ol id="user.list">
        {% for user in users %}
            <li>{{ user.firstName|upper }} {{ user.middleName|lower }} {{ user.lastName }} ({{ user.phone }}) </li>
        {% endfor %}
    </ol>
    {% endblock %}
    ```
1. Исправляем в классе `App\Controller\WorldController` метод `hello`
    ```php
    public function hello(): Response
    {
        return $this->render('user-content.twig', ['users' => $this->userService->getUserList()]);
    }
    ```
1. Обновляем страницу в браузере, видим, что список стал нумерованным
1. Исправляем файл `templates/layout.twig`
    ```html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>{% block title %}Welcome!{% endblock %}</title>
            {# Run `composer require symfony/webpack-encore-bundle`
               and uncomment the following Encore helpers to start using Symfony UX #}
            {% block stylesheets %}
                {#{{ encore_entry_link_tags('app') }}#}
            {% endblock %}
    
            {% block javascripts %}
                {#{{ encore_entry_script_tags('app') }}#}
            {% endblock %}
        </head>
        <body>
            {% block body %}{% endblock %}
            {% block footer %}{% endblock %}
        </body>
    </html>
    ```
1. Исправляем файл `templates/user-content.twig`
    ```html
    {% extends 'layout.twig' %}
    {% block title %}
    User list
    {% endblock %}
    {% block body %}
    <ol id="user.list">
         {% for user in users %}
             <li>{{ user.firstName|upper }} {{ user.middleName|lower }} {{ user.lastName }} ({{ user.phone }}) </li>
         {% endfor %}
    </ol>
    {% endblock %}
    {% block footer %}
    <h1>Footer</h1>
    {{ block('body') }}
    <h1>Repeat twice</h1>
    {{ block('body') }}
    {% endblock %}
    ```
1. Обновляем страницу в браузере, видим, что список выводится трижды с заголовками между списками
1. Создаём файл `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
    {% block title %}
    User table
    {% endblock %}
    {% block body %}
     <table>
          <tbody>
                <tr><th>Имя</th><th>Отчество</th><th>Фамилия</th><th>Телефон</th></tr>
                {% for user in users %}
                    <tr><td>{{ user.firstName }}</td><td>{{ user.middleName }}</td><td>{{ user.lastName }}</td><td>({{ user.phone }})</td></tr>
                {% endfor %}
          </tbody>
     </table>
    {% endblock %}
    ```
1. Исправляем в классе `App\Controller\WorldController` метод `hello`
    ```php
    public function hello(): Response
    {
        return $this->render('user-table.twig', ['users' => $this->userService->getUserList()]);
    }
    ```
1. Обновляем страницу в браузере, видим, что вместо списка выведена таблица
1. Создаём файл `templates/macros.twig`
    ```html
    {% macro user_table_body(users) %}
         {% for user in users %}
             <tr><td>({{ user.phone }})</td><td>{{ user.firstName|lower }}</td><td>{{ user.middleName|upper }}</td><td>{{ user.lastName }}</td></tr>
         {% endfor %}
    {% endmacro %}
    ```
1. Исправляем файл `templates/user-table.twig`:
    ```html
    {% extends 'layout.twig' %}
    {% import 'macros.twig' as macros %}
    {% block title %}
    User table
    {% endblock %}
    {% block body %}
    <table>
         <tbody>
             <tr><th>Телефон</th><th>Имя</th><th>Отчество</th><th>Фамилия</th></tr>
             {{ macros.user_table_body(users) }}
         </tbody>
    </table>
    {% endblock %}
    ```
1. Обновляем страницу в браузере, видим, что в таблице первой колонкой стала колонка "Телефон"
1. Устанавливаем Webpack Encore командой `composer require symfony/webpack-encore-bundle`
1. Устанавливаем зависимости командой `yarn install`
1. Устанавливаем загрузчик для работы с SASS командой `yarn add sass-loader@^10.0.0 node-sass --dev`
1. Устанавливаем bootstrap командой `yarn add bootstrap --dev`
1. Устанавливаем плагины для работы с Vue.js `yarn add vue vue-loader vue-template-compiler --dev`
1. Выполняем сборку для dev-окружения командой `yarn encore dev`
1. Видим собранные файлы в директории `public/build`
1. Выполняем сборку для dev-окружения командой `yarn encore production`
1. Видим собранные файлы в директории `public/build`, которые обфусцированы и содержат хэш в имени
1. В файле `templates/layout.twig` убираем комментарии с вызовов макросов для загрузки CSS и JS
    ```html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <title>{% block title %}Welcome!{% endblock %}</title>
            {# Run `composer require symfony/webpack-encore-bundle`
               and uncomment the following Encore helpers to start using Symfony UX #}
            {% block stylesheets %}
                {{ encore_entry_link_tags('app') }}
            {% endblock %}
    
            {% block javascripts %}
                {{ encore_entry_script_tags('app') }}
            {% endblock %}
        </head>
        <body>
            {% block body %}{% endblock %}
            {% block footer %}{% endblock %}
        </body>
    </html>   
    ```
1. Выполняем сборку для dev-окружения командой `yarn encore dev`
1. Обновляем страницу в браузере, видим, что фон стал серым, т.е. CSS-стили загрузились
1. Переименовываем файл `assets/styles/app.css` в `app.scss` и исправляем его
    ```sass
    $color: orange;
    
    body {
        background-color: $color;
    }
    ```
1. Исправляем файл `assets/app.js`
    ```js
    /*
     * Welcome to your app's main JavaScript file!
     *
     * We recommend including the built version of this JavaScript file
     * (and its CSS file) in your base layout (base.html.twig).
     */
    
    // any CSS you import will output into a single css file (app.css in this case)
    import './styles/app.scss';
    
    // start the Stimulus application
    import './bootstrap';
    ```
1. Исправляем файл `webpack.config.js`, убираем комментарий в строке 59 (`//.enableSassLoader()`)
1. Выполняем сборку для dev-окружения командой `yarn encore dev`
1. Обновляем страницу в браузере, видим, что фон стал оранжевым, т.е. SASS-компилятор отработал
1. Исправляем файл `assets/styles/app.scss`
    ```sass
    @import "~bootstrap/scss/bootstrap";
    
    $color: orange;
    
    body {
        background-color: $color;
    }
    ```
1. Исправляем файл `templates/user-table.twig`
    ```html
    {% extends 'layout.twig' %}
    {% import 'macros.twig' as macros %}
    {% block title %}
    User table
    {% endblock %}
    {% block body %}
    <table class="table table-hover">
        <tbody>
            <tr><th>Телефон</th><th>Имя</th><th>Отчество</th><th>Фамилия</th></tr>
            {{ macros.user_row(users) }}
        </tbody>
    </table>
    {% endblock %}
    ```
1. Выполняем сборку для dev-окружения командой `yarn encore dev`
1. Обновляем страницу в браузере, видим, что таблица отображается в bootstrap-стиле
1. Исправляем файл `webpack.config.js`
    ```js
    const Encore = require('@symfony/webpack-encore');
    
    // Manually configure the runtime environment if not already configured yet by the "encore" command.
    // It's useful when you use tools that rely on webpack.config.js file.
    if (!Encore.isRuntimeEnvironmentConfigured()) {
        Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
    }
    
    Encore
        // directory where compiled assets will be stored
        .setOutputPath('public/build/')
        // public path used by the web server to access the output path
        .setPublicPath('/build')
        // only needed for CDN's or sub-directory deploy
        //.setManifestKeyPrefix('build/')
    
        /*
         * ENTRY CONFIG
         *
         * Each entry will result in one JavaScript file (e.g. app.js)
         * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
         */
        .addEntry('app', './assets/app.js')
    
        // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
        .enableStimulusBridge('./assets/controllers.json')
    
        // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
        .splitEntryChunks()
    
        // will require an extra script tag for runtime.js
        // but, you probably want this, unless you're building a single-page app
        .enableSingleRuntimeChunk()
    
        /*
         * FEATURE CONFIG
         *
         * Enable & configure other features below. For a full
         * list of features, see:
         * https://symfony.com/doc/current/frontend.html#adding-more-features
         */
        .cleanupOutputBeforeBuild()
        .enableBuildNotifications()
        .enableSourceMaps(!Encore.isProduction())
        // enables hashed filenames (e.g. app.abc123.css)
        .enableVersioning(Encore.isProduction())
    
        .configureBabel((config) => {
            config.plugins.push('@babel/plugin-proposal-class-properties');
        })
    
        // enables @babel/preset-env polyfills
        .configureBabelPresetEnv((config) => {
            config.useBuiltIns = 'usage';
            config.corejs = 3;
        })
    
        // enables Sass/SCSS support
        .enableSassLoader()
        .enableVueLoader()
    
        // uncomment if you use TypeScript
        //.enableTypeScriptLoader()
    
        // uncomment if you use React
        //.enableReactPreset()
    
        // uncomment to get integrity="..." attributes on your script & link tags
        // requires WebpackEncoreBundle 1.4 or higher
        //.enableIntegrityHashes(Encore.isProduction())
    
        // uncomment if you're having problems with a jQuery plugin
        //.autoProvidejQuery()
    ;
    
    module.exports = Encore.getWebpackConfig();
    ```
1. Создаём файл `assets/components/App.vue`
    ```vue
    <template>
        <table class="table table-hover">
            <thead>
                <tr><th>Имя</th><th>Отчество</th><th>Фамилия</th><th>Телефон</th></tr>
            </thead>
            <tbody>
                <tr v-for="user in users">
                    <td v-for="key in columns">
                        {{ user[key] }}
                    </td>
                </tr>
            </tbody>
        </table>
    </template>
    
    <script>
        export default {
            data() {
                return {
                    users: [],
                    columns: ['firstName', 'middleName', 'lastName', 'phone']
                };
            },
            mounted() {
                let data = document.querySelector("div[data-users]");
                let userList = JSON.parse(data.dataset.users);
    
                this.users.push.apply(this.users, userList);
            }
        };
    </script>
    ```
1. Создаём файл `templates/user-vue.twig`
    ```html
    {% import 'macros.twig' as macros %}
    {% extends 'layout.twig' %}
    {% block title %}
    User list
    {% endblock %}
    {% block body %}
    <div ref="users" data-users="{{ users }}">
    </div>

    <div id="app">
        <app></app>
    </div>
    {% endblock %}
    ```
1. Исправляем класс `App\Entity\User`, добавляя новый метод `toArray`
    ```php
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
        ];
    }
    ```
1. Исправляем класс `App\Service\UserService`, добавляя новый метод `getUsersListVue`
    ```php
    public function getUsersListVue(): array
    {
        return array_map(
            static fn(User $user) => $user->toArray(),
            $this->getUserList()
        );
    }
    ```
1. Исправляем в классе `App\Controller\WorldController` метод `hello`
    ```php
    public function hello(): Response
    {
        return $this->render('user-vue.twig', ['users' => json_encode($this->userService->getUsersListVue())]);
    }
    ```
1. Исправляем файл `assets/app.js`
    ```js
    /*
     * Welcome to your app's main JavaScript file!
     *
     * We recommend including the built version of this JavaScript file
     * (and its CSS file) in your base layout (base.html.twig).
     */
    
    // any CSS you import will output into a single css file (app.css in this case)
    import './styles/app.scss';
    import Vue from 'vue';
    import App from './components/App';
    
    // start the Stimulus application
    import './bootstrap';
    
    new Vue({
        el: '#app',
        render: h => h(App)
    });
    ```
1. Выполняем сборку для dev-окружения командой `yarn encore dev`
1. Обновляем страницу в браузере, видим, что таблица отображается через Vue-приложение
   