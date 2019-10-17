<?php

namespace A17\Twill\Tests\Integration;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Support\Str;
use A17\Twill\AuthServiceProvider;
use A17\Twill\TwillServiceProvider;
use A17\Twill\RouteServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Contracts\Console\Kernel;
use A17\Twill\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    const DATABASE_MEMORY = ':memory:';
    const DEFAULT_PASSWORD = 'secret';
    const DEFAULT_LOCALE = 'en_US';
    const DB_CONNECTION = 'sqlite';

    /**
     * @var \Faker\Generator
     */
    public $faker;

    /**
     * @var \A17\Twill\Tests\Integration\UserClass
     */
    public $superAdmin;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    public $files;

    /**
     * @var \Carbon\Carbon
     */
    public $now;

    private function configTwill($app)
    {
        $app['config']->set('twill.admin_app_url', '');
        $app['config']->set('twill.admin_app_path', 'twill');
        $app['config']->set('twill.auth_login_redirect_path', '/twill');
    }

    /**
     * @param $app
     */
    protected function configureDatabase($app)
    {
        $app['config']->set(
            'database.default',
            $connection = env('DB_CONNECTION', self::DB_CONNECTION)
        );

        $app['config']->set('activitylog.database_connection', $connection);

        $app['config']->set(
            'database.connections.' . $connection . '.database',
            env('DB_DATABASE', self::DATABASE_MEMORY)
        );
    }

    /**
     * Configure storage path.
     *
     * @param $app
     */
    private function configureStorage($app)
    {
        $app['config']->set(
            'logging.channels.single.path',
            __DIR__ . '/../storage/logs/laravel.log'
        );
    }

    /**
     * Create sqlite database, if needed.
     *
     * @param $database
     */
    protected function createDatabase($database): void
    {
        if ($database !== self::DATABASE_MEMORY) {
            if (file_exists($database)) {
                unlink($database);
            }

            touch($database);
        }
    }

    /**
     * Setup tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->instantiateFaker();

        $this->installTwill();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function boot($app)
    {
        $this->files = $app->make(Filesystem::class);

        $this->prepareLaravelDirectory();
    }

    /**
     * Fake a super admin.
     *
     * @return \A17\Twill\Tests\Integration\UserClass
     */
    public function makeNewSuperAdmin()
    {
        $user = new UserClass();

        $user->name = $this->faker->name;
        $user->email = $this->faker->email;
        $user->password = self::DEFAULT_PASSWORD;

        return $user;
    }

    /**
     * Instantiate Faker.
     */
    protected function instantiateFaker(): void
    {
        $this->faker = Faker::create(self::DEFAULT_LOCALE);
    }

    /**
     * Get application package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AuthServiceProvider::class,
            RouteServiceProvider::class,
            TwillServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $this->freezeTime();

        $this->configureStorage($app);

        $this->configTwill($app);

        $this->configureDatabase($app);

        $this->boot($app);

        $this->setUpDatabase($app);
    }

    /**
     * Setup up the database.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $connection = $app['config']['database.default'];

        if (
            $driver =
                $app['config'][
                    'database.connections.' . $connection . '.driver'
                ] === self::DB_CONNECTION
        ) {
            $this->createDatabase(
                $app['config'][
                    'database.connections.' . $connection . '.database'
                ]
            );
        }
    }

    /**
     * Our dd.
     *
     * @param $value
     */
    public function dd($value)
    {
        dd($value ?? $this->app[Kernel::class]->output());
    }

    /**
     * Get or make a super admin.
     *
     * @param $force
     * @return \A17\Twill\Models\User|\A17\Twill\Tests\Integration\UserClass
     */
    public function getSuperAdmin($force = false)
    {
        return $this->superAdmin =
            !$this->superAdmin || $force
                ? $this->makeNewSuperAdmin()
                : $this->superAdmin;
    }

    /**
     * Clear and make needed directories in the Laravel directory.
     */
    protected function prepareLaravelDirectory()
    {
        array_map(
            'unlink',
            glob($this->getBasePath() . '/database/migrations/*')
        );

        if (!file_exists($directory = twill_path('Http/Controllers'))) {
            $this->files->makeDirectory($directory, 744, true);
        }
    }

    /**
     * Install Twill.
     */
    public function installTwill()
    {
        $this->artisan('twill:install')
            ->expectsQuestion('Enter an email', $this->getSuperAdmin()->email)
            ->expectsQuestion(
                'Enter a password',
                $this->getSuperAdmin()->password
            )
            ->expectsQuestion(
                'Confirm the password',
                $this->getSuperAdmin()->password
            );
    }

    /**
     * Delete a directory.
     *
     * @param string $param
     */
    public function deleteDirectory(string $param)
    {
        if ($this->files->exists($param)) {
            $this->files->deleteDirectory($param);
        }
    }

    /**
     * Get a collection with all routes.
     *
     * @param null $method
     * @return \Illuminate\Support\Collection
     */
    public function getAllRoutes($method = null)
    {
        $routes = Route::getRoutes();

        if ($method) {
            $routes = $routes->get($method);
        }

        return collect($routes);
    }

    /**
     * Get a collection with all package uris.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllUris()
    {
        return $this->getAllRoutes()
            ->filter(function ($route) {
                return Str::startsWith($route->action['uses'], 'A17\Twill');
            })
            ->pluck('uri')
            ->sort()
            ->unique()
            ->values();
    }

    /**
     * Send request to an ajax route.
     *
     * @param $uri
     * @param string $method
     * @param array $parameters
     * @param array $cookies
     * @param array $files
     * @param array $server
     * @param null $content
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    public function ajax(
        $uri,
        $method = 'GET',
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null
    ) {
        $server = array_merge($server, [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        return $this->call(
            $method,
            $uri,
            $parameters,
            $cookies,
            $files,
            $server,
            $content
        );
    }

    /**
     * Login the current SuperUser.
     *
     * @return \Illuminate\Foundation\Testing\TestResponse|void
     */
    protected function login()
    {
        return $this->followingRedirects()->call('POST', '/twill/login', [
            'email' => $this->getSuperAdmin()->email,
            'password' => $this->getSuperAdmin()->password,
        ]);
    }

    public function freezeTime()
    {
        Carbon::setTestNow($this->now = Carbon::now());
    }
}

class UserClass
{
    public $name;

    public $email;

    public $password;
}
